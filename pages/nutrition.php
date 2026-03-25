<?php
session_start();
include '../config/connect.php';
date_default_timezone_set('Asia/Bangkok');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* ── User info ── */
$u_stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$u_data    = $u_stmt->get_result()->fetch_assoc();
$firstName = $u_data['first_name'] ?? 'User';
$lastName  = $u_data['last_name']  ?? '';
$initials  = mb_strtoupper(mb_substr($firstName,0,1)).mb_strtoupper(mb_substr($lastName,0,1));

/* ── Health profile ── */
$hp = $conn->prepare("SELECT daily_calorie_target, dietary_type, health_conditions FROM health_profiles WHERE user_id = ?");
$hp->bind_param("i", $user_id);
$hp->execute();
$hp_row    = $hp->get_result()->fetch_assoc();
$targetCal = $hp_row['daily_calorie_target'] ?? 2000;
$dietType  = $hp_row['dietary_type'] ?? 'ทั่วไป';
$conditions = $hp_row['health_conditions'] ?? 'ไม่มี';

/* ── Date range selection ── */
$range = $_GET['range'] ?? '7';
$valid_ranges = ['7', '14', '30'];
if (!in_array($range, $valid_ranges)) $range = '7';

$start_date = date('Y-m-d', strtotime("-$range days"));
$end_date   = date('Y-m-d');
$range_int  = (int)$range; // ตัวแปรสำหรับใช้ใน Query SQL

