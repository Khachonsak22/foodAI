<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

include '../config/connect.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON body']);
    exit;
}

$incoming_secret = $data['secret'] ?? '';
if ($incoming_secret !== N8N_SECRET) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Secret token mismatch']);
    exit;
}

$title   = trim($data['title'] ?? '');
$content = trim($data['content'] ?? '');

if (empty($title) || empty($content)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Title and content are required']);
    exit;
}

$title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

// ✅ แก้ไข: รับรูปภาพจาก N8N แทนการสุ่มเอง
$image_url = trim($data['image_url'] ?? '');

// ถ้าไม่มีรูปจาก N8N ให้ใช้ fallback
if (empty($image_url)) {
    $image_url = "https://loremflickr.com/800/500/healthy,food?random=" . rand(1, 999999);
}

// ลบข่าวเก่า (เก็บไว้ 7 วัน)
$conn->query("DELETE FROM news WHERE created_at < (NOW() - INTERVAL 7 DAY)");

// บันทึกข้อมูล
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