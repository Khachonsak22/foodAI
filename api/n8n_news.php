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

// 2. เรียกใช้งานไฟล์เชื่อมต่อฐานข้อมูล
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

if ($incoming_secret !== N8N_SECRET) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Secret token mismatch']);
    exit;
}

// 5. ตรวจสอบความครบถ้วนของข้อมูลข่าว
$title        = trim($data['title'] ?? '');
$content      = trim($data['content'] ?? '');
$image_url_in = trim($data['image_url'] ?? ''); 
$image_prompt = trim($data['image_prompt'] ?? '');

if (empty($title) || empty($content)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Title and content are required']);
    exit;
}

// 6. ทำความสะอาดข้อมูล (Sanitize) 
$title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

if (!empty($image_url_in)) {
    // ถ้าระบบส่งรูปลิงก์จริงมา ให้เปลี่ยน http เป็น https กันเบราว์เซอร์บล็อก
    $image_url = str_replace('http://', 'https://', $image_url_in);
} else {
    // 💡 ระบบให้ AI วาดรูป (ฉบับอัปเกรด)
    if (!empty($image_prompt)) {
        // กรองเอาเฉพาะภาษาอังกฤษและช่องว่าง
        $clean_prompt = preg_replace('/[^a-zA-Z0-9\s]/', '', $image_prompt);
        $clean_prompt = trim(preg_replace('/\s+/', ' ', $clean_prompt));
        $clean_prompt = substr($clean_prompt, 0, 80);
        
        if (strlen($clean_prompt) > 0) {
            // 🌟 1. สุ่มตัวเลข 1 ถึง 1 ล้าน เพื่อบังคับให้ AI วาดรูปใหม่ทุกครั้ง (ภาพไม่ซ้ำ)
            $random_seed = rand(1, 999999);
            
            // 🌟 2. เติมคีย์เวิร์ดตากล้อง เพื่อให้ภาพตรงข่าวและสมจริงที่สุด
            $pro_prompt = "high quality realistic food photography of " . $clean_prompt . ", delicious, healthy ingredients, soft lighting";
            
            // นำไปประกอบลิงก์
            $image_url = "https://image.pollinations.ai/prompt/" . rawurlencode($pro_prompt) . "?width=800&height=500&nologo=true&seed=" . $random_seed;
        } else {
            $image_url = "https://loremflickr.com/800/500/healthy,food?random=" . rand(1, 100000);
        }
    } else {
        $image_url = "https://loremflickr.com/800/500/healthy,food?random=" . rand(1, 100000);
    }
}

// 7. ลบข่าวเก่ากว่า 7 วันทิ้ง
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
        'image'   => $image_url
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>