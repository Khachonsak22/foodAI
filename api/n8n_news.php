<?php
/**
 * n8n_news.php  —  Webhook receiver สำหรับรับข่าวสารอาหารประจำวันจาก n8n
 * * วิธีตั้งค่าใน n8n:
 * 1. สร้าง Workflow ใน n8n
 * 2. เพิ่ม Trigger: Schedule (ทุกวัน 07:00 น.)
 * 3. เชื่อมต่อ node: RSS Feed / HTTP Request (ดึงข่าวอาหาร)
 * 4. เพิ่ม node: HTTP Request (POST) → URL: https://yourdomain.com/api/n8n_news.php
 * 5. Body (JSON):
 * {
 * "secret": "ใส่รหัสผ่านลับของคุณให้ตรงกับในไฟล์ connect.php",
 * "title": "{{ $json.title }}",
 * "content": "{{ $json.content }}",
 * "image_url": "{{ $json.image_url }}"
 * }
 */

header('Content-Type: application/json; charset=utf-8');

// 1. อนุญาตให้ส่งข้อมูลมาแบบ POST เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// 2. เรียกใช้งานไฟล์เชื่อมต่อฐานข้อมูล (ซึ่งมี N8N_SECRET_TOKEN ฝังอยู่แล้ว)
include '../config/connect.php';

// 3. รับ JSON body จาก n8n
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON body']);
    exit;
}

// 4. ตรวจสอบความปลอดภัย (Secret Token)
$incoming_secret = $data['secret'] ?? '';

// เช็คว่ารหัสที่ส่งมา ตรงกับที่เราตั้งไว้ใน connect.php หรือไม่
if ($incoming_secret !== N8N_SECRET) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Secret token mismatch']);
    exit;
}

// 5. ตรวจสอบความครบถ้วนของข้อมูลข่าว
$title        = trim($data['title'] ?? '');
$content      = trim($data['content'] ?? '');
$image_prompt = trim($data['image_prompt'] ?? ''); // รับคำค้นหาภาษาอังกฤษจาก n8n

if (empty($title) || empty($content)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Title and content are required']);
    exit;
}

// 6. ทำความสะอาดข้อมูล (Sanitize) และสร้างลิงก์รูปภาพ AI
$title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

// [ไม้ตายกันเหนียว] ถ้าระบบส่งคำสั่งวาดรูปมาให้ใช้ตามนั้น 
// แต่ถ้าส่งมาเป็นค่าว่าง ให้เอา "หัวข้อข่าว" ไปให้ AI วาดแทน!
if (!empty($image_prompt)) {
    $prompt_text = $image_prompt;
} else {
    // ใส่คำว่า healthy food นำหน้าหัวข้อข่าว เพื่อคุมโทนรูปให้อยู่ในหมวดอาหาร
    $prompt_text = "healthy food menu for: " . $data['title']; 
}

// ล้างตัวอักษรขยะและการเคาะบรรทัดทิ้ง
$clean_prompt = trim(preg_replace('/\s+/', ' ', $prompt_text));

// สร้างลิงก์ Pollinations.ai แบบสมบูรณ์
$image_url = "https://image.pollinations.ai/prompt/" . urlencode($clean_prompt) . "?width=800&height=500&nologo=true";

// 7. ลบเฉพาะข่าวที่เก่ากว่า 7 วันทิ้ง
$conn->query("DELETE FROM news WHERE created_at < (NOW() - INTERVAL 7 DAY)");

// 8. บันทึกลงตาราง news
$insert_sql = "INSERT INTO news (title, content, image_url, created_at) VALUES (?, ?, ?, NOW())";
$stmt = $conn->prepare($insert_sql);
$stmt->bind_param("sss", $title, $content, $image_url);

if ($stmt->execute()) {
    $new_id = $conn->insert_id;
    http_response_code(201);
    echo json_encode([
        'status'  => 'success',
        'message' => 'News saved successfully',
        'id'      => $new_id,
        'saved_at' => date('Y-m-d H:i:s'),
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>