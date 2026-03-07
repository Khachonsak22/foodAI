<?php
session_start();
include '../config/connect.php';

// ดักไว้เลย ถ้ายังไม่ล็อกอิน ให้เตะออก
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);
$userId = $_SESSION['user_id'] ?? 0;

// ─── ระบบรับคำสั่งเมื่อผู้ใช้กด "คลิกบันทึกเมนูเอง" ───
if (isset($input['action']) && $input['action'] === 'save_ai_menu') {
    $mName = $input['menu_name'] ?? '';
    $mCal = (int)($input['calories'] ?? 0);
    $mDesc = $input['description'] ?? '';
    if ($mName && $userId > 0) {
        $ins = $conn->prepare("INSERT INTO ai_saved_menus (user_id, menu_name, calories, description) VALUES (?, ?, ?, ?)");
        $ins->bind_param("isis", $userId, $mName, $mCal, $mDesc);
        if ($ins->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error']);
        }
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit();
}
// ────────────────────────────────────────────────

$userMessage = $input['message'] ?? '';
if (!$userMessage) { echo json_encode(['reply' => '']); exit; }

// บันทึก Log ฝั่ง User
if ($userId > 0) {
    $stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'user', ?)");
    $stmt->bind_param("is", $userId, $userMessage);
    $stmt->execute();
}

// ดึงข้อมูลสุขภาพ (เป้าหมาย, โรคประจำตัว, และชื่อเป้าหมายจากตาราง goals)
$profile = ['target' => 2000, 'conditions' => 'ไม่มี', 'goal_title' => 'ไม่ระบุ', 'goal_desc' => ''];
if ($userId > 0) {
    $stmt = $conn->prepare("
        SELECT hp.daily_calorie_target, hp.health_conditions, g.title AS goal_title, g.description AS goal_desc
        FROM health_profiles hp
        LEFT JOIN goals g ON hp.goal_preference = g.goal_key
        WHERE hp.user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $profile['target'] = $row['daily_calorie_target'];
        $profile['conditions'] = !empty($row['health_conditions']) ? $row['health_conditions'] : 'ไม่มี';
        $profile['goal_title'] = !empty($row['goal_title']) ? $row['goal_title'] : 'ไม่ระบุ';
        $profile['goal_desc']  = !empty($row['goal_desc']) ? $row['goal_desc'] : '';
    }
}

// ดึงข้อมูลอาหารที่แพ้
$allergies = [];
if ($userId > 0) {
    $stmt = $conn->prepare("SELECT i.name FROM user_allergies ua JOIN ingredients i ON ua.ingredient_id = i.id WHERE ua.user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) {
        $allergies[] = $r['name'];
    }
}
$allergy_str = empty($allergies) ? 'ไม่มี' : implode(", ", $allergies);

// ดึงรายชื่อเมนูที่มีในระบบ
$existing_recipes = [];
$r_stmt = $conn->query("SELECT title FROM recipes LIMIT 200");
while($r_row = $r_stmt->fetch_assoc()) {
    $existing_recipes[] = $r_row['title'];
}
$recipes_str = empty($existing_recipes) ? 'ไม่มี' : implode(", ", $existing_recipes);

