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
$goal = $_POST['goal'] ?? 'maintain'; // รับค่าเป้าหมายมาแล้ว

// รับค่ารูปแบบการกิน (Array -> String)
$diet_input = isset($_POST['diet']) ? $_POST['diet'] : []; 
$diet = empty($diet_input) ? 'normal' : (is_array($diet_input) ? implode(", ", $diet_input) : $diet_input);

// 2. จัดการข้อมูลโรคประจำตัว (Array -> String)
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

// 4. ปรับแคลอรี่ตามเป้าหมาย
$final_calories = $tdee;
switch ($goal) {
    case 'lose_slow': $final_calories -= 250; break;
    case 'lose_normal': 
    case 'lose': $final_calories -= 500; break;
    case 'lose_fast': $final_calories -= 750; break;
    case 'maintain': $final_calories = $tdee; break;
    case 'gain_lean': $final_calories += 250; break;
    case 'gain_bulk': 
    case 'gain': $final_calories += 500; break;
    case 'athlete': $final_calories += 400; break;
}

$daily_target = round($final_calories);
if ($daily_target < 1200) $daily_target = 1200;

// 5. บันทึกลงฐานข้อมูล (เพิ่มคอลัมน์ goal_preference ลงไปในคำสั่ง)
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

// ปรับ Type Binding ให้ตรงกับ 10 ตัวแปร (เพิ่มตัวแปร string สำหรับ goal_preference เข้าไป)
$stmt->bind_param("iisssddiss", 
    $user_id,       // i (int)
    $daily_target,  // i (int)
    $diet,          // s (string)
    $diseases_str,  // s (string)
    $goal,          // s (string) -> บันทึกค่า goal ลงฐานข้อมูล
    $weight,        // d (double)
    $height,        // d (double)
    $age,           // i (int)
    $gender,        // s (string)
    $activity       // s (string)
);

if ($stmt->execute()) {
    header("Location: dashboard.php");
} else {
    echo "เกิดข้อผิดพลาด: " . $conn->error;
}
?>