/* ── Fetch daily calorie data for range ── */
$daily_data = [];
for ($i = $range_int - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    
    // แคลอรี่ที่ "กดบันทึกกินเอง" และเป็นเมนูที่ "มาจาก AI"
    $s1 = $conn->prepare("
        SELECT COALESCE(SUM(r.calories),0) as c 
        FROM meal_logs ml 
        JOIN recipes r ON ml.recipe_id = r.id 
        WHERE ml.user_id = ? AND DATE(ml.logged_at) = ?
        AND r.title IN (SELECT menu_name FROM ai_saved_menus WHERE user_id = ?)
    ");
    $s1->bind_param("isi", $user_id, $d, $user_id);
    $s1->execute();
    $ai_cal = (int)$s1->get_result()->fetch_assoc()['c'];
    
    // แคลอรี่ที่ "กดบันทึกกินเอง" และเป็น "เมนูทั่วไปของระบบ"
    $s2 = $conn->prepare("
        SELECT COALESCE(SUM(r.calories),0) as c 
        FROM meal_logs ml 
        JOIN recipes r ON ml.recipe_id = r.id 
        WHERE ml.user_id = ? AND DATE(ml.logged_at) = ?
        AND r.title NOT IN (SELECT menu_name FROM ai_saved_menus WHERE user_id = ?)
    ");
    $s2->bind_param("isi", $user_id, $d, $user_id);
    $s2->execute();
    $rec_cal = (int)$s2->get_result()->fetch_assoc()['c'];
    
    $total = $ai_cal + $rec_cal;
    $daily_data[] = [
        'date' => $d,
        'date_short' => date('j M', strtotime($d)),
        'ai_cal' => $ai_cal,
        'rec_cal' => $rec_cal,
        'total' => $total,
        'pct' => $targetCal > 0 ? round($total / $targetCal * 100) : 0
    ];
}

/* ── Statistics ── */
$total_cal_period = array_sum(array_column($daily_data, 'total'));

// ✅ นับเฉพาะวันที่มีการบันทึก (total > 0)
$days_with_records = count(array_filter($daily_data, fn($d) => $d['total'] > 0));

// ✅ คำนวณค่าเฉลี่ยจากวันที่บันทึกจริง
$avg_cal_day = $days_with_records > 0 ? round($total_cal_period / $days_with_records) : 0;

$days_met_goal    = count(array_filter($daily_data, fn($d) => $d['total'] >= $targetCal));
$max_day          = !empty($daily_data) ? max(array_column($daily_data, 'total')) : 0;
$min_day          = !empty($daily_data) ? min(array_filter(array_column($daily_data, 'total'), fn($v) => $v > 0) ?: [0]) : 0;

/* ── Meal type breakdown (dynamic range) ── */
$meal_types = ['breakfast' => 0, 'lunch' => 0, 'dinner' => 0, 'snack' => 0];
$mt_stmt = $conn->prepare(
    "SELECT ml.meal_type, COALESCE(SUM(r.calories),0) as total
     FROM meal_logs ml
     JOIN recipes r ON ml.recipe_id = r.id
     WHERE ml.user_id = ? AND ml.logged_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
     GROUP BY ml.meal_type"
);
$mt_stmt->bind_param("ii", $user_id, $range_int);
$mt_stmt->execute();
$mt_res = $mt_stmt->get_result();
while ($row = $mt_res->fetch_assoc()) {
    $meal_types[$row['meal_type']] = (int)$row['total'];
}
$total_meal_cal = array_sum($meal_types);

/* ── Top consumed recipes (dynamic range) ── */
$top_stmt = $conn->prepare(
    "SELECT r.id, r.title, r.calories, COUNT(ml.id) as log_count, SUM(r.calories) as total_cal
     FROM meal_logs ml
     JOIN recipes r ON ml.recipe_id = r.id
     WHERE ml.user_id = ? AND ml.logged_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
     GROUP BY r.id
     ORDER BY log_count DESC, total_cal DESC
     LIMIT 5"
);
$top_stmt->bind_param("ii", $user_id, $range_int);
$top_stmt->execute();
$top_recipes = $top_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* ── Consistency score (dynamic range) ── */
// ดความสม่ำเสมอเฉพาะวันที่ "กดบันทึกการกิน" เท่านั้น
$consistency_stmt = $conn->prepare(
    "SELECT COUNT(DISTINCT DATE(logged_at)) as days_logged
     FROM meal_logs 
     WHERE user_id = ? AND logged_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"
);
$consistency_stmt->bind_param("ii", $user_id, $range_int);
$consistency_stmt->execute();
$days_logged = (int)$consistency_stmt->get_result()->fetch_assoc()['days_logged'];
$consistency_pct = $range_int > 0 ? round($days_logged / $range_int * 100) : 0;

/* ── Nutrition recommendations based on health conditions ── */
$recommendations = [];
if (stripos($conditions, 'เบาหวาน') !== false || stripos($conditions, 'diabetes') !== false) {
    $recommendations[] = ['icon' => '🩺', 'title' => 'ควบคุมน้ำตาล', 'text' => 'เลือกคาร์โบไฮเดรตเชิงซ้อน หลีกเลี่ยงน้ำตาล', 'color' => '#dc2626'];
}
if (stripos($conditions, 'ความดัน') !== false || stripos($conditions, 'hypertension') !== false) {
    $recommendations[] = ['icon' => '🧂', 'title' => 'ลดโซเดียม', 'text' => 'เลือกเมนูโซเดียมต่ำ หลีกเลี่ยงอาหารดอง', 'color' => '#ea580c'];
}
if (stripos($conditions, 'ไขมัน') !== false || stripos($conditions, 'คอเลส') !== false) {
    $recommendations[] = ['icon' => '🥑', 'title' => 'ไขมันดี', 'text' => 'เน้นโอเมก้า 3 จากปลา ถั่ว อะโวคาโด', 'color' => '#16a34a'];
}
if (empty($recommendations)) {
    $recommendations[] = ['icon' => '💚', 'title' => 'สุขภาพดี', 'text' => 'รักษาสมดุลโภชนาการและออกกำลังกายสม่ำเสมอ', 'color' => '#22c55e'];
}

$max_chart = max(array_column($daily_data, 'total') ?: [1]);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>โภชนาการ — FoodAI</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
:root{
  --g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;
  --g400:#4ade80;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;
  --t400:#2dd4bf;--t500:#14b8a6;--t600:#0d9488;
  --bg:#f5f8f5;--card:#fff;--bdr:#e8f0e9;
  --txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;
  --sb-w:248px;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,#c8e6c9 1px,transparent 1px);background-size:28px 28px;opacity:.35;}

main { 
    padding: 2rem 2.5rem 3.5rem; 
    width: 100%; 
    max-width: 100%; 
    margin: 0 auto; 
}

/* Sidebar */
.sidebar{width:var(--sb-w);min-height:100vh;background:#fff;border-right:1px solid #e5ede6;display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;z-index:100;box-shadow:4px 0 24px rgba(34,197,94,.06);}
.sb-logo{padding:24px 22px 20px;border-bottom:1px solid #e5ede6;display:flex;align-items:center;gap:11px;}
.sb-logo-icon{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--g500),var(--t500));display:flex;align-items:center;justify-content:center;font-size:1.1rem;box-shadow:0 4px 12px rgba(34,197,94,.35);}
.sb-logo-text{font-family:'Nunito',sans-serif;font-size:1.18rem;font-weight:800;color:var(--g700);letter-spacing:-.02em;line-height:1;}
.sb-logo-sub{font-size:.6rem;font-weight:600;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin-top:2px;}
.sb-label{font-size:.6rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);padding:18px 22px 8px;}
.sb-nav{padding:6px 12px;display:flex;flex-direction:column;gap:2px;flex:1;overflow-y:auto;}
.nav-item{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:12px;text-decoration:none;color:var(--sub);font-size:.82rem;font-weight:500;transition:all .18s;}
.nav-item:hover{background:var(--g50);color:var(--g700);transform:translateX(2px);}
.nav-item.active{background:var(--g50);color:var(--g600);font-weight:600;box-shadow:inset 3px 0 0 var(--g500);}
.nav-item.active .ni{background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;box-shadow:0 3px 10px rgba(34,197,94,.38);}
.ni{width:34px;height:34px;border-radius:10px;background:var(--g50);border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;transition:all .18s;color:var(--g600);}
.nav-item:hover .ni{background:var(--g100);border-color:var(--g200);}
.nb{margin-left:auto;background:var(--g500);color:#fff;font-size:.6rem;font-weight:700;padding:2px 7px;border-radius:99px;}
.nb.orange{background:#f97316;}
.sb-div{height:1px;background:#e5ede6;margin:6px 12px;}
.sb-user{border-top:1px solid #e5ede6;padding:16px;display:flex;align-items:center;gap:11px;background:var(--g50);}
.sb-av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--t400));display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:800;color:#fff;font-family:'Nunito',sans-serif;box-shadow:0 2px 8px rgba(34,197,94,.3);}
.sb-un{font-size:.78rem;font-weight:600;color:var(--txt);line-height:1.2;}
.sb-out{margin-left:auto;width:30px;height:30px;border-radius:8px;border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:.72rem;text-decoration:none;transition:all .18s;}
.sb-out:hover{background:#fee2e2;border-color:#fecaca;color:#dc2626;}

.page-wrap{margin-left:var(--sb-w);flex:1;position:relative;z-index:1;min-width:0;}
.topbar{height:62px;background:rgba(255,255,255,.9);backdrop-filter:blur(12px);border-bottom:1px solid #e5ede6;display:flex;align-items:center;padding:0 2.5rem;gap:14px;position:sticky;top:0;z-index:50;}
.tb-back{width:38px;height:38px;border-radius:11px;background:#fff;border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;color:var(--sub);text-decoration:none;font-size:.8rem;transition:all .18s;}
.tb-back:hover{background:var(--g50);border-color:var(--g200);color:var(--g600);}

@keyframes slideUp{from{opacity:0;transform:translateY(18px);}to{opacity:1;transform:translateY(0);}}
.rv{opacity:0;animation:slideUp .5s cubic-bezier(.22,1,.36,1) forwards;}
.rv1{animation-delay:.04s;}.rv2{animation-delay:.10s;}.rv3{animation-delay:.17s;}.rv4{animation-delay:.24s;}.rv5{animation-delay:.31s;}.rv6{animation-delay:.38s;}

.card{background:var(--card);border:1px solid var(--bdr);border-radius:20px;transition:box-shadow .2s;}
.card:hover{box-shadow:0 8px 24px rgba(34,197,94,.08);}

.stitle{font-family:'Nunito',sans-serif;font-size:1rem;font-weight:800;color:var(--txt);display:flex;align-items:center;gap:8px;}
.gline{height:3px;background:linear-gradient(90deg,var(--g500),transparent);border-radius:99px;}

/* Stats card */
.stat-card{background:#fff;border:1px solid var(--bdr);border-radius:18px;padding:20px;text-align:center;box-shadow:0 4px 12px rgba(0,0,0,.02);transition:all .2s;}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(34,197,94,.08);}
.stat-icon{width:46px;height:46px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;margin:0 auto 12px;}
.stat-val{font-family:'Nunito',sans-serif;font-size:1.8rem;font-weight:800;color:var(--txt);line-height:1.1;margin-bottom:2px;}
.stat-lbl{font-size:.72rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;}

/* Chart container */
.chart-container{padding:24px;border-bottom:1px solid var(--bdr);}
.chart-bars{display:flex;align-items:flex-end;height:220px;gap:8px;padding-top:20px;position:relative;}
.chart-bg-lines{position:absolute;inset:0;display:flex;flex-direction:column;justify-content:space-between;pointer-events:none;z-index:0;padding-bottom:24px;}
.bg-line{border-top:1px dashed #e5ede6;position:relative;}
.bg-line span{position:absolute;left:-40px;top:-8px;font-size:.65rem;color:#cbd5e1;font-family:'Nunito',sans-serif;}

.c-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:8px;z-index:1;height:100%;justify-content:flex-end;}
.c-bar-wrap{width:100%;max-width:32px;height:100%;display:flex;flex-direction:column;justify-content:flex-end;position:relative;border-radius:6px;background:var(--g50);cursor:pointer;}

.c-bar-ai, .c-bar-rec{width:100%;transition:all .3s;}
.c-bar-ai{background:linear-gradient(to top, var(--t500), var(--t400));border-radius:6px 6px 0 0;}
.c-bar-rec{background:linear-gradient(to top, var(--g600), var(--g400));border-radius:0 0 6px 6px;}

.c-bar-wrap:hover .c-bar-ai{background:var(--t600);}
.c-bar-wrap:hover .c-bar-rec{background:var(--g700);}

/* Tooltip */
.c-tooltip{position:absolute;top:-45px;left:50%;transform:translateX(-50%) translateY(10px);background:#1a2e1a;color:#fff;padding:6px 10px;border-radius:8px;font-size:.75rem;font-family:'Nunito',sans-serif;font-weight:700;pointer-events:none;opacity:0;transition:all .2s;white-space:nowrap;box-shadow:0 4px 12px rgba(0,0,0,.15);z-index:10;}
.c-tooltip::after{content:'';position:absolute;bottom:-4px;left:50%;transform:translateX(-50%);border-width:4px 4px 0;border-style:solid;border-color:#1a2e1a transparent transparent transparent;}
.c-bar-wrap:hover .c-tooltip{opacity:1;transform:translateX(-50%) translateY(0);}

.c-date{font-size:.65rem;color:var(--muted);font-weight:500;}

/* Target line */
.target-line{position:absolute;left:0;right:0;border-top:2px dashed #f59e0b;z-index:5;pointer-events:none;display:flex;align-items:center;}
.target-badge{background:#f59e0b;color:#fff;font-size:.6rem;font-weight:700;padding:2px 6px;border-radius:4px;margin-left:8px;font-family:'Nunito',sans-serif;}

/* Donut */
.donut-wrap{position:relative;width:160px;height:160px;margin:0 auto;}
.donut-center{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;}
.donut-num{font-family:'Nunito',sans-serif;font-size:1.4rem;font-weight:800;color:var(--txt);line-height:1;}
.donut-lbl{font-size:.65rem;color:var(--muted);text-transform:uppercase;}
.legend{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:24px;}
.leg-item{display:flex;align-items:center;gap:8px;font-size:.8rem;color:var(--sub);}
.leg-dot{width:10px;height:10px;border-radius:50%;}

/* Range tabs */
.range-tabs{display:flex;background:var(--g50);padding:4px;border-radius:12px;border:1px solid var(--bdr);}
.range-tab{padding:6px 16px;font-size:.75rem;font-weight:600;color:var(--sub);text-decoration:none;border-radius:8px;transition:all .2s;}
.range-tab:hover{color:var(--g600);}
.range-tab.active{background:#fff;color:var(--g600);box-shadow:0 2px 6px rgba(0,0,0,.04);}

.menu-toggle{display:none;background:none;border:none;font-size:1.2rem;color:var(--txt);cursor:pointer;}
@media(max-width:992px){
  :root{--sb-w:0px;}
  .sidebar{transform:translateX(-100%);transition:transform .3s;width:260px;}
  .sidebar.show{transform:translateX(0);}
  .menu-toggle{display:block;}
  .c-bar-wrap{max-width:20px;}
  .c-date{font-size:.55rem;}
}
@media(max-width:768px){
  .rv2 { grid-template-columns: 1fr 1fr; }
  main{padding:1.5rem 1rem;}
  .topbar{padding:0 1rem;}
  .grid-layout{grid-template-columns:1fr !important;}
}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sb-logo">
    <div class="sb-logo-icon"><i class="fas fa-leaf text-white"></i></div>
    <div>
      <div class="sb-logo-text">FoodAI</div>
      <div class="sb-logo-sub">Nutrition Guide</div>
    </div>
  </div>
  
  <div class="sb-label">Menu</div>
  <nav class="sb-nav">
    <a href="dashboard.php" class="nav-item">
      <div class="ni"><i class="fas fa-home"></i></div>
      หน้าหลัก
    </a>
    <a href="ai_recommend.php" class="nav-item">
      <div class="ni"><i class="fas fa-magic"></i></div>
      AI จัดมื้ออาหาร
    </a>
    <a href="food_detect.php" class="nav-item">
      <div class="ni"><i class="fas fa-camera"></i></div>
      AI วิเคราะห์อาหาร
    </a>
    <a href="meal_log.php" class="nav-item">
      <div class="ni"><i class="fas fa-book-open"></i></div>
      บันทึกการกิน
    </a>
    <a href="nutrition.php" class="nav-item active">
      <div class="ni"><i class="fas fa-chart-pie"></i></div>
      สรุปโภชนาการ
    </a>
    <a href="recipes.php" class="nav-item">
      <div class="ni"><i class="fas fa-utensils"></i></div>
      สูตรอาหาร
    </a>
    <a href="health_profile.php" class="nav-item">
      <div class="ni"><i class="fas fa-heartbeat"></i></div>
      ข้อมูลสุขภาพ
    </a>
  </nav>

  <div class="sb-user">
    <div class="sb-av"><?= $initials ?></div>
    <div>
      <div class="sb-un"><?= htmlspecialchars($firstName) ?></div>
      <div style="font-size:.65rem;color:var(--muted);"><?= $targetCal ?> kcal/day</div>
    </div>
    <a href="logout.php" class="sb-out" title="ออกจากระบบ"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</aside>

<div class="page-wrap">
  <header class="topbar">
    <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('show')">
      <i class="fas fa-bars"></i>
    </button>
    <a href="dashboard.php" class="tb-back"><i class="fas fa-arrow-left"></i></a>
    <div>
      <div style="font-family:'Nunito',sans-serif;font-size:.95rem;font-weight:800;color:var(--txt);">วิเคราะห์โภชนาการ</div>
      <div style="font-size:.7rem;color:var(--muted);">สถิติและข้อมูลสุขภาพของคุณ</div>
    </div>
    <div style="margin-left:auto;">
      <div class="range-tabs">
        <a href="?range=7" class="range-tab <?= $range==='7' ?'active':'' ?>">7 วัน</a>
        <a href="?range=14" class="range-tab <?= $range==='14'?'active':'' ?>">14 วัน</a>
        <a href="?range=30" class="range-tab <?= $range==='30'?'active':'' ?>">30 วัน</a>
      </div>
    </div>
  </header>

  <main>
    <div class="rv rv1" style="margin-bottom:1.8rem;">
      <p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">การวิเคราะห์</p>
      <h1 style="font-family:'Nunito',sans-serif;font-size:1.6rem;font-weight:800;color:var(--txt);line-height:1.1;">
        โภชนาการ <?= $range ?> วันที่ผ่านมา
      </h1>
      <div class="gline" style="width:50px;margin-top:10px;"></div>
    </div>

    <div class="rv rv2" style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:2rem;">
      <div class="stat-card">
        <div class="stat-icon" style="background:#f0fdf4;border:1px solid var(--g200);color:var(--g600);">
          <i class="fas fa-fire"></i>
        </div>
        <div class="stat-val"><?= number_format($avg_cal_day) ?></div>
        <div class="stat-lbl">เฉลี่ย Kcal/วัน</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#fef3c7;border:1px solid #fde68a;color:#d97706;">
          <i class="fas fa-trophy"></i>
        </div>
        <div class="stat-val"><?= $days_met_goal ?> <span style="font-size:1rem;color:var(--muted);">/<?= $range ?></span></div>
        <div class="stat-lbl">วันที่ถึงเป้าหมาย</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#eff6ff;border:1px solid #bfdbfe;color:#2563eb;">
          <i class="fas fa-arrow-up"></i>
        </div>
        <div class="stat-val"><?= number_format($max_day) ?></div>
        <div class="stat-lbl">กินเยอะสุด (Kcal)</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#faf5ff;border:1px solid #e9d5ff;color:#9333ea;">
          <i class="fas fa-arrow-down"></i>
        </div>
        <div class="stat-val"><?= number_format($min_day) ?></div>
        <div class="stat-lbl">กินน้อยสุด (Kcal)</div>
      </div>
    </div>

    <div class="rv rv3 card" style="margin-bottom:2rem;">
      <div style="padding:20px 24px;border-bottom:1px solid var(--bdr);display:flex;align-items:center;justify-content:space-between;">
        <h2 class="stitle"><i class="fas fa-chart-bar" style="color: var(--g500);"></i> ปริมาณแคลอรี่รายวัน</h2>
        <div style="display:flex;gap:16px;font-size:.75rem;color:var(--sub);">
          <div style="display:flex;align-items:center;gap:6px;">
            <div style="width:12px;height:12px;border-radius:3px;background:var(--g500);"></div> เมนูทั่วไป
          </div>
          <div style="display:flex;align-items:center;gap:6px;">
            <div style="width:12px;height:12px;border-radius:3px;background:var(--t500);"></div> จัดโดย AI
          </div>
        </div>
      </div>
      
      <div class="chart-container">
        <div class="chart-bars">
          <div class="chart-bg-lines">
            <div class="bg-line"><span><?= number_format($max_chart) ?></span></div>
            <div class="bg-line"><span><?= number_format($max_chart*0.75) ?></span></div>
            <div class="bg-line"><span><?= number_format($max_chart*0.5) ?></span></div>
            <div class="bg-line"><span><?= number_format($max_chart*0.25) ?></span></div>
            <div class="bg-line" style="border-top-color:var(--bdr);"><span>0</span></div>
          </div>
          
          <?php if($targetCal > 0 && $max_chart > 0): 
            $t_pct = min(100, ($targetCal / $max_chart) * 100);
          ?>
          <div class="target-line" style="bottom:<?= $t_pct ?>%;">
            <div class="target-badge">เป้าหมาย <?= number_format($targetCal) ?></div>
          </div>
          <?php endif; ?>

          <?php foreach ($daily_data as $idx => $d): 
            $h_rec = $max_chart > 0 ? ($d['rec_cal'] / $max_chart * 100) : 0;
            $h_ai  = $max_chart > 0 ? ($d['ai_cal'] / $max_chart * 100) : 0;
            // ซ่อน label วันที่บ้างถ้าแสดง 30 วันแล้วมันแน่นไป
            $show_date = ($range <= 14) || ($idx % 2 == 0);
          ?>
          <div class="c-col">
            <div class="c-bar-wrap">
              <div class="c-tooltip"><?= number_format($d['total']) ?> kcal</div>
              <?php if($h_ai > 0): ?>
              <div class="c-bar-ai" style="height:<?= $h_ai ?>%;"></div>
              <?php endif; ?>
              <?php if($h_rec > 0): ?>
              <div class="c-bar-rec" style="height:<?= $h_rec ?>%; <?= $h_ai > 0 ? 'border-radius:0 0 6px 6px;' : 'border-radius:6px;' ?>"></div>
              <?php endif; ?>
            </div>
            <div class="c-date"><?= $show_date ? $d['date_short'] : '' ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="grid-layout" style="display:grid;grid-template-columns:300px 1fr 300px;gap:20px;">
      
      <div class="rv rv4" style="display:flex;flex-direction:column;gap:20px;">
        
        <div class="card" style="padding:24px;">
          <h2 class="stitle" style="margin-bottom:18px;"><i class="fas fa-utensils" style="color: #22c55e;"></i> สัดส่วนมื้ออาหาร</h2>
          <p style="font-size:.7rem;color:var(--muted);margin-bottom:16px;"><?= $range ?> วันที่ผ่านมา</p>
          
          <?php
          $meal_colors = [
            'breakfast' => '#f59e0b',
            'lunch' => '#22c55e',
            'dinner' => '#6366f1',
            'snack' => '#f97316'
          ];
          $meal_labels = [
            'breakfast' => 'มื้อเช้า',
            'lunch' => 'มื้อกลางวัน',
            'dinner' => 'มื้อเย็น',
            'snack' => 'ของว่าง'
          ];
          
          // Calculate angles for donut
          $angles = [];
          $start_angle = -90;
          foreach ($meal_types as $mt => $cal) {
              $pct = $total_meal_cal > 0 ? $cal / $total_meal_cal : 0;
              $angle = $pct * 360;
              $angles[$mt] = [
                  'start' => $start_angle,
                  'angle' => $angle,
                  'pct' => round($pct * 100)
              ];
              $start_angle += $angle;
          }
          ?>
          
          <div class="donut-wrap">
            <svg viewBox="0 0 160 160" width="160" height="160">
              <?php
              $cx = 80; $cy = 80; $r = 65; $stroke = 22;
              $circumference = 2 * pi() * $r;
              foreach ($meal_types as $mt => $cal):
                if ($cal <= 0) continue;
                $a = $angles[$mt];
                $dasharray = ($a['angle'] / 360 * $circumference) . " " . $circumference;
              ?>
              <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>" 
                      fill="transparent" 
                      stroke="<?= $meal_colors[$mt] ?>" 
                      stroke-width="<?= $stroke ?>" 
                      stroke-dasharray="<?= $dasharray ?>"
                      transform="rotate(<?= $a['start'] ?> <?= $cx ?> <?= $cy ?>)"
                      style="transition:all 1s ease;" />
              <?php endforeach; ?>
              
              <?php if($total_meal_cal == 0): ?>
              <circle cx="80" cy="80" r="65" fill="transparent" stroke="#f1f5f9" stroke-width="22" />
              <?php endif; ?>
            </svg>
            <div class="donut-center">
              <div class="donut-num"><?= number_format($total_meal_cal) ?></div>
              <div class="donut-lbl">Kcal รวม</div>
            </div>
          </div>

          <div class="legend">
            <?php foreach($meal_types as $mt => $cal): ?>
            <div class="leg-item">
              <div class="leg-dot" style="background:<?= $meal_colors[$mt] ?>;"></div>
              <div>
                <div style="font-weight:600;color:var(--txt);line-height:1.2;"><?= $meal_labels[$mt] ?></div>
                <div style="font-size:.7rem;"><?= $angles[$mt]['pct'] ?? 0 ?>% (<?= number_format($cal) ?>)</div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div>

      <div class="rv rv5">
        <div class="card" style="padding:24px;height:100%;">
          <h2 class="stitle" style="margin-bottom:6px;"><i class="fas fa-lightbulb" style="color: #eab308;"></i> คำแนะนำสำหรับคุณ</h2>
          <p style="font-size:.75rem;color:var(--muted);margin-bottom:20px;line-height:1.5;">
            วิเคราะห์จากเป้าหมาย <strong><?= number_format($targetCal) ?> kcal</strong><br>
            และปัญหาสุขภาพ: <strong><?= htmlspecialchars($conditions) ?></strong>
          </p>
          
          <div style="display:flex;flex-direction:column;gap:12px;">
            <?php foreach($recommendations as $rec): ?>
            <div style="display:flex;gap:14px;padding:16px;background:var(--g50);border:1px solid var(--bdr);border-radius:14px;align-items:flex-start;">
              <div style="width:36px;height:36px;border-radius:10px;background:#fff;display:flex;align-items:center;justify-content:center;font-size:1.2rem;box-shadow:0 2px 6px rgba(0,0,0,.03);flex-shrink:0;">
                <?= $rec['icon'] ?>
              </div>
              <div>
                <div style="font-size:.85rem;font-weight:700;color:<?= $rec['color'] ?>;margin-bottom:3px;"><?= $rec['title'] ?></div>
                <div style="font-size:.8rem;color:var(--sub);line-height:1.5;"><?= $rec['text'] ?></div>
              </div>
            </div>
            <?php endforeach; ?>
            
            <?php 
            // Add dynamic insight based on data
            if ($avg_cal_day > $targetCal + 200) {
                echo '<div style="padding:14px;background:#fee2e2;border:1px solid #fecaca;border-radius:12px;font-size:.8rem;color:#b91c1c;margin-top:8px;">
                      <i class="fas fa-exclamation-triangle"></i> ช่วงนี้คุณรับประทานเกินเป้าหมายเฉลี่ย '.number_format($avg_cal_day - $targetCal).' kcal/วัน ลองลดปริมาณของว่างหรือคาร์โบไฮเดรตลงเล็กน้อย
                      </div>';
            } elseif ($avg_cal_day > 0 && $avg_cal_day < $targetCal - 300) {
                echo '<div style="padding:14px;background:#fef3c7;border:1px solid #fde68a;border-radius:12px;font-size:.8rem;color:#b45309;margin-top:8px;">
                      <i class="fas fa-info-circle"></i> คุณรับประทานน้อยกว่าเป้าหมายค่อนข้างมาก ระวังร่างกายขาดสารอาหารที่จำเป็น
                      </div>';
            }
            ?>
          </div>
        </div>
      </div>

      <div class="rv rv6" style="display:flex;flex-direction:column;gap:20px;">
        
        <div class="card" style="padding:24px;">
          <h2 class="stitle" style="margin-bottom:14px;"><i class="fas fa-fire-alt" style="color: #ff5722;"></i> เมนูที่บันทึกบ่อย</h2>
          <p style="font-size:.7rem;color:var(--muted);margin-bottom:14px;"><?= $range ?> วันที่ผ่านมา</p>
          
          <?php if (count($top_recipes) > 0): ?>
          <div style="display:flex;flex-direction:column;gap:9px;">
            <?php foreach ($top_recipes as $idx => $tr): ?>
            <a href="recipe_detail.php?id=<?= $tr['id'] ?>" class="top-row" style="text-decoration:none;">
              <span style="font-family:'Nunito',sans-serif;font-size:1.1rem;font-weight:800;color:var(--g600);min-width:26px;">
                #<?= $idx + 1 ?>
              </span>
              <div style="flex:1;min-width:0;">
                <div style="font-size:.83rem;font-weight:700;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                  <?= htmlspecialchars($tr['title']) ?>
                </div>
                <div style="font-size:.7rem;color:var(--muted);">
                  กินไป <?= $tr['log_count'] ?> ครั้ง &bull; <?= number_format($tr['total_cal']) ?> kcal
                </div>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div style="text-align:center;padding:20px 0;">
            <i class="fas fa-utensils" style="font-size:1.5rem;color:var(--g200);margin-bottom:8px;"></i>
            <p style="font-size:.75rem;color:var(--muted);">ยังไม่มีข้อมูล</p>
          </div>
          <?php endif; ?>
          <style>
            .top-row{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:12px;background:var(--g50);border:1px solid transparent;transition:all .2s;}
            .top-row:hover{background:#fff;border-color:var(--g300);transform:translateY(-2px);box-shadow:0 4px 12px rgba(34,197,94,.1);}
          </style>
        </div>

        <div class="card" style="padding:24px;">
          <h2 class="stitle" style="margin-bottom:18px;"><i class="fas fa-calendar-check" style="color: #3b82f6;"></i> วินัยการกิน</h2>
          <p style="font-size:.7rem;color:var(--muted);margin-bottom:18px;">ความสม่ำเสมอใน <?= $range ?> วัน</p>
          
          <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:12px;">
            <span style="font-family:'Nunito',sans-serif;font-size:2.8rem;font-weight:800;color:#3b82f6;line-height:1;"><?= $consistency_pct ?></span>
            <span style="font-size:1rem;color:var(--muted);font-weight:600;">%</span>
          </div>
          <div style="width:100%;height:8px;background:#eff6ff;border-radius:99px;overflow:hidden;">
            <div style="width:<?= $consistency_pct ?>%;height:100%;background:linear-gradient(90deg,#60a5fa,#3b82f6);border-radius:99px;"></div>
          </div>
          <div style="font-size:.75rem;color:var(--sub);margin-top:12px;text-align:center;">
            บันทึกอาหารแล้ว <strong><?= $days_logged ?></strong> จาก <?= $range ?> วัน
          </div>
        </div>

      </div>

    </div>

  </main>
</div>

</body>
</html>