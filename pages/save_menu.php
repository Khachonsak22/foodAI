<?php
session_start();
include '../config/connect.php';

header('Content-Type: application/json');

// รับข้อมูล JSON
$data = json_decode(file_get_contents('php://input'), true);

if (isset($_SESSION['user_id']) && isset($data['name'])) {
    $user_id = $_SESSION['user_id'];
    $menu_name = $data['name'];
    $calories = intval($data['calories']);
    $desc = $data['desc'];

    $stmt = $conn->prepare("INSERT INTO ai_saved_menus (user_id, menu_name, calories, description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isis", $user_id, $menu_name, $calories, $desc);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}
?>