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

/* ── Fetch daily calorie data for range ── */
$daily_data = [];
for ($i = (int)$range - 1; $i >= 0; $i--) {
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

/* ── Meal type breakdown (last 7 days) ── */
$meal_types = ['breakfast' => 0, 'lunch' => 0, 'dinner' => 0, 'snack' => 0];
$mt_stmt = $conn->prepare(
    "SELECT ml.meal_type, COALESCE(SUM(r.calories),0) as total
     FROM meal_logs ml
     JOIN recipes r ON ml.recipe_id = r.id
     WHERE ml.user_id = ? AND ml.logged_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY ml.meal_type"
);
$mt_stmt->bind_param("i", $user_id);
$mt_stmt->execute();
$mt_res = $mt_stmt->get_result();
while ($row = $mt_res->fetch_assoc()) {
    $meal_types[$row['meal_type']] = (int)$row['total'];
}
$total_meal_cal = array_sum($meal_types);

/* ── Top consumed recipes (last 30 days) ── */
$top_stmt = $conn->prepare(
    "SELECT r.id, r.title, r.calories, COUNT(ml.id) as log_count, SUM(r.calories) as total_cal
     FROM meal_logs ml
     JOIN recipes r ON ml.recipe_id = r.id
     WHERE ml.user_id = ? AND ml.logged_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY r.id
     ORDER BY log_count DESC, total_cal DESC
     LIMIT 5"
);
$top_stmt->bind_param("i", $user_id);
$top_stmt->execute();
$top_recipes = $top_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* ── Consistency score (days logged in last 30) ── */
// ดความสม่ำเสมอเฉพาะวันที่ "กดบันทึกการกิน" เท่านั้น
$consistency_stmt = $conn->prepare(
    "SELECT COUNT(DISTINCT DATE(logged_at)) as days_logged
     FROM meal_logs 
     WHERE user_id = ? AND logged_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
);
$consistency_stmt->bind_param("i", $user_id);
$consistency_stmt->execute();
$days_logged = (int)$consistency_stmt->get_result()->fetch_assoc()['days_logged'];
$consistency_pct = round($days_logged / 30 * 100);

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
.stat-card{background:#fff;border:1px solid var(--bdr);border-radius:18px;padding:20px;text-align:center;transition:all .2s;}
.stat-card:hover{border-color:var(--g300);box-shadow:0 6px 20px rgba(34,197,94,.1);transform:translateY(-2px);}
.stat-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin:0 auto 10px;}
.stat-val{font-family:'Nunito',sans-serif;font-size:1.7rem;font-weight:800;color:var(--txt);line-height:1;}
.stat-lbl{font-size:.7rem;color:var(--muted);margin-top:5px;letter-spacing:.02em;}

/* Chart bar */
.chart-bar{display:flex;flex-direction:column;align-items:center;gap:6px;flex:1;cursor:pointer;transition:opacity .2s;}
.chart-bar:hover{opacity:.85;}
.bar-track{width:100%;background:var(--g50);border-radius:99px 99px 4px 4px;display:flex;flex-direction:column;justify-content:flex-end;height:140px;overflow:hidden;border:1px solid var(--bdr);position:relative;}
.bar-stack{width:100%;border-radius:99px 99px 0 0;transition:height .6s cubic-bezier(.4,0,.2,1);}
.bar-lbl{font-size:.62rem;color:var(--muted);font-weight:500;text-align:center;}

/* Donut chart */
.donut-wrap{position:relative;width:160px;height:160px;margin:0 auto;}
.donut-center{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;}

/* Legend */
.legend-item{display:flex;align-items:center;gap:8px;font-size:.78rem;color:var(--sub);}
.legend-dot{width:12px;height:12px;border-radius:3px;flex-shrink:0;}

/* Recommendation card */
.rec-card{background:#fff;border-left:4px solid;border-radius:14px;padding:14px 16px;transition:all .2s;}
.rec-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.06);transform:translateX(3px);}

/* Top recipe row */
.top-row{background:var(--g50);border:1px solid var(--bdr);border-radius:13px;padding:11px 14px;display:flex;align-items:center;gap:11px;transition:all .18s;}
.top-row:hover{border-color:var(--g300);background:var(--g100);}