// ดึงประวัติเมนูที่เคยกิน (เพื่อกันซ้ำ)
$past_menus = [];
if ($userId > 0) {
    $past_stmt = $conn->prepare("SELECT menu_name FROM ai_saved_menus WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $past_stmt->bind_param("i", $userId);
    $past_stmt->execute();
    $past_res = $past_stmt->get_result();
    while($r = $past_res->fetch_assoc()) {
        $past_menus[] = $r['menu_name'];
    }
}
$past_menu_str = empty($past_menus) ? 'ไม่มี' : implode(", ", $past_menus);

// System Prompt (เพิ่มกฎเหล็กเรื่องห้ามใส่คำว่า มื้อเช้า มื้อเย็น)
$systemPrompt = "คุณคือเชฟและนักโภชนาการอัจฉริยะ ตอบกลับเป็นภาษาไทยที่สุภาพ
ข้อมูลผู้ใช้:
- เป้าหมายแคลอรี่: {$profile['target']} kcal/วัน
- เป้าหมายสุขภาพ: {$profile['goal_title']}
- โรคประจำตัว: {$profile['conditions']}
- อาหารที่แพ้: {$allergy_str}
- เมนูที่เพิ่งทานไปเมื่อเร็วๆ นี้ (ห้ามแนะนำซ้ำ): {$past_menu_str}

รายชื่อเมนูที่มีในระบบ: {$recipes_str}

คำสั่งสำคัญ:
1. จัดเมนูอาหารไทยที่มีอยู่จริง 4 มื้อ (ไม่ซ้ำกับที่เคยกิน)
2. ตรวจสอบ 'โรคประจำตัว' และ 'อาหารที่แพ้' อย่างเคร่งครัด
3. 💥 กฎเหล็ก: ในช่อง \"name\" ของ JSON ห้ามมีคำนำหน้าหรือต่อท้าย เช่น 'มื้อเช้า', 'มื้อเย็น', 'มื้อว่าง', หรือตัวเลขลำดับ เด็ดขาด! ให้ตอบเฉพาะ \"ชื่อเมนูอาหารเพียวๆ\" เท่านั้น (เช่น 'ข้าวกะเพราหมูสับ')
4. ให้ตอบกลับเป็น JSON Format เท่านั้น

รูปแบบ JSON ที่ต้องการ:
{
  \"chat_response\": \"คำอธิบาย...\",
  \"recommended_menus\": [
    { \"name\": \"ชื่อเมนูอาหารเพียวๆ\", \"calories\": 400, \"desc\": \"อธิบายส่วนผสมสั้นๆ\" }
  ]
}";

$setting_stmt = $conn->query("SELECT api_key, api_model FROM system_settings WHERE id = 1");
if ($setting_stmt && $setting_stmt->num_rows > 0) {
    $setting = $setting_stmt->fetch_assoc();
    $apiKey = $setting['api_key'];
    $apiModel = $setting['api_model']; 
} else {
    $apiKey = GEMINI_API_KEY;
    $apiModel = 'gemini-2.5-flash';
}

$url = "[https://generativelanguage.googleapis.com/v1beta/models/](https://generativelanguage.googleapis.com/v1beta/models/){$apiModel}:generateContent?key=" . $apiKey;

$data = [
    "contents" => [[ "parts" => [[ "text" => $systemPrompt . "\nUser: " . $userMessage ]] ]]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$responseData = json_decode($response, true);

if ($httpcode == 200 && isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    $rawText = $responseData['candidates'][0]['content']['parts'][0]['text'];
    
    // ดึงเฉพาะข้อมูล JSON เท่านั้น เพื่อป้องกัน Error จากคำพูดส่วนเกินของ AI
    $cleanJson = '';
    if (preg_match('/\{.*\}/s', $rawText, $matches)) {
        $cleanJson = $matches[0];
    } else {
        $cleanJson = $rawText;
    }
    
    $parsedData = json_decode($cleanJson, true);
    if (!$parsedData) {
        $parsedData = ["chat_response" => "ขออภัยค่ะ ฉันไม่สามารถจัดรูปแบบข้อมูลได้ กรุณาลองใหม่อีกครั้ง", "recommended_menus" => []];
    }

    if ($userId > 0) {
        $menusToSave = $parsedData['recommended_menus'] ?? [];
        
        // ── สร้างปุ่มคลิกบันทึก ฝังไปในแชทให้ผู้ใช้กดเอง ──
        if (!empty($menusToSave)) {
            $htmlButtons = "<div style='margin-top:15px; display:flex; flex-direction:column; gap:8px;'>";
            $htmlButtons .= "<p style='font-size:0.85rem; color:#4b5563; margin-bottom:5px;'>📌 <b>คลิกปุ่มด้านล่างเพื่อบันทึกเมนูที่คุณต้องการ:</b></p>";
            
            foreach ($menusToSave as $m) {
                $mName = htmlspecialchars($m['name'] ?? '');
                $mCal = (int)($m['calories'] ?? 0);
                $mDesc = htmlspecialchars($m['desc'] ?? '');
                
                // แปลงข้อมูลให้ปลอดภัยสำหรับส่งค่าผ่าน Javascript
                $mNameJs = addslashes($m['name'] ?? '');
                $mDescJs = addslashes($m['desc'] ?? '');
                
                // ชุดคำสั่ง JS สำหรับยิง API บันทึกข้อมูล
                $onClick = "event.preventDefault(); var btn=this; btn.innerText='กำลังบันทึก...'; fetch('api_chat.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'save_ai_menu', menu_name:'{$mNameJs}', calories:{$mCal}, description:'{$mDescJs}'})}).then(r=>r.json()).then(d=>{if(d.status==='success'){btn.innerText='✅ บันทึกแล้ว'; btn.style.background='#9ca3af'; btn.disabled=true;}else{btn.innerText='บันทึก'; alert('เกิดข้อผิดพลาด');}}).catch(()=>alert('เกิดข้อผิดพลาดในการเชื่อมต่อ'));";
                
                $onClickAttr = htmlspecialchars($onClick, ENT_QUOTES, 'UTF-8');
                
                $htmlButtons .= "<div style='background:#fff; border:1px solid #d1d5db; padding:10px; border-radius:10px; display:flex; justify-content:space-between; align-items:center; gap:10px;'>";
                $htmlButtons .= "<div style='flex:1;'><div style='font-weight:600; color:#16a34a; font-size:0.9rem;'>{$mName}</div><div style='font-size:0.75rem; color:#6b7280;'>🔥 {$mCal} kcal - {$mDesc}</div></div>";
                $htmlButtons .= "<button onclick=\"{$onClickAttr}\" style='background:#22c55e; color:#fff; border:none; padding:6px 12px; border-radius:8px; font-size:0.8rem; font-family:Kanit,sans-serif; cursor:pointer; transition:0.2s; white-space:nowrap;'>บันทึก</button>";
                $htmlButtons .= "</div>";
            }
            $htmlButtons .= "</div>";
            
            // นำปุ่มแนบต่อท้ายข้อความแชท
            $parsedData['chat_response'] .= $htmlButtons;
        }

        // บันทึกประวัติแชทลงฐานข้อมูล (จะบันทึกปุ่มลงไปด้วยเพื่อให้โหลดแชทเก่าแล้วยังกดได้)
        $finalMessageToSave = $parsedData['chat_response'];
        if (!empty($menusToSave)) {
            $finalMessageToSave .= '|||MENUS|||' . json_encode($menusToSave, JSON_UNESCAPED_UNICODE);
        }
        
        $log_stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'ai', ?)");
        $log_stmt->bind_param("is", $userId, $finalMessageToSave);
        $log_stmt->execute();
    }

    echo json_encode($parsedData);
} else {
    $error_msg = isset($responseData['error']['message']) ? $responseData['error']['message'] : 'ระบบ AI ขัดข้องชั่วคราว (HTTP '.$httpcode.')';
    echo json_encode([
        'chat_response' => 'ขออภัยค่ะ มีข้อผิดพลาดจากเซิร์ฟเวอร์ AI: ' . $error_msg, 
        'recommended_menus' => []
    ]);
}
?>