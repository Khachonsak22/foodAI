<?php
session_start();
include '../config/connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: settings.php");
    exit();
}

$action = $_POST['action'] ?? '';

/* ══════════════════════════════════════════════════════════════════
   ACTION: UPDATE PROFILE (name, birth_date)
   ══════════════════════════════════════════════════════════════════ */
if ($action === 'update_profile') {
    
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    
    // ** จุดที่แก้ไข: หากไม่ได้ระบุวันเกิด ให้เปลี่ยนเป็นค่า null เพื่อป้องกัน Error จาก Database **
    if ($birth_date === '') {
        $birth_date = null;
    }
    
    // Validation
    if (empty($first_name) || empty($last_name)) {
        $_SESSION['settings_error'] = 'กรุณากรอกชื่อและนามสกุล';
        header("Location: settings.php");
        exit();
    }
    
    // Update database
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, birth_date = ? WHERE id = ?");
    $stmt->bind_param("sssi", $first_name, $last_name, $birth_date, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['settings_success'] = 'บันทึกข้อมูลเรียบร้อยแล้ว';
    } else {
        $_SESSION['settings_error'] = 'เกิดข้อผิดพลาดในการบันทึก: ' . $conn->error;
    }
    
    header("Location: settings.php");
    exit();
}

/* ══════════════════════════════════════════════════════════════════
   ACTION: UPLOAD AVATAR
   ══════════════════════════════════════════════════════════════════ */
if ($action === 'upload_avatar') {
    
    // ** จุดที่แก้ไข: ดักจับ Error การอัปโหลดให้ชัดเจนขึ้นว่าไฟล์ใหญ่เกินไปหรือไม่ **
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $upload_error = $_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($upload_error === UPLOAD_ERR_INI_SIZE || $upload_error === UPLOAD_ERR_FORM_SIZE) {
            $_SESSION['settings_error'] = 'ไฟล์รูปภาพมีขนาดใหญ่เกินกว่าที่ระบบรองรับ (แนะนำไม่เกิน 2MB)';
        } else {
            $_SESSION['settings_error'] = 'ไม่สามารถอ่านไฟล์รูปภาพได้ หรือยังไม่ได้เลือกไฟล์';
        }
        header("Location: settings.php");
        exit();
    }
    
    $file = $_FILES['avatar'];
    $file_name = $file['name'];
    $file_size = $file['size'];
    $file_tmp  = $file['tmp_name'];
    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Validate file type
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($file_ext, $allowed_ext)) {
        $_SESSION['settings_error'] = 'รองรับเฉพาะไฟล์ JPG, PNG, GIF, WEBP';
        header("Location: settings.php");
        exit();
    }
    
    // Validate file size (2MB max)
    if ($file_size > 2 * 1024 * 1024) {
        $_SESSION['settings_error'] = 'ขนาดไฟล์ต้องไม่เกิน 2MB';
        header("Location: settings.php");
        exit();
    }
    
    // Create upload directory if not exists
    $upload_dir = '../public/uploads/avatars/';
    if (!file_exists($upload_dir)) {
        // เพิ่ม @ เพื่อป้องกัน Warning หากโฟลเดอร์มีปัญหา permission
        @mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_ext;
    $upload_path  = $upload_dir . $new_filename;
    
    // Delete old avatar if exists
    $old_stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
    $old_stmt->bind_param("i", $user_id);
    $old_stmt->execute();
    $old_data = $old_stmt->get_result()->fetch_assoc();
    if (!empty($old_data['profile_image'])) {
        $old_file = $upload_dir . $old_data['profile_image'];
        if (file_exists($old_file)) {
            @unlink($old_file);
        }
    }
    
    // Upload new file
    if (move_uploaded_file($file_tmp, $upload_path)) {
        // Update database
        $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
        $stmt->bind_param("si", $new_filename, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['settings_success'] = 'อัปโหลดรูปภาพเรียบร้อยแล้ว';
        } else {
            $_SESSION['settings_error'] = 'เกิดข้อผิดพลาดในการบันทึกรูปภาพลงฐานข้อมูล';
        }
    } else {
        $_SESSION['settings_error'] = 'อัปโหลดไม่สำเร็จ (ตรวจสอบสิทธิ์การเขียนโฟลเดอร์ uploads/avatars)';
    }
    
    header("Location: settings.php");
    exit();
}

/* ══════════════════════════════════════════════════════════════════
   DEFAULT: Invalid action
   ══════════════════════════════════════════════════════════════════ */
$_SESSION['settings_error'] = 'คำสั่งไม่ถูกต้อง';
header("Location: settings.php");
exit();
?>