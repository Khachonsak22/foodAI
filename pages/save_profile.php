<?php
session_start();
include '../config/connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 1. รับค่าจากฟอร์ม
$gender = $_POST['gender'] ?? 'male';
$age = intval($_POST['age'] ?? 0);
$weight = floatval($_POST['weight'] ?? 0);
$height = floatval($_POST['height'] ?? 0);
$activity = $_POST['activity_level'] ?? 'sedentary';
$goal = $_POST['goal'] ?? 'maintain'; // รับค่าเป้าหมาย

// รับค่ารูปแบบการกิน
$diet_input = isset($_POST['diet']) ? $_POST['diet'] : []; 
$diet = empty($diet_input) ? 'normal' : (is_array($diet_input) ? implode(", ", $diet_input) : $diet_input);

// 2. จัดการข้อมูลโรคประจำตัว
$diseases_arr = isset($_POST['diseases']) ? $_POST['diseases'] : [];
if (!empty($_POST['other_disease_text'])) {
    $other_text = trim($_POST['other_disease_text']);
    $other_text = htmlspecialchars($other_text, ENT_QUOTES, 'UTF-8');
    array_push($diseases_arr, $other_text);
}
$diseases_str = empty($diseases_arr) ? "ไม่มีโรคประจำตัว" : implode(", ", $diseases_arr);

// 3. คำนวณ BMR & TDEE
if ($gender == 'male') {
    $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) + 5;
} else {
    $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) - 161;
}

$activity_multiplier = 1.2;
switch ($activity) {
    case 'light': $activity_multiplier = 1.375; break;
    case 'moderate': $activity_multiplier = 1.55; break;
    case 'active': $activity_multiplier = 1.725; break;
    case 'very_active': $activity_multiplier = 1.9; break;
}
$tdee = $bmr * $activity_multiplier;

// 4.ดึง "ค่าปรับแคลอรี่" (cal_adjust) จากตาราง goals
$cal_adjust = 0;
$goal_stmt = $conn->prepare("SELECT cal_adjust FROM goals WHERE goal_key = ?");
$goal_stmt->bind_param("s", $goal);
$goal_stmt->execute();
$goal_res = $goal_stmt->get_result()->fetch_assoc();
if ($goal_res) {
    $cal_adjust = (int)$goal_res['cal_adjust'];
}

// คำนวณแคลอรี่เป้าหมายสุทธิ
$final_calories = $tdee + $cal_adjust;
$daily_target = round($final_calories);

// เซฟตี้ลิมิต: ป้องกันแคลอรี่ต่ำเกินไปจนเสียสุขภาพ
$min_cal = ($gender == 'male') ? 1500 : 1200;
if ($daily_target < $min_cal) {
    $daily_target = $min_cal;
}

// 5. บันทึกลงฐานข้อมูล
$sql = "INSERT INTO health_profiles 
        (user_id, daily_calorie_target, dietary_type, health_conditions, goal_preference, weight, height, age, gender, activity_level) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        daily_calorie_target = VALUES(daily_calorie_target),
        dietary_type = VALUES(dietary_type),
        health_conditions = VALUES(health_conditions),
        goal_preference = VALUES(goal_preference),
        weight = VALUES(weight),
        height = VALUES(height),
        age = VALUES(age),
        gender = VALUES(gender),
        activity_level = VALUES(activity_level)";

$stmt = $conn->prepare($sql);

$stmt->bind_param("iisssddiss", 
    $user_id,       // i 
    $daily_target,  // i 
    $diet,          // s 
    $diseases_str,  // s 
    $goal,          // s 
    $weight,        // d 
    $height,        // d 
    $age,           // i 
    $gender,        // s 
    $activity       // s 
);

if ($stmt->execute()) {
    header("Location: dashboard.php");
    exit();
} else {
    echo "เกิดข้อผิดพลาด: " . $conn->error;
}
?>