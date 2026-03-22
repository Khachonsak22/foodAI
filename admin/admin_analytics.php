<?php
session_start();
include '../config/connect.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit(); }

$user_id = $_SESSION['user_id'];
$admin_stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$admin_stmt->bind_param("i", $user_id);
$admin_stmt->execute();
$admin_data = $admin_stmt->get_result()->fetch_assoc();

if ($admin_data['role'] != 1) {
    header("Location: ../pages/dashboard.php");
    exit();
}

// Daily users (30 days)
$daily_users = $conn->query("
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM users WHERE email NOT LIKE '%@admin.com' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at) ORDER BY date ASC
")->fetch_all(MYSQLI_ASSOC);

// Popular recipes
$popular_recipes = $conn->query("
    SELECT r.title, COUNT(ml.id) as count FROM recipes r
    LEFT JOIN meal_logs ml ON r.id = ml.recipe_id
    GROUP BY r.id ORDER BY count DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Health conditions distribution
$health_dist = $conn->query("
    SELECT health_conditions, COUNT(*) as count FROM health_profiles
    WHERE health_conditions IS NOT NULL AND health_conditions != ''
    GROUP BY health_conditions ORDER BY count DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// Calorie distribution
$calorie_dist = $conn->query("
    SELECT 
        CASE 
            WHEN daily_calorie_target < 1500 THEN 'น้อยกว่า 1500'
            WHEN daily_calorie_target < 2000 THEN '1500-2000'
            WHEN daily_calorie_target < 2500 THEN '2000-2500'
            ELSE '2500+'
        END as 'range',
        COUNT(*) as count
    FROM health_profiles
    WHERE daily_calorie_target > 0
    GROUP BY 'range'
")->fetch_all(MYSQLI_ASSOC);

// Meal logs by month (6 months)
$meal_trends = $conn->query("
    SELECT DATE_FORMAT(logged_at, '%Y-%m') as month, COUNT(*) as count
    FROM meal_logs
    WHERE logged_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month ORDER BY month ASC
")->fetch_all(MYSQLI_ASSOC);

// Active users trend
$active_trend = $conn->query("
    SELECT DATE(logged_at) as date, COUNT(DISTINCT user_id) as count
    FROM meal_logs
    WHERE logged_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY DATE(logged_at) ORDER BY date ASC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root{--bg:#f5f8f5;--bdr:#e8f0e9;--txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;--sb-w:260px;}
        body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;}
        .sidebar{width:var(--sb-w);min-height:100vh;background:#fff;border-right:1px solid #e5ede6;position:fixed;z-index:100;}
        .page-wrap{margin-left:var(--sb-w);flex:1;min-width:0;}
        .card{background:#fff;border:1px solid var(--bdr);border-radius:18px;padding:22px;height:100%;display:flex;flex-direction:column;}
        .chart-container-circle { position: relative; height: 280px; width: 100%; margin: auto; }
        .chart-container-line { position: relative; height: 300px; width: 100%; }
        
        /*CSS สำหรับปุ่ม Hamburger และ Responsive บนมือถือ*/
        .hamburger {
        display: none;
        width: 40px; height: 40px; border-radius: 10px;
        background: #fff; border: 1px solid var(--bdr);
        align-items: center; justify-content: center;
        cursor: pointer; color: var(--txt); font-size: 1.1rem;
        transition: all .2s; margin-right: 14px; flex-shrink: 0;
        }
        .hamburger:hover { background: var(--g50); color: var(--g600); border-color: var(--g300); }

        @media (max-width: 1024px) {
        .hamburger { display: flex; }
        .page-wrap { margin-left: 0; }
        .topbar { padding: 0 1.25rem; }
        main { padding: 1.5rem 1rem !important; }
        }
    </style>
</head>
<body>

<?php include '../includes/sidebar_admin.php' ?>

<div class="page-wrap">
    <header class="topbar h-[66px] bg-white/95 backdrop-blur-md border-b border-[#e5ede6] flex items-center px-6 sticky top-0 z-50">
        <button class="hamburger"><i class="fas fa-bars"></i></button>
        <div>
            <h1 class="font-extrabold text-lg leading-tight">Analytics Dashboard</h1>
            <p class="text-xs text-gray-500">สถิติและวิเคราะห์ระบบ</p>
        </div>
    </header>

    <main class="p-6 lg:p-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <div class="card lg:col-span-2">
                <h2 class="font-bold mb-4">จำนวนผู้ใช้ใหม่ (30 วัน)</h2>
                <div class="chart-container-line">
                    <canvas id="userChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h2 class="font-bold mb-4">Top 10 เมนู</h2>
                <div class="chart-container-line">
                    <canvas id="recipeChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h2 class="font-bold mb-4 text-center lg:text-left">โรคประจำตัว</h2>
                <div class="chart-container-circle">
                    <canvas id="healthChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h2 class="font-bold mb-4 text-center lg:text-left">เป้าหมายแคลอรี่</h2>
                <div class="chart-container-circle">
                    <canvas id="calorieChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h2 class="font-bold mb-4">Meal Logs (6 เดือน)</h2>
                <div class="chart-container-line">
                    <canvas id="mealChart"></canvas>
                </div>
            </div>

            <div class="card lg:col-span-2">
                <h2 class="font-bold mb-4">Active Users (14 วัน)</h2>
                <div class="chart-container-line">
                    <canvas id="activeChart"></canvas>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
// ตั้งค่าเริ่มต้นสำหรับทุก Chart
Chart.defaults.font.family = 'Kanit';
Chart.defaults.maintainAspectRatio = false;

// User Chart
new Chart(document.getElementById('userChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($daily_users, 'date')) ?>,
        datasets: [{
            label: 'ผู้ใช้ใหม่',
            data: <?= json_encode(array_column($daily_users, 'count')) ?>,
            borderColor: '#22c55e',
            backgroundColor: 'rgba(34, 197, 94, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: { plugins: { legend: { display: false } } }
});

// Recipe Chart
new Chart(document.getElementById('recipeChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($popular_recipes, 'title')) ?>,
        datasets: [{
            label: 'ครั้ง',
            data: <?= json_encode(array_column($popular_recipes, 'count')) ?>,
            backgroundColor: '#22c55e',
            borderRadius: 5
        }]
    },
    options: { indexAxis: 'y', plugins: { legend: { display: false } } }
});

// Health Chart (Doughnut)
new Chart(document.getElementById('healthChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($health_dist, 'health_conditions')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($health_dist, 'count')) ?>,
            backgroundColor: ['#22c55e', '#14b8a6', '#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#ef4444', '#6b7280']
        }]
    },
    options: {
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 15 } }
        },
        cutout: '65%'
    }
});

// Calorie Chart (Pie)
new Chart(document.getElementById('calorieChart'), {
    type: 'pie',
    data: {
        labels: <?= json_encode(array_column($calorie_dist, 'range')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($calorie_dist, 'count')) ?>,
            backgroundColor: ['#dcfce7', '#86efac', '#22c55e', '#15803d']
        }]
    },
    options: {
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 15 } }
        }
    }
});

// Meal Chart
new Chart(document.getElementById('mealChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($meal_trends, 'month')) ?>,
        datasets: [{
            label: 'บันทึก',
            data: <?= json_encode(array_column($meal_trends, 'count')) ?>,
            backgroundColor: '#14b8a6',
            borderRadius: 5
        }]
    },
    options: { plugins: { legend: { display: false } } }
});

// Active Chart
new Chart(document.getElementById('activeChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($active_trend, 'date')) ?>,
        datasets: [{
            label: 'Active Users',
            data: <?= json_encode(array_column($active_trend, 'count')) ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: { plugins: { legend: { display: false } } }
});
</script>
</body>
</html>