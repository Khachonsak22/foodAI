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
    $u_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $u_stmt->bind_param("i", $userId);
    $u_stmt->execute();
    if ($u_row = $u_stmt->get_result()->fetch_assoc()) {
        $userName = $u_row['username'];
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


// System Prompt
$systemPrompt = "คุณคือเชฟและนักโภชนาการอัจฉริยะ ตอบกลับเป็นภาษาไทยที่สุภาพและเป็นกันเอง

**สำคัญมาก:** ในการตอบกลับทุกครั้ง คุณต้องเรียกชื่อผู้ใช้ด้วยความเป็นมิตร เช่น:
- เริ่มต้นด้วย \"ครับคุณ{$userName}\" หรือ \"คุณ{$userName}ครับ\"
- ใช้ตลอดการสนทนา เช่น \"สำหรับคุณ{$userName}...\" \"คุณ{$userName}สามารถ...\"
- สร้างความรู้สึกเป็นกันเองและใส่ใจเฉพาะบุคคล

ข้อมูลผู้ใช้:
- ชื่อ: {$userName}
- เป้าหมายแคลอรี่: {$profile['target']} kcal/วัน
- เป้าหมายสุขภาพ (Goal): {$profile['goal_title']} ({$profile['goal_desc']})
- โรคประจำตัว: {$profile['conditions']}
- อาหารที่แพ้: {$allergy_str}
- เมนูที่เพิ่งทานไปเมื่อเร็วๆ นี้ (ห้ามแนะนำซ้ำเด็ดขาด): {$past_menu_str}

รายชื่อเมนูที่มีในฐานข้อมูลระบบ: {$recipes_str}

คำสั่งสำคัญ:
1. หากผู้ใช้ต้องการให้จัดเมนู ให้คุณจัดเมนูอาหารไทยที่มีอยู่จริง รวมทั้งหมด 4 มื้อ (มื้อเช้า, มื้อกลางวัน, มื้อเย็น, และมื้อว่าง 1 มื้อ)
2. เมนูทั้ง 4 มื้อในครั้งนี้ จะต้องไม่ซ้ำกันเองเลย และต้องไม่ซ้ำกับ 'เมนูที่เพิ่งทานไปเมื่อเร็วๆ นี้' โดยเด็ดขาด เพื่อความหลากหลาย
3. คุณต้องตรวจสอบ 'โรคประจำตัว' และ 'อาหารที่แพ้' ของคุณ{$userName} อย่างเคร่งครัด เมนูที่แนะนำต้องปลอดภัย 100%
4. พยายามเลือกเมนูจาก 'รายชื่อเมนูที่มีในฐานข้อมูลระบบ' ก่อน หากเมนูนั้นปลอดภัยและไม่ซ้ำ
5. หากในฐานข้อมูลไม่มีเมนูที่เหมาะสม หรือเมนูที่มีมันซ้ำกับที่เคยกินไปแล้ว ให้คิดเมนูอาหารไทยขึ้นมาใหม่ให้ครบ 4 มื้อ
6. **สำคัญมาก:** สำหรับแต่ละเมนู ต้องมีข้อมูลครบถ้วน:
   - name: ชื่อเมนูพร้อมระบุมื้อ (เช่น ข้าวผัดกะเพราไก่ - มื้อกลางวัน)
   - calories: แคลอรี่ที่แม่นยำ (เป็นตัวเลข)
   - ingredients: วัตถุดิบพร้อมปริมาณ (แยกบรรทัดด้วย \\n)
   - instructions: วิธีทำทีละขั้นตอน (แยกเป็นข้อๆ ด้วย \\n)
   - desc: คำอธิบายสั้นๆ และคำแนะนำด้านโภชนาการ
7. ให้ตอบกลับในรูปแบบ **JSON เท่านั้น** โดยไม่มี Markdown (```json) ครอบ
8. ในช่อง \"name\" ของ recommended_menus ให้ใส่แค่ชื่อเมนูอาหารเพียวๆ ห้ามมีคำว่า มื้อเช้า มื้อเย็น นำหน้าหรือต่อท้ายเด็ดขาด

รูปแบบ JSON ที่ต้องการ:
{
  \"chat_response\": \"ครับคุณ{$userName}! ข้อความอธิบายว่าทำไมถึงเลือกเมนูเหล่านี้ ปลอดภัยต่อโรคประจำตัวหรืออาหารที่แพ้อย่างไร\",
  \"recommended_menus\": [
    {
      \"name\": \"ข้าวต้มไก่ใส่ขิง\",
      \"calories\": 380,
      \"ingredients\": \"- ข้าวหอมมะลิ 80 กรัม\\n- อกไก่สับ 80 กรัม\\n- ขิงหั่นฝอย 20 กรัม\\n- ผักชี 10 กรัม\\n- กระเทียมเจียว 1 ช้อนโต๊ะ\\n- น้ำซุป 3 ถ้วย\\n- ซีอิ๊วขาว 1 ช้อนชา\",
      \"instructions\": \"1. ต้มน้ำให้เดือด ใส่ข้าว\\n2. ต้มจนข้าวเริ่มสุก เคี่ยวไฟอ่อน\\n3. ใส่อกไก่สับ คนเบาๆ\\n4. เติมขิงหั่นฝอย\\n5. ปรุงรสด้วยซีอิ๊วขาว\\n6. ตักใส่ชาม โรยผักชีและกระเทียมเจียว\\n7. เสิร์ฟร้อนๆ\",
      \"desc\": \"เหมาะกับผู้ป่วยเบาหวาน ย่อยง่าย ให้พลังงานสม่ำเสมอ\"
    }
  ]
}
หมายเหตุ: 
- ถ้าผู้ใช้แค่ชวนคุยทั่วไป ไม่ได้ขอเมนู ให้ปล่อย recommended_menus เป็นอาเรย์ว่าง []
- เมนูทุกเมนูต้องเป็นอาหารไทยที่มีจริง ทำได้จริง และปลอดภัย 100%
- ต้องมี ingredients และ instructions ครบทุกเมนู โดยใช้ \\n แยกบรรทัด
- อย่าลืมเรียกชื่อ คุณ{$userName} ในการตอบทุกครั้ง
";


/// ── ดึง API Key และ Model จากตาราง system_settings (แก้ไขเป็นรูปแบบ mysqli ให้ถูกต้อง) ──
$setting_stmt = $conn->query("SELECT api_key, api_model FROM system_settings WHERE id = 1");

if ($setting_stmt && $setting_stmt->num_rows > 0) {
    $setting = $setting_stmt->fetch_assoc();
    $apiKey = $setting['api_key'];
    $apiModel = $setting['api_model']; 
} else {
    // กรณีหาข้อมูลในฐานข้อมูลไม่เจอ (กันเว็บพัง)
    $apiKey = GEMINI_API_KEY;
    $apiModel = 'gemini-2.5-flash';
}

// เอาตัวแปร $apiModel เข้าไปเสียบใน URL แทนการพิมพ์ชื่อโมเดลตรงๆ
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$apiModel}:generateContent?key=" . $apiKey;

// 4. จัดรูปแบบข้อมูล (ล็อกคอเป็น JSON เพื่อความเสถียร)
$data = [
    "contents" => [[ "parts" => [[ "text" => $systemPrompt . "\nUser: " . $userMessage ]] ]],
    "generationConfig" => [
        "responseMimeType" => "application/json"
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
// รับ HTTP Code เพื่อเช็คสถานะการเชื่อมต่อ
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$responseData = json_decode($response, true);

// เพิ่มการเช็ค Http Code เพื่อป้องกัน API เอ๋อแล้วเงียบหาย
if ($httpcode == 200 && isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    $rawText = $responseData['candidates'][0]['content']['parts'][0]['text'];
    
    // Clean JSON string (ดักจับ JSON ให้แม่นยำขึ้น ป้องกัน AI พิมพ์เกิน)
    $cleanJson = str_replace(['```json', '```'], '', $rawText);
    $cleanJson = trim($cleanJson);
    
    // ถ้า json_decode ไม่ผ่าน ให้ลองใช้ Regex ดึงเฉพาะปีกกา { ... } ออกมา
    $parsedData = json_decode($cleanJson, true);
    if (!$parsedData) {
        preg_match('/\{.*\}/s', $cleanJson, $matches);
        if (!empty($matches)) {
             $parsedData = json_decode($matches[0], true);
        }
    }

    if (!$parsedData) {
        $parsedData = [
            "chat_response" => $rawText,
            "recommended_menus" => []
        ];
    }

    if ($userId > 0) {
        $menusToSave = $parsedData['recommended_menus'] ?? [];
        
        if (!empty($menusToSave)) {
            $ins_menu_stmt = $conn->prepare("INSERT INTO ai_saved_menus (user_id, menu_name, calories, description) VALUES (?, ?, ?, ?)");
            
            // 🌟 แก้ไขคำสั่งเพิ่มคอลัมน์ ingredients แยกออกมาอย่างชัดเจน
            $check_recipe = $conn->prepare("SELECT id FROM recipes WHERE title = ?");
            $ins_recipe = $conn->prepare("INSERT INTO recipes (title, description, ingredients, instructions, calories, image) VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($menusToSave as $m) {
                $mName = $m['name'] ?? 'เมนูอาหาร';
                $mCal = (int)($m['calories'] ?? 0);
                $mDesc = $m['desc'] ?? '';
                $mIng = $m['ingredients'] ?? '';
                $mInst = $m['instructions'] ?? '';
                
                // บันทึกลงตารางประวัติการแนะนำของ AI
                if ($ins_menu_stmt) {
                    $ins_menu_stmt->bind_param("isis", $userId, $mName, $mCal, $mDesc);
                    $ins_menu_stmt->execute();
                }
                
                // บันทึกลงคลังอาหารหลัก
                if ($check_recipe) {
                    $check_recipe->bind_param("s", $mName);
                    $check_recipe->execute();
                    $res = $check_recipe->get_result();
                    
                    if ($res && $res->num_rows === 0) {
                        // สุ่มรูปภาพให้เมนูใหม่
                        $randomImg = "https://loremflickr.com/800/500/healthy,food?lock=" . rand(1, 999999);
                        
                        if ($ins_recipe) {
                            // 🌟 บันทึกแยกช่องกัน 100% ($mDesc ลงช่อง description, $mIng ลงช่อง ingredients)
                            // ssssis = String 4 ตัว, Int 1 ตัว, String 1 ตัว
                            $ins_recipe->bind_param("ssssis", $mName, $mDesc, $mIng, $mInst, $mCal, $randomImg);
                            $ins_recipe->execute();
                        }
                    }
                }
            }
        }
        
        // บันทึกประวัติแชทลง chat_logs
        $finalMessageToSave = $parsedData['chat_response'];
        if (!empty($menusToSave)) {
            $finalMessageToSave .= '|||MENUS|||' . json_encode($menusToSave, JSON_UNESCAPED_UNICODE);
        }
        
        $log_stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'ai', ?)");
        if ($log_stmt) {
            $log_stmt->bind_param("is", $userId, $finalMessageToSave);
            $log_stmt->execute();
        }
    }

    echo json_encode($parsedData);
} else {
    // ส่ง Error ให้ฝั่งหน้าเว็บทราบชัดเจน
    $error_msg = isset($responseData['error']['message']) ? $responseData['error']['message'] : 'ระบบ AI ขัดข้องชั่วคราว (HTTP '.$httpcode.')';
    echo json_encode([
        'chat_response' => 'ขออภัยค่ะ มีข้อผิดพลาดจากเซิร์ฟเวอร์ AI: ' . $error_msg, 
        'recommended_menus' => []
    ]);
}
?>