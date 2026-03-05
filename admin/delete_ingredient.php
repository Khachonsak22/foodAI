<?php
session_start();
include 'config/connect.php';

/**
 * 1. ตรวจสอบสิทธิ์และรับค่า ID
 */
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    if ($id > 0) {
        // เริ่ม Transaction เพื่อความปลอดภัยของข้อมูล
        mysqli_begin_transaction($conn);

        try {
            /**
             * 2. ตรวจสอบว่าวัตถุดิบนี้ถูกใช้งานอยู่ในสูตรอาหาร (Recipe) หรือไม่
             * เพื่อป้องกัน Error แบบ Foreign Key Constraint
             */
            $check_sql = "SELECT COUNT(*) as count FROM recipe_ingredients WHERE ingredient_id = $id";
            $check_res = mysqli_query($conn, $check_sql);
            $usage = mysqli_fetch_assoc($check_res);

            if ($usage['count'] > 0) {
                // หากมีการใช้งานอยู่ ห้ามลบ และแจ้งเตือน Admin
                mysqli_rollback($conn);
                header("Location: manage_ingredients.php?error=in_use");
                exit();
            }

            /**
             * 3. ทำการลบข้อมูลจากตาราง ingredients
             */
            $delete_sql = "DELETE FROM ingredients WHERE id = $id";
            if (mysqli_query($conn, $delete_sql)) {
                mysqli_commit($conn);
                header("Location: manage_ingredients.php?msg=deleted");
            } else {
                throw new Exception("ลบข้อมูลไม่สำเร็จ");
            }

        } catch (Exception $e) {
            // หากเกิดข้อผิดพลาดให้ยกเลิกการเปลี่ยนแปลงทั้งหมด
            mysqli_rollback($conn);
            header("Location: manage_ingredients.php?error=failed");
        }
    } else {
        header("Location: manage_ingredients.php");
    }
} else {
    header("Location: manage_ingredients.php");
}
?>