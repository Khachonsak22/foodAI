<?php
session_start();
header('Content-Type: application/json');

include '../config/connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['recipe_id'])) {
    echo json_encode(['success' => false, 'message' => 'Recipe ID required']);
    exit();
}

$recipe_id = (int)$data['recipe_id'];

// Check if already favorited
$check_sql = "SELECT id FROM user_interactions 
              WHERE user_id = ? AND recipe_id = ? AND interaction_type = 'favorite'";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $user_id, $recipe_id);
$check_stmt->execute();
$existing = $check_stmt->get_result()->fetch_assoc();

if ($existing) {
    // Remove favorite
    $delete_sql = "DELETE FROM user_interactions WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $existing['id']);
    
    if ($delete_stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'action' => 'removed',
            'message' => 'ลบออกจากรายการโปรดแล้ว'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    // Add favorite
    $insert_sql = "INSERT INTO user_interactions (user_id, recipe_id, interaction_type, created_at) 
                   VALUES (?, ?, 'favorite', NOW())";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("ii", $user_id, $recipe_id);
    
    if ($insert_stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'action' => 'added',
            'message' => 'เพิ่มในรายการโปรดแล้ว'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

$conn->close();
?>