/* Range tabs */
.range-tabs{display:flex;gap:6px;background:var(--g50);border-radius:12px;padding:4px;}
.range-tab{padding:6px 16px;border-radius:9px;font-size:.75rem;font-weight:600;color:var(--sub);cursor:pointer;transition:all .18s;text-decoration:none;display:flex;align-items:center;gap:5px;}
.range-tab:hover{background:rgba(255,255,255,.6);color:var(--g700);}
.range-tab.active{background:#fff;color:var(--g600);box-shadow:0 2px 8px rgba(34,197,94,.12);}

::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-thumb{background:var(--g200);border-radius:99px;}

/* ── 🌟 Responsive CSS (ปรับปรุง Topbar ให้ยืดหยุ่น) ── */
.menu-toggle { display: none; width: 38px; height: 38px; border-radius: 11px; background: white; border: 1px solid var(--bdr); align-items: center; justify-content: center; color: var(--sub); font-size: 0.9rem; cursor: pointer; flex-shrink: 0; margin-right: 10px; }

@media (max-width: 1024px) {
  .sidebar { transform: translateX(-100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
  .sidebar.show { transform: translateX(0); }
  .page-wrap { margin-left: 0 !important; }
  .menu-toggle { display: flex; }
  .rv3, .rv4 { grid-template-columns: 1fr !important; } 
}

@media (max-width: 768px) {
  main { padding: 1.5rem 1.2rem 3rem !important; }
  .topbar { flex-wrap: wrap; height: auto; padding: 12px 1.5rem; gap: 10px; }
  .rv2 { grid-template-columns: repeat(2, 1fr) !important; } 
  .rv5 > div { grid-template-columns: 1fr !important; } 
}

@media (max-width: 480px) {
  .topbar { padding: 12px 1rem; }
  /* ดันแท็บ 7, 14, 30 วัน ลงบรรทัดใหม่และขยายเต็ม */
  .topbar > div:last-child { width: 100%; margin-top: 5px; display: flex; justify-content: flex-start; }
  .range-tabs { width: 100%; justify-content: space-between; }
  .range-tab { flex: 1; text-align: center; justify-content: center; }
  .rv2 { grid-template-columns: 1fr !important; } 
}
</style>
</head>
<body>

<?php include '../includes/sidebar.php' ?>

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
        <a href="?range=7"  class="range-tab <?= $range==='7' ?'active':'' ?>">7 วัน</a>
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
        <div class="stat-icon" style="background:#f0fdf4;border:1px solid var(--g200);"><i class="bi bi-bar-chart-line-fill" style="color: #22c55e;"></i></div>
        <div class="stat-val" style="color:var(--g600);"><?= number_format($avg_cal_day) ?></div>
        <div class="stat-lbl">ค่าเฉลี่ย/วัน (kcal)</div>
      </div>

      <div class="stat-card">
        <div class="stat-icon" style="background:#fefce8;border:1px solid #fde68a;"><i class="fa fa-bullseye" style="color: #ff5722;"></i></div>
        <div class="stat-val" style="color:#ca8a04;"><?= $days_met_goal ?><span style="font-size:1rem;color:var(--muted);">/<?= $range ?></span></div>
        <div class="stat-lbl">วันที่ถึงเป้า</div>
      </div>

      <div class="stat-card">
        <div class="stat-icon" style="background:#eff6ff;border:1px solid #bfdbfe;"><i class="bi bi-fire" style="color: #ff5722;"></i></div>
        <div class="stat-val" style="color:#2563eb;"><?= number_format($max_day) ?></div>
        <div class="stat-lbl">สูงสุด (kcal)</div>
      </div>

      <div class="stat-card">
        <div class="stat-icon" style="background:#f0fdf4;border:1px solid var(--g200);"><i class="bi bi-check-square-fill" style="color: #22c55e"></i></div>
        <div class="stat-val" style="color:var(--t600);"><?= $consistency_pct ?><span style="font-size:1rem;color:var(--muted);">%</span></div>
        <div class="stat-lbl">ความสม่ำเสมอ (30 วัน)</div>
      </div>

    </div>

    <div class="rv rv3" style="display:grid;grid-template-columns:1.5fr 1fr;gap:18px;margin-bottom:2rem;">

      <div class="card" style="padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
          <h2 class="stitle"><i class="bi bi-graph-up" style="color: #22c55e;"></i> แคลอรี่ที่บันทึกรายวัน</h2>
          <div style="display:flex;gap:12px;font-size:.68rem;">
            <div style="display:flex;align-items:center;gap:5px;">
              <!-- ✅ สีฟ้าเทอร์ควอยซ์ -->
              <div style="width:10px;height:10px;border-radius:2px;background:linear-gradient(135deg, #5eead4, #14b8a6);"></div>
              <span style="color:var(--muted);">เมนูจาก AI</span>
            </div>
            <div style="display:flex;align-items:center;gap:5px;">
              <!-- ✅ สีเขียวสด -->
              <div style="width:10px;height:10px;border-radius:2px;background:linear-gradient(135deg, #4ade80, #22c55e);"></div>
              <span style="color:var(--muted);">เมนูทั่วไป</span>
            </div>
          </div>
        </div>

        <div style="display:flex;gap:4px;align-items:flex-end;height:160px;margin-bottom:12px;">
          <?php foreach ($daily_data as $dd):
            $ai_h  = $max_chart > 0 ? max(4, round($dd['ai_cal'] / $max_chart * 140)) : 4;
            $rec_h = $max_chart > 0 ? max(4, round($dd['rec_cal'] / $max_chart * 140)) : 4;
            $total_h = $ai_h + $rec_h;
          ?>
          <div class="chart-bar" title="<?= $dd['date_short'] ?>: <?= number_format($dd['total']) ?> kcal (AI: <?= number_format($dd['ai_cal']) ?>, ทั่วไป: <?= number_format($dd['rec_cal']) ?>)">
            <div class="bar-track">
              <?php if ($dd['rec_cal'] > 0): ?>
              <!-- ✅ เมนูทั่วไป: สีเขียวอ่อน #4ade80 -->
              <div class="bar-stack" style="height:<?= $rec_h ?>px;background:linear-gradient(180deg, #4ade80, #22c55e);border-radius:0 0 3px 3px;"></div>
              <?php endif; ?>
              
              <?php if ($dd['ai_cal'] > 0): ?>
              <!-- ✅ เมนูจาก AI: สีฟ้าเทอร์ควอยซ์ #14b8a6 -->
              <div class="bar-stack" style="height:<?= $ai_h ?>px;background:linear-gradient(180deg, #5eead4, #14b8a6);border-radius:<?= $dd['rec_cal'] > 0 ? '0' : '0 0 3px 3px' ?>;"></div>
              <?php endif; ?>
            </div>
            <span class="bar-lbl"><?= date('j/n', strtotime($dd['date'])) ?></span>
          </div>
          <?php endforeach; ?>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--bdr);padding-top:14px;">
          <span style="font-size:.7rem;color:var(--muted);">เป้าหมายรายวัน: <strong style="color:var(--g600);"><?= number_format($targetCal) ?> kcal</strong></span>
          <span style="font-size:.7rem;color:var(--muted);">รวม <?= $days_with_records ?> วันที่บันทึก: <strong style="color:var(--txt);"><?= number_format($total_cal_period) ?> kcal</strong></span>
        </div>
      </div>

      <div class="card" style="padding:24px;">
        <h2 class="stitle" style="margin-bottom:18px;"><i class="fas fa-utensils" style="color: #22c55e;"></i> สัดส่วนมื้ออาหาร</h2>
        <p style="font-size:.7rem;color:var(--muted);margin-bottom:16px;">7 วันที่ผ่านมา</p>

        <?php
        $meal_colors = [
          'breakfast' => '#f59e0b',
          'lunch'     => '#22c55e',
          'dinner'    => '#6366f1',
          'snack'     => '#f97316'
        ];
        $meal_labels = [
          'breakfast' => 'มื้อเช้า',
          'lunch'     => 'มื้อกลางวัน',
          'dinner'    => 'มื้อเย็น',
          'snack'     => 'ของว่าง'
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
                $dash = $a['pct'] / 100 * $circumference;
                $gap  = $circumference - $dash;
                $rotate = $a['start'];
            ?>
            <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>"
                    fill="none" stroke="<?= $meal_colors[$mt] ?>" stroke-width="<?= $stroke ?>"
                    stroke-dasharray="<?= $dash ?> <?= $gap ?>"
                    transform="rotate(<?= $rotate ?> <?= $cx ?> <?= $cy ?>)"
                    style="transition:stroke-dasharray .6s ease;"/>
            <?php endforeach; ?>
          </svg>
          <div class="donut-center">
            <span style="font-family:'Nunito',sans-serif;font-size:1.4rem;font-weight:800;color:var(--txt);line-height:1;">
              <?= number_format($total_meal_cal) ?>
            </span>
            <span style="font-size:.6rem;color:var(--muted);margin-top:2px;">kcal รวม</span>
          </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:9px;margin-top:18px;">
          <?php foreach ($meal_types as $mt => $cal):
            if ($cal <= 0) continue;
            $pct = $total_meal_cal > 0 ? round($cal / $total_meal_cal * 100) : 0;
          ?>
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <div class="legend-item">
              <span class="legend-dot" style="background:<?= $meal_colors[$mt] ?>;"></span>
              <span><?= $meal_labels[$mt] ?></span>
            </div>
            <span style="font-size:.75rem;font-weight:700;color:var(--txt);"><?= $pct ?>%</span>
          </div>
          <?php endforeach; ?>
          <?php if ($total_meal_cal === 0): ?>
          <p style="text-align:center;font-size:.78rem;color:var(--muted);padding:1rem 0;">ยังไม่มีข้อมูล</p>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <div class="rv rv4" style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:2rem;">

      <div class="card" style="padding:24px;">
        <h2 class="stitle" style="margin-bottom:18px;">💡 คำแนะนำสุขภาพ</h2>
        <div style="display:flex;flex-direction:column;gap:12px;">
          <?php foreach ($recommendations as $rec): ?>
          <div class="rec-card" style="border-color:<?= $rec['color'] ?>;">
            <div style="display:flex;align-items:flex-start;gap:12px;">
              <span style="font-size:1.8rem;flex-shrink:0;"><?= $rec['icon'] ?></span>
              <div style="flex:1;">
                <div style="font-size:.88rem;font-weight:700;color:var(--txt);margin-bottom:3px;"><?= $rec['title'] ?></div>
                <div style="font-size:.75rem;color:var(--muted);line-height:1.6;"><?= $rec['text'] ?></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>

          <div style="background:var(--g50);border:1px solid var(--g200);border-radius:13px;padding:14px;margin-top:6px;">
            <div style="display:flex;align-items:center;gap:9px;margin-bottom:7px;">
              <i class="fas fa-info-circle" style="color:var(--g600);font-size:.9rem;"></i>
              <span style="font-size:.78rem;font-weight:700;color:var(--g700);">ข้อมูลสุขภาพของคุณ</span>
            </div>
            <div style="font-size:.72rem;color:var(--sub);line-height:1.65;">
              <strong>โรคประจำตัว:</strong> <?= htmlspecialchars($conditions) ?><br>
              <strong>รูปแบบการกิน:</strong> <?= htmlspecialchars($dietType) ?>
            </div>
          </div>
        </div>
      </div>

      <div class="card" style="padding:24px;">
        <h2 class="stitle" style="margin-bottom:18px;"><i class="bi bi-fire" style="color: #ff5722;"></i> เมนูที่บันทึกบ่อย</h2>
        <p style="font-size:.7rem;color:var(--muted);margin-bottom:14px;">30 วันที่ผ่านมา</p>

        <?php if (count($top_recipes) > 0): ?>
        <div style="display:flex;flex-direction:column;gap:9px;">
          <?php foreach ($top_recipes as $idx => $tr): ?>
          <a href="recipe_detail.php?id=<?= $tr['id'] ?>" class="top-row" style="text-decoration:none;">
            <span style="font-family:'Nunito',sans-serif;font-size:1.1rem;font-weight:800;color:var(--g600);min-width:26px;">
              #<?= $idx + 1 ?>
            </span>
            <div style="flex:1;min-width:0;">
              <div style="font-size:.83rem;font-weight:600;color:var(--txt);line-height:1.4;">
                <?= htmlspecialchars($tr['title']) ?>
              </div>
              <div style="font-size:.68rem;color:var(--muted);margin-top:2px;">
                บันทึก <?= $tr['log_count'] ?> ครั้ง • <?= number_format($tr['total_cal']) ?> kcal รวม
              </div>
            </div>
            <i class="fas fa-chevron-right" style="color:var(--muted);font-size:.7rem;"></i>
          </a>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:2rem 1rem;border:2px dashed var(--g200);border-radius:14px;">
          <div style="font-size:2rem;opacity:.2;margin-bottom:10px;"><i class="bi bi-bar-chart-line-fill" style="color: #22c55e;"></i></div>
          <p style="font-size:.82rem;color:var(--muted);">ยังไม่มีข้อมูลสถิติ</p>
          <p style="font-size:.72rem;color:var(--muted);margin-top:4px;">เริ่มบันทึกเมนูอาหารของคุณ</p>
        </div>
        <?php endif; ?>
      </div>

    </div>

    <div class="rv rv5 card" style="padding:28px;">
      <h2 class="stitle" style="margin-bottom:18px;"><i class="fas fa-clipboard-list" style="color: #22c55e;"></i> สรุปภาพรวม</h2>
      
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:18px;">
        
        <div style="background:var(--g50);border-radius:14px;padding:18px;">
          <div style="font-size:.7rem;color:var(--muted);margin-bottom:8px;font-weight:600;letter-spacing:.04em;">CONSISTENCY SCORE</div>
          <div style="display:flex;align-items:baseline;gap:6px;margin-bottom:10px;">
            <span style="font-family:'Nunito',sans-serif;font-size:2.2rem;font-weight:800;color:var(--g600);line-height:1;"><?= $consistency_pct ?></span>
            <span style="font-size:.9rem;color:var(--muted);">%</span>
          </div>
          <div style="height:6px;background:rgba(34,197,94,.15);border-radius:99px;overflow:hidden;">
            <div style="width:<?= $consistency_pct ?>%;height:100%;background:var(--g500);border-radius:99px;"></div>
          </div>
          <p style="font-size:.7rem;color:var(--sub);margin-top:10px;line-height:1.55;">
            <?php if ($consistency_pct >= 80): ?>
            เยี่ยมมาก! คุณบันทึกอย่างสม่ำเสมอ
            <?php elseif ($consistency_pct >= 50): ?>
            ดีมาก ลองบันทึกให้ได้ทุกวันนะ
            <?php else: ?>
            ลองบันทึกให้สม่ำเสมอมากขึ้น
            <?php endif; ?>
          </p>
        </div>

        <div style="background:#fefce8;border-radius:14px;padding:18px;">
          <div style="font-size:.7rem;color:#a16207;margin-bottom:8px;font-weight:600;letter-spacing:.04em;">GOAL ACHIEVEMENT</div>
          <div style="display:flex;align-items:baseline;gap:6px;margin-bottom:10px;">
            <span style="font-family:'Nunito',sans-serif;font-size:2.2rem;font-weight:800;color:#ca8a04;line-height:1;"><?= $days_met_goal ?></span>
            <span style="font-size:.9rem;color:#a16207;">/ <?= $range ?> วัน</span>
          </div>
          <div style="height:6px;background:rgba(202,138,4,.15);border-radius:99px;overflow:hidden;">
            <div style="width:<?= $range>0?round($days_met_goal/$range*100):0 ?>%;height:100%;background:#eab308;border-radius:99px;"></div>
          </div>
          <p style="font-size:.7rem;color:#854d0e;margin-top:10px;line-height:1.55;">
            <?php if ($days_met_goal >= $range * 0.8): ?>
            สุดยอด! ถึงเป้าหมายเกือบทุกวัน
            <?php else: ?>
            มุ่งมั่นต่อไปนะ เป้าหมายใกล้แล้ว
            <?php endif; ?>
          </p>
        </div>

        <div style="background:#eff6ff;border-radius:14px;padding:18px;">
          <div style="font-size:.7rem;color:#1e40af;margin-bottom:8px;font-weight:600;letter-spacing:.04em;">AVG CALORIES/DAY</div>
          <div style="display:flex;align-items:baseline;gap:6px;margin-bottom:10px;">
            <span style="font-family:'Nunito',sans-serif;font-size:2.2rem;font-weight:800;color:#2563eb;line-height:1;"><?= number_format($avg_cal_day) ?></span>
            <span style="font-size:.9rem;color:#1e40af;">kcal</span>
          </div>
          <div style="height:6px;background:rgba(37,99,235,.15);border-radius:99px;overflow:hidden;">
            <div style="width:<?= $targetCal>0?min(100,round($avg_cal_day/$targetCal*100)):0 ?>%;height:100%;background:#3b82f6;border-radius:99px;"></div>
          </div>
          <p style="font-size:.7rem;color:#1e3a8a;margin-top:10px;line-height:1.55;">
            เป้าหมาย <?= number_format($targetCal) ?> kcal/วัน
          </p>
        </div>

      </div>
    </div>

  </main>
</div>

</body>
</html>