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
$userMessage = $input['message'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

if (!$userMessage) { echo json_encode(['reply' => '']); exit; }

// บันทึก Log ฝั่ง User
if ($userId > 0) {
    $stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'user', ?)");
    $stmt->bind_param("is", $userId, $userMessage);
    $stmt->execute();
}

// ดึงข้อมูลสุขภาพ (เป้าหมาย, โรคประจำตัว)
$profile = ['target' => 2000, 'conditions' => 'ไม่มี'];
if ($userId > 0) {
    $stmt = $conn->prepare("SELECT daily_calorie_target, health_conditions FROM health_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $profile['target'] = $row['daily_calorie_target'];
        $profile['conditions'] = !empty($row['health_conditions']) ? $row['health_conditions'] : 'ไม่มี';
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
$systemPrompt = "คุณคือเชฟและนักโภชนาการอัจฉริยะ ตอบกลับเป็นภาษาไทยที่สุภาพ
ข้อมูลผู้ใช้:
- เป้าหมายแคลอรี่: {$profile['target']} kcal/วัน
- โรคประจำตัว: {$profile['conditions']}
- อาหารที่แพ้: {$allergy_str}
- เมนูที่เพิ่งทานไปเมื่อเร็วๆ นี้ (ห้ามแนะนำซ้ำเด็ดขาด): {$past_menu_str}

รายชื่อเมนูที่มีในฐานข้อมูลระบบ: {$recipes_str}

คำสั่งสำคัญ:
1. หากผู้ใช้ต้องการให้จัดเมนู ให้คุณจัดเมนูอาหารไทยที่มีอยู่จริง รวมทั้งหมด 4 มื้อ (มื้อเช้า, มื้อกลางวัน, มื้อเย็น, และมื้อว่าง 1 มื้อ)
2. เมนูทั้ง 4 มื้อในครั้งนี้ จะต้องไม่ซ้ำกันเองเลย และต้องไม่ซ้ำกับ 'เมนูที่เพิ่งทานไปเมื่อเร็วๆ นี้' โดยเด็ดขาด เพื่อความหลากหลาย
3. คุณต้องตรวจสอบ 'โรคประจำตัว' และ 'อาหารที่แพ้' ของผู้ใช้อย่างเคร่งครัด เมนูที่แนะนำต้องปลอดภัย 100%
4. พยายามเลือกเมนูจาก 'รายชื่อเมนูที่มีในฐานข้อมูลระบบ' ก่อน หากเมนูนั้นปลอดภัยและไม่ซ้ำ
5. หากในฐานข้อมูลไม่มีเมนูที่เหมาะสม หรือเมนูที่มีมันซ้ำกับที่เคยกินไปแล้ว ให้คิดเมนูอาหารไทยขึ้นมาใหม่ให้ครบ 4 มื้อ
6. ให้ตอบกลับในรูปแบบ **JSON เท่านั้น** โดยไม่มี Markdown (```json) ครอบ

รูปแบบ JSON ที่ต้องการ:
{
  \"chat_response\": \"ข้อความอธิบายว่าทำไมถึงเลือกเมนูเหล่านี้ ปลอดภัยต่อโรคประจำตัวหรืออาหารที่แพ้อย่างไร (สามารถใช้ Markdown ตกแต่งข้อความได้)\",
  \"recommended_menus\": [
    { \"name\": \"ชื่อเมนู 1 \", \"calories\": 400, \"desc\": \"คำอธิบายส่วนผสมสั้นๆ\" },
    { \"name\": \"ชื่อเมนู 2 \", \"Menu_Desc\": 500, \"desc\": \"คำอธิบายส่วนผสมสั้นๆ\" },
    { \"name\": \"ชื่อเมนู 3 \", \"calories\": 150, \"desc\": \"คำอธิบายส่วนผสมสั้นๆ\" },
    { \"name\": \"ชื่อเมนู 4 \", \"calories\": 400, \"desc\": \"คำอธิบายส่วนผสมสั้นๆ\" }
  ]
}
หมายเหตุ: ถ้าผู้ใช้แค่ชวนคุยทั่วไป ไม่ได้ขอเมนู ให้ปล่อย recommended_menus เป็นอาเรย์ว่าง []
";

// ใส่ API Key ของเจ้าเด้ออ้าย
// ใส่ API Key ของคุณ
$apiKey = GEMINI_API_KEY;

// ✅ แก้ไข 1: เปลี่ยนเป็นโมเดล gemini-2.5-flash ที่ถูกต้องและเสถียร
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite-preview:generateContent?key=" . $apiKey;

// ✅ แก้ไข 2: เพิ่มการล็อกคอ AI ให้ส่งกลับมาเป็น JSON แบบ 100%
$data = [
    "contents" => [[ "parts" => [[ "text" => $systemPrompt . "\nUser: " . $userMessage ]] ]],
    "generationConfig" => [
        "responseMimeType" => "application/json"
    ]
];

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
// รับ HTTP Code เพื่อเช็คสถานะการเชื่อมต่อ
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$responseData = json_decode($response, true);

// เพิ่มการเช็ค Http Code เพื่อป้องกัน API เอ๋อแล้วเงียบหาย
if ($httpcode == 200 && isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    $rawText = $responseData['candidates'][0]['content']['parts'][0]['text'];
    
    // Clean JSON string
    $cleanJson = str_replace(['```json', '```'], '', $rawText);
    $parsedData = json_decode($cleanJson, true);

    if (!$parsedData) {
        $parsedData = [
            "chat_response" => $rawText,
            "recommended_menus" => []
        ];
    }

    if ($userId > 0) {
        $menusToSave = $parsedData['recommended_menus'] ?? [];
        
        // 1. บันทึกเมนูลงตาราง ai_saved_menus อัตโนมัติ (เพื่อให้ไปโผล่ในหน้า meal_log ทันที)
        if (!empty($menusToSave)) {
            $ins_menu_stmt = $conn->prepare("INSERT INTO ai_saved_menus (user_id, menu_name, calories, description) VALUES (?, ?, ?, ?)");
            foreach ($menusToSave as $m) {
                $mName = $m['name'] ?? 'เมนูอาหาร';
                $mCal = (int)($m['calories'] ?? 0);
                $mDesc = $m['desc'] ?? '';
                $ins_menu_stmt->bind_param("isis", $userId, $mName, $mCal, $mDesc);
                $ins_menu_stmt->execute();
            }
        }
        
        // 2. บันทึกประวัติแชทลง chat_logs โดยใช้ |||MENUS||| เป็นตัวแบ่ง เพื่อให้หน้าเว็บดึงไปสร้างปุ่มได้
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
    // ส่ง Error ให้ฝั่งหน้าเว็บทราบชัดเจน
    $error_msg = isset($responseData['error']['message']) ? $responseData['error']['message'] : 'ระบบ AI ขัดข้องชั่วคราว (HTTP '.$httpcode.')';
    echo json_encode([
        'chat_response' => 'ขออภัยค่ะ มีข้อผิดพลาดจากเซิร์ฟเวอร์ AI: ' . $error_msg, 
        'recommended_menus' => []
    ]);
}
?>