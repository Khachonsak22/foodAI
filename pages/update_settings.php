<?php
session_start();
include '../config/connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// ═══════════════════════════════════════════════════════════════
// ACTION 1: Upload Avatar
// ═══════════════════════════════════════════════════════════════
if ($action === 'upload_avatar') {
    
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['avatar']['name'];
        $tmp_path = $_FILES['avatar']['tmp_name'];
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Validate extension
        if (!in_array($file_ext, $allowed)) {
            $_SESSION['settings_error'] = 'รองรับเฉพาะไฟล์ JPG, PNG, GIF';
            header("Location: settings.php");
            exit();
        }
        
        // Validate size (2MB)
        if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
            $_SESSION['settings_error'] = 'ไฟล์ใหญ่เกิน 2MB';
            header("Location: settings.php");
            exit();
        }
        
        // Create upload directory if not exists
        $upload_dir = '../public/uploads/avatars/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_ext;
        $dest_path = $upload_dir . $new_filename;
        
        // Delete old avatar
        $old_stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
        $old_stmt->bind_param("i", $user_id);
        $old_stmt->execute();
        $old_data = $old_stmt->get_result()->fetch_assoc();
        
        if ($old_data && $old_data['profile_image']) {
            $old_file = $upload_dir . $old_data['profile_image'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
        
        // Move uploaded file
        if (move_uploaded_file($tmp_path, $dest_path)) {
            
            // Update database
            $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
            $stmt->bind_param("si", $new_filename, $user_id);
            
            if ($stmt->execute()) {
                // ✅ Set flag for success popup
                $_SESSION['avatar_uploaded'] = true;
                $_SESSION['settings_success'] = 'อัปเดตรูปโปรไฟล์เรียบร้อยแล้ว';
            } else {
                $_SESSION['settings_error'] = 'เกิดข้อผิดพลาดในการบันทึก';
            }
            
        } else {
            $_SESSION['settings_error'] = 'เกิดข้อผิดพลาดในการอัปโหลด';
        }
        
    } else {
        $_SESSION['settings_error'] = 'กรุณาเลือกไฟล์รูปภาพ';
    }
    
    header("Location: settings.php");
    exit();
}

// ═══════════════════════════════════════════════════════════════
// ACTION 2: Delete Avatar
// ═══════════════════════════════════════════════════════════════
if ($action === 'delete_avatar') {
    header('Content-Type: application/json');
    
    // Get current avatar
    $stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    
    if ($data && $data['profile_image']) {
        $avatar_path = '../public/uploads/avatars/' . $data['profile_image'];
        
        // Delete file from disk
        if (file_exists($avatar_path)) {
            unlink($avatar_path);
        }
        
        // Update database
        $update_stmt = $conn->prepare("UPDATE users SET profile_image = NULL WHERE id = ?");
        $update_stmt->bind_param("i", $user_id);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'ลบรูปโปรไฟล์สำเร็จ']);
        } else {
            echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปเดตฐานข้อมูล']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบรูปโปรไฟล์']);
    }
    exit();
}

// ═══════════════════════════════════════════════════════════════
// ACTION 3: Update Profile (username + birth_date)
// ═══════════════════════════════════════════════════════════════
if ($action === 'update_profile') {
    
    $username = trim($_POST['username'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    
    // Validate username
    if (empty($username)) {
        $_SESSION['settings_error'] = 'กรุณากรอกชื่อผู้ใช้';
        header("Location: settings.php");
        exit();
    }
    
    if (strlen($username) < 3) {
        $_SESSION['settings_error'] = 'ชื่อผู้ใช้ต้องมีอย่างน้อย 3 ตัวอักษร';
        header("Location: settings.php");
        exit();
    }
    
    // Check if username already exists (for other users)
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check_stmt->bind_param("si", $username, $user_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        $_SESSION['settings_error'] = 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว';
        header("Location: settings.php");
        exit();
    }
    
    // Update username and birth_date
    $stmt = $conn->prepare("UPDATE users SET username = ?, birth_date = ? WHERE id = ?");
    $stmt->bind_param("ssi", $username, $birth_date, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['settings_success'] = 'บันทึกข้อมูลเรียบร้อยแล้ว';
    } else {
        $_SESSION['settings_error'] = 'เกิดข้อผิดพลาด: ' . $conn->error;
    }
    
    header("Location: settings.php");
    exit();
}

// ═══════════════════════════════════════════════════════════════
// Invalid action
// ═══════════════════════════════════════════════════════════════
$_SESSION['settings_error'] = 'Invalid action';
header("Location: settings.php");
exit();
?>