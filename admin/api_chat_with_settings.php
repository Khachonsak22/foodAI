<?php
session_start();
include '../config/connect.php';

header('Content-Type: application/json');

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);
$userMessage = $input['message'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

if (!$userMessage) { echo json_encode(['reply' => '']); exit; }

// ✅ ดึง API Key และ Model จาก Database
$apiKey = "GEMINI_API_KEY"; // Default (ถ้าไม่มีในระบบ)
$modelName = "gemini-2.5-flash"; // Default

if ($userId > 0) {
    $api_stmt = $conn->prepare("SELECT api_key, model_name FROM ai_settings WHERE user_id = ?");
    $api_stmt->bind_param("i", $userId);
    $api_stmt->execute();
    $api_result = $api_stmt->get_result();
    if ($api_row = $api_result->fetch_assoc()) {
        $apiKey = $api_row['api_key'] ?? "";
        $modelName = $api_row['model_name'] ?? "gemini-1.5-flash";
    }
}

// ตรวจสอบว่ามี API Key หรือไม่
if (empty($apiKey)) {
    echo json_encode([
        'chat_response' => '❌ **กรุณาตั้งค่า API Key**\n\nคุณยังไม่ได้ตั้งค่า API Key กรุณาไปที่ [ตั้งค่า AI](/pages/admin_settings.php) เพื่อเพิ่ม API Key ของคุณ\n\n📌 **วิธีรับ API Key ฟรี:**\n1. เข้า [Google AI Studio](https://aistudio.google.com/apikey)\n2. คลิก "Get API Key"\n3. Copy มาวางในหน้าตั้งค่า', 
        'recommended_menus' => []
    ]);
    exit;
}

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

// ดึงรายชื่อเมนูที่มีในระบบ
$existing_recipes = [];
$r_stmt = $conn->query("SELECT title FROM recipes LIMIT 200");
while($r_row = $r_stmt->fetch_assoc()) {
    $existing_recipes[] = $r_row['title'];
}
$recipes_str = empty($existing_recipes) ? 'ไม่มีข้อมูล' : implode(", ", $existing_recipes);

// ดึงประวัติเมนูที่ AI เคยแนะนำ
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
2. เมนูทั้ง 4 มื้อในครั้งนี้ จะต้องไม่ซ้ำกันเองเลย และต้องไม่ซ้ำกับ 'เมนูที่เพิ่งทานไปเมื่อเร็วๆ นี้' โดยเด็ดขาด
3. คุณต้องตรวจสอบ 'โรคประจำตัว' และ 'อาหารที่แพ้' ของผู้ใช้อย่างเคร่งครัด เมนูที่แนะนำต้องปลอดภัย 100%
4. พยายามเลือกเมนูจาก 'รายชื่อเมนูที่มีในฐานข้อมูลระบบ' ก่อน หากเมนูนั้นปลอดภัยและไม่ซ้ำ
5. หากในฐานข้อมูลไม่มีเมนูที่เหมาะสม หรือเมนูที่มีมันซ้ำกับที่เคยกินไปแล้ว ให้คิดเมนูอาหารไทยขึ้นมาใหม่ให้ครบ 4 มื้อ
6. **สำคัญมาก:** สำหรับแต่ละเมนู ต้องมีข้อมูลครบถ้วน:
   - name: ชื่อเมนูพร้อมระบุมื้อ
   - calories: แคลอรี่ที่แม่นยำ
   - ingredients: วัตถุดิบพร้อมปริมาณ (แยกบรรทัดด้วย \\n)
   - instructions: วิธีทำทีละขั้นตอน (แยกเป็นข้อๆ ด้วย \\n)
   - desc: คำอธิบายสั้นๆ และคำแนะนำด้านโภชนาการ
7. ให้ตอบกลับในรูปแบบ **JSON เท่านั้น** โดยไม่มี Markdown (```json) ครอบ

รูปแบบ JSON ที่ต้องการ:
{
  \"chat_response\": \"ข้อความอธิบายว่าทำไมถึงเลือกเมนูเหล่านี้ ปลอดภัยอย่างไร\",
  \"recommended_menus\": [
    {
      \"name\": \"ข้าวต้มไก่ใส่ขิง - มื้อเช้า\",
      \"calories\": 380,
      \"ingredients\": \"- ข้าวหอมมะลิ 80g\\n- อกไก่สับ 80g\\n...\",
      \"instructions\": \"1. ต้มน้ำให้เดือด\\n2. ใส่ข้าว\\n...\",
      \"desc\": \"เหมาะกับผู้ป่วยเบาหวาน ย่อยง่าย\"
    }
  ]
}
หมายเหตุ: ถ้าผู้ใช้แค่ชวนคุยทั่วไป ให้ปล่อย recommended_menus เป็น []
";

// ✅ ใช้ API Key และ Model จาก Database
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

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
        
        // บันทึกประวัติแชท
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
        'chat_response' => 'ขออภัยค่ะ มีข้อผิดพลาด: ' . $error_msg, 
        'recommended_menus' => []
    ]);
}
?>