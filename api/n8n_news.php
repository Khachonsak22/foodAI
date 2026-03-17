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
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

// 4. รับค่าต่างๆ (เพิ่มการรับค่า image_url และ image_prompt ที่หายไป)
$secret       = $data['secret'] ?? '';
$title        = $data['title'] ?? '';
$content      = $data['content'] ?? '';
$image_url_in = $data['image_url'] ?? ''; 
$image_prompt = $data['image_prompt'] ?? ''; 

// 5. ตรวจสอบ Secret Token
if ($secret !== N8N_SECRET) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid secret token']);
    exit;
}

// 6. ทำความสะอาดข้อมูล (Sanitize) และจัดการรูปภาพ
$title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

// ✅ เช็คว่า n8n ส่งรูปจริงมาไหม ถ้ามีให้ใช้รูปจริงเลย!
if (!empty($image_url_in)) {
    // บังคับเปลี่ยน http:// เป็น https:// อัตโนมัติ เพื่อแก้ปัญหา Mixed Content (เบราว์เซอร์บล็อกรูป)
    $image_url = str_replace('http://', 'https://', $image_url_in);
} else {
    // ถ้าไม่มีรูป ค่อยให้ AI (Pollinations) วาดให้
    if (!empty($image_prompt)) {
        $prompt_text = $image_prompt;
    } else {
        $prompt_text = "healthy food menu for: " . $data['title']; 
    }
    
    // ล้างตัวอักษรขยะและการเคาะบรรทัดทิ้ง
    $clean_prompt = trim(preg_replace('/\s+/', ' ', $prompt_text));
    $image_url = "https://image.pollinations.ai/prompt/" . urlencode($clean_prompt) . "?width=800&height=500&nologo=true";
}

// 7. ลบเฉพาะข่าวที่เก่ากว่า 7 วันทิ้ง
$conn->query("DELETE FROM news WHERE created_at < (NOW() - INTERVAL 7 DAY)");

// 8. บันทึกลงตาราง news
$insert_sql = "INSERT INTO news (title, content, image_url, created_at) VALUES (?, ?, ?, NOW())";
$stmt = $conn->prepare($insert_sql);
$stmt->bind_param("sss", $title, $content, $image_url);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'News saved successfully']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>