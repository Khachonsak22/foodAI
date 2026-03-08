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

// ─── เพิ่มใหม่: ดึงชื่อผู้ใช้ ───
$userName = 'ผู้ใช้งาน';
if ($userId > 0) {
    $u_stmt = $conn->prepare("SELECT first_name FROM users WHERE id = ?");
    $u_stmt->bind_param("i", $userId);
    $u_stmt->execute();
    if ($u_row = $u_stmt->get_result()->fetch_assoc()) {
        $userName = $u_row['first_name'];
    }
}

// ─── เพิ่มใหม่: ระบบทักทายตอนเปิดหน้าเว็บครั้งแรกของวัน ───
if (isset($input['action']) && $input['action'] === 'get_greeting') {
    // เช็คว่าวันนี้มีการคุยกันหรือยัง จะได้ไม่ทักซ้ำเวลารีเฟรชหน้า
    $check_stmt = $conn->prepare("SELECT id FROM chat_logs WHERE user_id = ? AND DATE(created_at) = CURDATE() LIMIT 1");
    $check_stmt->bind_param("i", $userId);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows === 0) {
        // ถ้ายังไม่มีแชทเลยในวันนี้ ให้ AI เริ่มทักก่อน
        $greet_msg = "สวัสดีครับคุณ {$userName}! 🌅 วันนี้อยากทานอะไร หรือให้เชฟช่วยคิดเมนูสำหรับ 1 วัน ที่เหมาะกับคุณให้ครับ?";
        
        // บันทึกลง log ฝั่ง AI 
        $log_stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'ai', ?)");
        $log_stmt->bind_param("is", $userId, $greet_msg);
        $log_stmt->execute();
        
        echo json_encode(['chat_response' => $greet_msg, 'recommended_menus' => []]);
    } else {
        // วันนี้ทักไปแล้ว ไม่ต้องส่งข้อความทักทายซ้ำ
        echo json_encode(['chat_response' => '', 'recommended_menus' => []]);
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
    // JOIN ตาราง goals เพื่อให้รู้ชื่อภาษาไทยของเป้าหมาย
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

// ดึงรายชื่อเมนูที่มีในระบบ (เพื่อใช้พิจารณาก่อนคิดเมนูใหม่)
$existing_recipes = [];
$r_stmt = $conn->query("SELECT title FROM recipes LIMIT 200");
while($r_row = $r_stmt->fetch_assoc()) {
    $existing_recipes[] = $r_row['title'];
}
//เพิ่มบรรทัดนี้เพื่อแปลง Array ให้เป็น String 
$recipes_str = empty($existing_recipes) ? 'ไม่มี' : implode(", ", $existing_recipes);


// ── ดึงประวัติเมนูที่ AI เคยแนะนำไปแล้ว 10 เมนูล่าสุด (เพื่อกัน AI แนะนำซ้ำ) ──
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


// System Prompt (เพิ่มชื่อผู้ใช้และคำสั่งให้เรียกชื่อ)
$systemPrompt = "คุณคือเชฟและนักโภชนาการอัจฉริยะ ให้คุณเป็นผู้ชายและตอบกลับเป็นภาษาไทยที่สุภาพ
ข้อมูลผู้ใช้:
- ชื่อผู้ใช้: {$userName}
- เป้าหมายแคลอรี่: {$profile['target']} kcal/วัน
- เป้าหมายสุขภาพ (Goal): {$profile['goal_title']} ({$profile['goal_desc']})
- โรคประจำตัว: {$profile['conditions']}
- อาหารที่แพ้: {$allergy_str}
- เมนูที่เพิ่งทานไปเมื่อเร็วๆ นี้ (ห้ามแนะนำซ้ำเด็ดขาด): {$past_menu_str}

รายชื่อเมนูที่มีในฐานข้อมูลระบบ: {$recipes_str}

คำสั่งสำคัญ:
1. ให้คุณเรียกชื่อผู้ใช้ว่า 'คุณ{$userName}' ในการสนทนาให้ดูเป็นธรรมชาติและเป็นกันเอง
2. หากผู้ใช้ต้องการให้จัดเมนู ให้คุณจัดเมนูอาหารไทยที่มีอยู่จริง รวมทั้งหมด 4 มื้อ (มื้อเช้า, มื้อกลางวัน, มื้อเย็น, และมื้อว่าง 1 มื้อ)
3. เมนูทั้ง 4 มื้อในครั้งนี้ จะต้องไม่ซ้ำกันเองเลย และต้องไม่ซ้ำกับ 'เมนูที่เพิ่งทานไปเมื่อเร็วๆ นี้' โดยเด็ดขาด เพื่อความหลากหลาย
4. คุณต้องตรวจสอบ 'โรคประจำตัว' และ 'อาหารที่แพ้' ของผู้ใช้อย่างเคร่งครัด เมนูที่แนะนำต้องปลอดภัย 100%
5. พยายามเลือกเมนูจาก 'รายชื่อเมนูที่มีในฐานข้อมูลระบบ' ก่อน หากเมนูนั้นปลอดภัยและไม่ซ้ำ
6. หากในฐานข้อมูลไม่มีเมนูที่เหมาะสม หรือเมนูที่มีมันซ้ำกับที่เคยกินไปแล้ว ให้คิดเมนูอาหารไทยขึ้นมาใหม่ให้ครบ 4 มื้อ
7. ให้ตอบกลับในรูปแบบ **JSON เท่านั้น** โดยไม่มี Markdown (```json) ครอบ
8. ในช่อง \"name\" ของ recommended_menus ให้ใส่แค่ชื่อเมนูอาหารเพียวๆ ห้ามมีคำว่า มื้อเช้า มื้อเย็น นำหน้าเด็ดขาด

รูปแบบ JSON ที่ต้องการ:
{
  \"chat_response\": \"ข้อความอธิบายว่าทำไมถึงเลือกเมนูเหล่านี้ ปลอดภัยต่อโรคประจำตัวหรืออาหารที่แพ้อย่างไร (สามารถใช้ Markdown ตกแต่งข้อความได้)\",
  \"recommended_menus\": [
    { \"name\": \"ชื่อเมนูเพียวๆ 1 \", \"calories\": 400, \"desc\": \"คำอธิบายส่วนผสมสั้นๆ\" },
    { \"name\": \"ชื่อเมนูเพียวๆ 2 \", \"calories\": 500, \"desc\": \"คำอธิบายส่วนผสมสั้นๆ\" },
    { \"name\": \"ชื่อเมนูเพียวๆ 3 \", \"calories\": 150, \"desc\": \"คำอธิบายส่วนผสมสั้นๆ\" },
    { \"name\": \"ชื่อเมนูเพียวๆ 4 \", \"calories\": 400, \"desc\": \"คำอธิบายส่วนผสมสั้นๆ\" }
  ]
}
หมายเหตุ: ถ้าผู้ใช้แค่ชวนคุยทั่วไป ไม่ได้ขอเมนู ให้ปล่อย recommended_menus เป็นอาเรย์ว่าง []
";


/// ── ดึง API Key และ Model จากตาราง system_settings ──
$setting_stmt = $conn->query("SELECT api_key, api_model FROM system_settings WHERE id = 1");

if ($setting_stmt && $setting_stmt->num_rows > 0) {
    $setting = $setting_stmt->fetch_assoc();
    // เพิ่ม trim() เพื่อตัดช่องว่างและ Enter ที่อาจติดมาจากฐานข้อมูล
    $apiKey = trim($setting['api_key']);
    $apiModel = trim($setting['api_model']); 
} else {
    // เช็คว่ามีการประกาศ GEMINI_API_KEY ไว้หรือไม่ ป้องกันเว็บพัง
    $apiKey = defined('GEMINI_API_KEY') ? trim(GEMINI_API_KEY) : '';
    $apiModel = 'gemini-2.5-flash';
}

// เอาตัวแปร $apiModel เข้าไปเสียบใน URL
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$apiModel}:generateContent?key=" . $apiKey;

$data = [
    "contents" => [[ "parts" => [[ "text" => $systemPrompt . "\nUser: " . $userMessage ]] ]]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // เพิ่ม Timeout ป้องกันรอนานเกินไป

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err = curl_error($ch); // 💥 เพิ่มบรรทัดนี้เพื่อดักจับ Error ที่แท้จริงของระบบ
curl_close($ch);

$responseData = json_decode($response, true);

if ($httpcode == 200 && isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    $rawText = $responseData['candidates'][0]['content']['parts'][0]['text'];
    
    // ดึงเฉพาะข้อมูล JSON เท่านั้น
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
        
        if (!empty($menusToSave)) {
            $htmlButtons = "<div style='margin-top:15px; display:flex; flex-direction:column; gap:8px;'>";
            $htmlButtons .= "<p style='font-size:0.85rem; color:#4b5563; margin-bottom:5px;'>📌 <b>คลิกปุ่มด้านล่างเพื่อบันทึกเมนูที่คุณต้องการ:</b></p>";
            
            foreach ($menusToSave as $m) {
                $mName = htmlspecialchars($m['name'] ?? '');
                $mCal = (int)($m['calories'] ?? 0);
                $mDesc = htmlspecialchars($m['desc'] ?? '');
                
                $mNameJs = addslashes($m['name'] ?? '');
                $mDescJs = addslashes($m['desc'] ?? '');
                
                $onClick = "event.preventDefault(); var btn=this; btn.innerText='กำลังบันทึก...'; fetch('api_chat.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'save_ai_menu', menu_name:'{$mNameJs}', calories:{$mCal}, description:'{$mDescJs}'})}).then(r=>r.json()).then(d=>{if(d.status==='success'){btn.innerText='✅ บันทึกแล้ว'; btn.style.background='#9ca3af'; btn.disabled=true;}else{btn.innerText='บันทึก'; alert('เกิดข้อผิดพลาด');}}).catch(()=>alert('เกิดข้อผิดพลาดในการเชื่อมต่อ'));";
                
                $onClickAttr = htmlspecialchars($onClick, ENT_QUOTES, 'UTF-8');
                
                $htmlButtons .= "<div style='background:#fff; border:1px solid #d1d5db; padding:10px; border-radius:10px; display:flex; justify-content:space-between; align-items:center; gap:10px;'>";
                $htmlButtons .= "<div style='flex:1;'><div style='font-weight:600; color:#16a34a; font-size:0.9rem;'>{$mName}</div><div style='font-size:0.75rem; color:#6b7280;'>🔥 {$mCal} kcal - {$mDesc}</div></div>";
                $htmlButtons .= "<button onclick=\"{$onClickAttr}\" style='background:#22c55e; color:#fff; border:none; padding:6px 12px; border-radius:8px; font-size:0.8rem; font-family:Kanit,sans-serif; cursor:pointer; transition:0.2s; white-space:nowrap;'>บันทึก</button>";
                $htmlButtons .= "</div>";
            }
            $htmlButtons .= "</div>";
            
            $parsedData['chat_response'] .= $htmlButtons;
        }

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
    // 💥 ระบบจะฟ้องข้อความ Error เชิงลึกให้เราเห็นชัดๆ ทันที
    $error_details = $curl_err ? $curl_err : (isset($responseData['error']['message']) ? $responseData['error']['message'] : 'ไม่ทราบสาเหตุ');
    $error_msg = "เซิร์ฟเวอร์ขัดข้อง (HTTP {$httpcode}) | รายละเอียด: {$error_details}";
    
    echo json_encode([
        'chat_response' => 'ขออภัยค่ะ มีข้อผิดพลาดในการเชื่อมต่อ: ' . $error_msg, 
        'recommended_menus' => []
    ]);
}
?>