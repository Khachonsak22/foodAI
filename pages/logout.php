<?php
session_start();

// 1. ล้างค่าตัวแปร Session ทั้งหมดในหน่วยความจำปัจจุบัน
$_SESSION = array();

// 2. ลบ Cookie ของ Session ออกจาก Browser (สำคัญ!)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. ทำลาย Session บน Server
session_destroy();

// 4. ส่งกลับไปหน้า Login
header("location: ../index.php");
exit();
?>