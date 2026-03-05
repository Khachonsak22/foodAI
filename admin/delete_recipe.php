<?php
session_start();
include 'config/connect.php';

if(isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // เริ่ม Transaction เพื่อความปลอดภัย
    mysqli_begin_transaction($conn);
    try {
        // 1. ลบความสัมพันธ์ในตารางลูกก่อน
        mysqli_query($conn, "DELETE FROM recipe_ingredients WHERE recipe_id = $id");
        mysqli_query($conn, "DELETE FROM user_interactions WHERE recipe_id = $id");
        
        // 2. ลบเมนูหลัก
        mysqli_query($conn, "DELETE FROM recipes WHERE id = $id");
        
        mysqli_commit($conn);
        header("Location: manage_recipes.php?msg=deleted");
    } catch (Exception $e) {
        mysqli_rollback($conn);
        header("Location: manage_recipes.php?error=failed");
    }
}
?>