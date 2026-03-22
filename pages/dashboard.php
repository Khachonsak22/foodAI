<?php
session_start();
include '../config/connect.php';

date_default_timezone_set('Asia/Bangkok');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ═══ HANDLE WATER INTAKE LOG ═══
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_water') {
    $amount_ml = (int)($_POST['amount_ml'] ?? 250);
    $log_date = date('Y-m-d');
    
    $check_stmt = $conn->prepare("SELECT id, total_ml FROM water_logs WHERE user_id = ? AND log_date = ?");
    $check_stmt->bind_param("is", $user_id, $log_date);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        $new_total = $existing['total_ml'] + $amount_ml;
        $update_stmt = $conn->prepare("UPDATE water_logs SET total_ml = ? WHERE id = ?");
        $update_stmt->bind_param("ii", $new_total, $existing['id']);
        $update_stmt->execute();
    } else {
        $insert_stmt = $conn->prepare("INSERT INTO water_logs (user_id, log_date, total_ml) VALUES (?, ?, ?)");
        $insert_stmt->bind_param("isi", $user_id, $log_date, $amount_ml);
        $insert_stmt->execute();
    }
    
    header("Location: dashboard.php");
    exit();
}

// ✅ อัปเดต SQL ให้ดึงชื่อเป้าหมายจากตาราง goals มาด้วย
$sql = "SELECT u.first_name, u.last_name,
               COALESCE(h.daily_calorie_target, 2000) as daily_calorie_target,
               COALESCE(h.dietary_type, 'ทั่วไป') as dietary_type,
               COALESCE(h.health_conditions, 'ไม่มี') as health_conditions,
               COALESCE(g.title, 'ไม่ระบุเป้าหมาย') as goal_title
        FROM users u
        LEFT JOIN health_profiles h ON u.id = h.user_id
        LEFT JOIN goals g ON h.goal_preference = g.goal_key
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

$firstName  = $user_data['first_name'] ?? "Guest";
$lastName   = $user_data['last_name']  ?? "";
$targetCal  = $user_data['daily_calorie_target'];
$goalTitle  = $user_data['goal_title']; // เก็บชื่อเป้าหมาย
$dietType   = $user_data['dietary_type'];
$conditions = $user_data['health_conditions'];
if ($conditions === '0' || empty($conditions)) $conditions = "ไม่มีระบุ";

$today       = date('Y-m-d');

// 1. แคลอรี่ที่กินจริง
$cal_sql     = "SELECT SUM(r.calories) as total_cal 
                FROM meal_logs ml
                INNER JOIN recipes r ON ml.recipe_id = r.id
                WHERE ml.user_id = ? AND DATE(ml.logged_at) = ?";
$stmt_cal    = $conn->prepare($cal_sql);
$stmt_cal->bind_param("is", $user_id, $today);
$stmt_cal->execute();
$cal_result  = $stmt_cal->get_result()->fetch_assoc();
$consumedCal = $cal_result['total_cal'] ?? 0;

// ✅ ปรับการคำนวณ % และหาค่าที่เกินเป้าหมาย (Over Calorie)
$calPercentRaw = ($targetCal > 0) ? ($consumedCal / $targetCal) * 100 : 0;
$calPercent    = min(100, $calPercentRaw); 
$barWidth      = min($calPercentRaw, 100);
$remaining     = max(0, $targetCal - $consumedCal);
$over          = max(0, $consumedCal - $targetCal); // ค่าที่เกิน
$ring_offset   = round(314 * (1 - min($calPercentRaw, 100) / 100));

// Water intake
$water_sql = "SELECT total_ml FROM water_logs WHERE user_id = ? AND log_date = ?";
$water_stmt = $conn->prepare($water_sql);
$water_stmt->bind_param("is", $user_id, $today);
$water_stmt->execute();
$water_res = $water_stmt->get_result()->fetch_assoc();
$water_total = $water_res['total_ml'] ?? 0;
$water_target = 2000;
$water_percent = min(100, ($water_total / $water_target) * 100);
$water_glasses = floor($water_total / 250);

// News
$news_sql    = "SELECT * FROM news WHERE DATE(created_at) = CURDATE() ORDER BY created_at DESC LIMIT 1";
$news_result = $conn->query($news_sql);
$daily_news  = null;
if ($news_result && $news_result->num_rows > 0) {
    $daily_news = $news_result->fetch_assoc();
} else {
    $fb = $conn->query("SELECT * FROM news ORDER BY created_at DESC LIMIT 1");
    if ($fb && $fb->num_rows > 0) $daily_news = $fb->fetch_assoc();
}

// 2. ดึงข้อมูลเมนูเข้าชมมากสุด Top 3
$most_viewed_sql = "SELECT r.id, r.title, r.description, r.calories, r.view_count
                    FROM recipes r
                    ORDER BY r.view_count DESC
                    LIMIT 3";
$most_viewed_result = $conn->query($most_viewed_sql);
$most_viewed_menus = [];
if ($most_viewed_result) while ($row = $most_viewed_result->fetch_assoc()) $most_viewed_menus[] = $row;

// 2. ดึงข้อมูลเมนูถูกใจมากสุด Top 3
$most_favorited_sql = "SELECT r.id, r.title, r.description, r.calories,
                              COUNT(ui.id) as fav_count
                       FROM recipes r
                       INNER JOIN user_interactions ui ON r.id = ui.recipe_id AND ui.interaction_type = 'favorite'
                       GROUP BY r.id
                       ORDER BY fav_count DESC
                       LIMIT 3";
$most_favorited_result = $conn->query($most_favorited_sql);
$most_favorited_menus = [];
if ($most_favorited_result) while ($row = $most_favorited_result->fetch_assoc()) $most_favorited_menus[] = $row;

// Today menus (จาก AI เหมือนเดิม)
$menu_sql = "SELECT asm.*, r.id as recipe_id 
             FROM ai_saved_menus asm
             LEFT JOIN recipes r ON asm.menu_name = r.title
             WHERE asm.user_id = ? AND DATE(asm.created_at) = ? 
             ORDER BY asm.created_at DESC";
$stmt_m   = $conn->prepare($menu_sql);
$stmt_m->bind_param("is", $user_id, $today);
$stmt_m->execute();
$menu_res   = $stmt_m->get_result();
$menu_count = $menu_res->num_rows;

$initials    = mb_strtoupper(mb_substr($firstName, 0, 1)) . mb_strtoupper(mb_substr($lastName, 0, 1));

// Current page for nav highlight
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — FoodAI</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
/* ════════════════════════════════════════
   DESIGN TOKENS — Health Green Palette
════════════════════════════════════════ */
:root {
  /* Greens */
  --g50:  #f0fdf4;
  --g100: #dcfce7;
  --g200: #bbf7d0;
  --g400: #4ade80;
  --g500: #22c55e;
  --g600: #16a34a;
  --g700: #15803d;

  /* Teals / Mint */
  --t400: #2dd4bf;
  --t500: #14b8a6;
  --t600: #0d9488;

  /* Neutrals */
  --bg:   #f5f8f5;
  --card: #ffffff;
  --bdr:  #e8f0e9;
  --txt:  #1a2e1a;
  --sub:  #4b6b4e;
  --muted:#8da98f;

  /* Accents */
  --orange: #f97316;
  --sky:    #38bdf8;

  /* Sidebar */
  --sb-bg:    #ffffff;
  --sb-w:     248px;
  --sb-act:   #f0fdf4;
  --sb-bdr:   #e5ede6;
}

*, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }

body {
  font-family: 'Kanit', sans-serif;
  background: var(--bg);
  color: var(--txt);
  min-height: 100vh;
  display: flex;
}

/* Subtle dot-grid background */
body::before {
  content: '';
  position: fixed; inset: 0; pointer-events: none; z-index: 0;
  background-image: radial-gradient(circle, #c8e6c9 1px, transparent 1px);
  background-size: 28px 28px;
  opacity: 0.38;
}

/* ════════════════════════════════════════
   RESPONSIVE ADJUSTMENTS
════════════════════════════════════════ */

/* สำหรับ Tablet และมือถือ (หน้าจอเล็กกว่า 1024px) */
@media (max-width: 1024px) {
  .sidebar {
    transform: translateX(-100%); /* ซ่อน Sidebar ไว้ด้านซ้าย */
    transition: transform 0.3s ease;
  }
  .page-wrap {
    margin-left: 0 !important; /* ให้เนื้อหาหลักเต็มหน้าจอ */
  }
  
  /* ปรับ Row 1 (Stat Cards) จาก 3 คอลัมน์ เป็น 1 คอลัมน์ */
  .rv2 {
    grid-template-columns: 1fr !important;
  }
  
  /* ปรับ Row 2 (Content) จาก 2 คอลัมน์ เป็น 1 คอลัมน์ */
  .rv3 {
    grid-template-columns: 1fr !important;
  }
}

/* สำหรับมือถือขนาดเล็ก (หน้าจอเล็กกว่า 768px) */
@media (max-width: 768px) {
  /* ปรับเมนูมาแรงจาก 3 คอลัมน์ เป็น 1 คอลัมน์ */
  .rv3 div div div[style*="grid-template-columns"] {
    grid-template-columns: 1fr !important;
  }
  
  main {
    padding: 1rem !important; /* ลดช่องว่างขอบจอ */
  }
  
  .hero-title {
    font-size: 1.4rem !important; /* ลดขนาดตัวอักษรหัวข้อ */
  }
}

/* ════════════════════════════════════════
   SIDEBAR / NAVBAR
════════════════════════════════════════ */
.sidebar {
  width: var(--sb-w);
  min-height: 100vh;
  background: var(--sb-bg);
  border-right: 1px solid var(--sb-bdr);
  display: flex;
  flex-direction: column;
  position: fixed; left: 0; top: 0; bottom: 0;
  z-index: 100;
  box-shadow: 4px 0 24px rgba(34,197,94,0.06);
}

/* Logo area */
.sb-logo {
  padding: 24px 22px 20px;
  border-bottom: 1px solid var(--sb-bdr);
  display: flex; align-items: center; gap: 11px;
}
.sb-logo-icon {
  width: 40px; height: 40px; border-radius: 12px;
  background: linear-gradient(135deg, var(--g500), var(--t500));
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem; box-shadow: 0 4px 12px rgba(34,197,94,0.35);
  flex-shrink: 0;
}
.sb-logo-text {
  font-family: 'Nunito', sans-serif;
  font-size: 1.18rem; font-weight: 800;
  color: var(--g700); letter-spacing: -.02em;
  line-height: 1;
}
.sb-logo-sub {
  font-size: .6rem; font-weight: 600;
  color: var(--muted); letter-spacing: .08em;
  text-transform: uppercase; margin-top: 2px;
}

/* Nav section label */
.sb-label {
  font-size: .6rem; font-weight: 700;
  letter-spacing: .12em; text-transform: uppercase;
  color: var(--muted);
  padding: 18px 22px 8px;
}

/* Nav item */
.sb-nav { padding: 6px 12px; display: flex; flex-direction: column; gap: 2px; flex:1; }

.nav-item {
  display: flex; align-items: center; gap: 11px;
  padding: 10px 12px; border-radius: 12px;
  text-decoration: none; color: var(--sub);
  font-size: .82rem; font-weight: 500;
  transition: background .18s, color .18s, transform .18s;
  position: relative;
  cursor: pointer;
}
.nav-item:hover {
  background: var(--g50);
  color: var(--g700);
  transform: translateX(2px);
}
.nav-item.active {
  background: var(--g50);
  color: var(--g600);
  font-weight: 600;
  box-shadow: inset 3px 0 0 var(--g500);
}
.nav-item.active .nav-icon-wrap {
  background: linear-gradient(135deg, var(--g500), var(--t500));
  color: white;
  box-shadow: 0 3px 10px rgba(34,197,94,0.38);
}
.nav-icon-wrap {
  width: 34px; height: 34px; border-radius: 10px;
  background: var(--g50); border: 1px solid var(--bdr);
  display: flex; align-items: center; justify-content: center;
  font-size: .8rem; flex-shrink: 0;
  transition: background .18s, box-shadow .18s;
  color: var(--g600);
}
.nav-item:hover .nav-icon-wrap:not(.active *) {
  background: var(--g100);
  border-color: var(--g200);
}
.nav-badge {
  margin-left: auto;
  background: var(--g500); color: white;
  font-size: .6rem; font-weight: 700;
  padding: 2px 7px; border-radius: 99px;
  min-width: 20px; text-align: center;
}
.nav-badge.orange { background: var(--orange); }

/* Divider */
.sb-divider { height: 1px; background: var(--sb-bdr); margin: 6px 12px; }

/* User profile at bottom */
.sb-user {
  border-top: 1px solid var(--sb-bdr);
  padding: 16px;
  display: flex; align-items: center; gap: 11px;
  background: var(--g50);
}
.sb-avatar {
  width: 38px; height: 38px; border-radius: 50%;
  background: linear-gradient(135deg, var(--g400), var(--t400));
  display: flex; align-items: center; justify-content: center;
  font-size: .82rem; font-weight: 800; color: white;
  flex-shrink: 0; font-family: 'Nunito', sans-serif;
  box-shadow: 0 2px 8px rgba(34,197,94,0.3);
}
.sb-user-name { font-size: .78rem; font-weight: 600; color: var(--txt); line-height: 1.2; }
.sb-user-role { font-size: .62rem; color: var(--muted); margin-top: 1px; }
.sb-logout {
  margin-left: auto; width: 30px; height: 30px; border-radius: 8px;
  background: transparent; border: 1px solid var(--bdr);
  display: flex; align-items: center; justify-content: center;
  color: var(--muted); font-size: .72rem; cursor: pointer;
  transition: background .18s, color .18s, border-color .18s;
  text-decoration: none;
}
.sb-logout:hover { background: #fee2e2; border-color: #fecaca; color: #dc2626; }

/* ════════════════════════════════════════
   PAGE SHELL
════════════════════════════════════════ */
.page-wrap {
  margin-left: var(--sb-w);
  flex: 1; display: flex; flex-direction: column;
  position: relative; z-index: 1; min-width: 0;
}

/* Topbar */
.topbar {
  height: 62px;
  background: rgba(255,255,255,0.88);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--sb-bdr);
  display: flex; align-items: center;
  padding: 0 2.5rem;
  gap: 14px;
  position: sticky; top: 0; z-index: 50;
}
.topbar-search {
  flex: 1; max-width: 600px;
  background: var(--bg); border: 1px solid var(--bdr);
  border-radius: 12px; display: flex; align-items: center; gap: 9px;
  padding: 0 14px; height: 38px;
  transition: border-color .18s, box-shadow .18s;
}
.topbar-search:focus-within {
  border-color: var(--g400); box-shadow: 0 0 0 3px rgba(74,222,128,.12);
}
.topbar-search input {
  border: none; outline: none; background: transparent;
  font-family: 'Kanit', sans-serif; font-size: .8rem; color: var(--txt);
  width: 100%;
}
.topbar-search input::placeholder { color: var(--muted); }
.tb-icon-btn {
  width: 38px; height: 38px; border-radius: 11px;
  background: white; border: 1px solid var(--bdr);
  display: flex; align-items: center; justify-content: center;
  color: var(--sub); font-size: .8rem; cursor: pointer;
  transition: background .18s, border-color .18s;
  text-decoration: none; position: relative;
}
.tb-icon-btn:hover { background: var(--g50); border-color: var(--g200); color: var(--g600); }
.tb-notif-dot {
  position: absolute; top: 6px; right: 6px;
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--orange); border: 2px solid white;
}
.tb-date {
  font-size: .72rem; color: var(--muted); font-weight: 400;
  display: flex; align-items: center; gap: 6px;
  margin-left: auto;
  white-space: nowrap;
}

/* ปรับให้ขยายตามหน้าจอ แต่ยังคงมีระยะห่างขอบ (Padding) เพื่อความสวยงาม */
main { 
    padding: 2rem 2.5rem 3.5rem; 
    width: 100%; 
    max-width: 100%; 
    margin: 0 auto; 
}

/* ════════════════════════════════════════
   ANIMATIONS
════════════════════════════════════════ */
@keyframes slideUp {
  from { opacity:0; transform:translateY(20px); }
  to   { opacity:1; transform:translateY(0); }
}
.rv  { opacity:0; animation: slideUp 0.5s cubic-bezier(.22,1,.36,1) forwards; }
.rv1 { animation-delay:.04s; }
.rv2 { animation-delay:.10s; }
.rv3 { animation-delay:.17s; }
.rv4 { animation-delay:.24s; }
.rv5 { animation-delay:.31s; }
.rv6 { animation-delay:.38s; }

@keyframes flameDance { 0%,100%{transform:rotate(-3deg) scale(1);} 50%{transform:rotate(4deg) scale(1.12);} }
.flame { display:inline-block; animation:flameDance 1.9s ease-in-out infinite; }

@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1);} 50%{opacity:.3;transform:scale(.6);} }
.ldot { display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--g500);animation:pulse-dot 1.5s ease-in-out infinite; }

/* ════════════════════════════════════════
   CARDS
════════════════════════════════════════ */
.card {
  background: var(--card);
  border: 1px solid var(--bdr);
  border-radius: 20px;
  transition: transform .22s, box-shadow .22s, border-color .22s;
}
.card:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 32px rgba(34,197,94,0.1);
  border-color: var(--g200);
}

/* Hero calorie card */
.cal-card {
  background: linear-gradient(135deg, var(--g500), var(--t500));
  border-radius: 20px; position: relative; overflow: hidden;
  transition: transform .22s, box-shadow .22s;
  box-shadow: 0 12px 48px rgba(34,197,94,0.28);
}
.cal-card:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(22,163,74,0.35); }
.cal-card::before {
  content: '';
  position: absolute; inset: 0;
  background:
    radial-gradient(ellipse 70% 60% at 15% 30%, rgba(21, 128, 61, 0.5) 0%, transparent 55%),
    radial-gradient(ellipse 50% 50% at 90% 85%, rgba(13, 148, 136, 0.4) 0%, transparent 55%);
}
.cal-card-inner { position: relative; z-index: 1; padding: 28px; }

/* Ring */
.ring-bg   { fill:none; stroke:rgba(255,255,255,.12); stroke-width:9; }
.ring-fill {
  fill:none; stroke-width:9; stroke-linecap:round;
  stroke-dasharray: 314;
  stroke-dashoffset: <?php echo $ring_offset; ?>;
  transition: stroke-dashoffset 1.2s cubic-bezier(.4,0,.2,1);
}
.ring-wrap { position:relative; width:116px; height:116px; flex-shrink:0; }
.ring-wrap svg { transform: rotate(-90deg); }
.ring-center { position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center; }

/* Progress */
.pbar { height:7px; background:rgba(255,255,255,.12); border-radius:99px; overflow:hidden; }
.pbar-fill {
  height:100%; border-radius:99px;
  transition: width 1.2s cubic-bezier(.4,0,.2,1);
}
.pbar-green { background: linear-gradient(90deg, var(--g500), var(--g400)); box-shadow:0 0 8px rgba(34,197,94,.4); }

/* Menu row */
.menu-row {
  background: white; border: 1px solid var(--bdr); border-radius: 14px;
  padding: 13px 15px; display: flex; align-items: flex-start; gap: 12px;
  transition: border-color .2s, box-shadow .2s, transform .2s;
}
.menu-row:hover { border-color: var(--g200); box-shadow: 0 4px 16px rgba(34,197,94,0.08); transform: translateX(2px); }
.menu-dot { width:9px;height:9px;border-radius:50%;background:var(--g500);margin-top:6px;flex-shrink:0;box-shadow:0 0 6px rgba(34,197,94,.5); }

/* Scroll container */
.scroll-box { display:flex;flex-direction:column;gap:9px;max-height:360px;overflow-y:auto;padding-right:3px; }
::-webkit-scrollbar { width:4px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:var(--g200);border-radius:99px; }

/* Badge */
.badge-green {
  background: var(--g50); border: 1px solid var(--g200);
  color: var(--g700); font-size:.65rem; font-weight:600;
  padding:3px 10px; border-radius:99px; white-space:nowrap;
}
.badge-orange {
  background:#fff7ed; border:1px solid #fed7aa;
  color:#c2410c; font-size:.65rem; font-weight:600;
  padding:3px 10px; border-radius:99px; white-space:nowrap;
}
.badge-sky {
  background:#f0f9ff; border:1px solid #bae6fd;
  color:#0369a1; font-size:.65rem; font-weight:600;
  padding:3px 10px; border-radius:99px; white-space:nowrap;
}

/* Water drop */
.wdrop { width:27px;height:32px;border-radius:50% 50% 50% 50%/60% 60% 40% 40%;transition:all .25s; }
.wdrop.on  { background:linear-gradient(160deg,#7dd3fc,#38bdf8);box-shadow:0 0 8px rgba(56,189,248,.3); }
.wdrop.off { background:var(--g50);border:1.5px solid var(--bdr); }

/* Section title */
.stitle { font-family:'Nunito',sans-serif;font-size:1.05rem;font-weight:800;color:var(--txt);display:flex;align-items:center;gap:8px; }

/* Green line */
.gline { height:3px;background:linear-gradient(90deg,var(--g500),transparent);border-radius:99px; }

/* Stat pill */
.stat-pill {
  background:var(--g50);border:1px solid var(--bdr);border-radius:14px;
  padding:11px 14px;display:flex;gap:11px;align-items:center;
}
.stat-icon {
  width:34px;height:34px;border-radius:10px;display:flex;align-items:center;
  justify-content:center;font-size:.8rem;flex-shrink:0;
}

/* Trending card */
.tcard {
  background:white;border:1.5px solid var(--bdr);border-radius:18px;
  padding:18px;display:flex;flex-direction:column;gap:12px;
  transition:border-color .22s,box-shadow .22s,transform .22s;cursor:pointer;
}
.tcard:hover { transform:translateY(-4px);box-shadow:0 12px 28px rgba(34,197,94,0.12); }

/* Rank stripes */
.tcard.r1 { border-color:#fbbf24; }
.tcard.r1:hover { border-color:#f59e0b;box-shadow:0 12px 28px rgba(251,191,36,.18); }
.tcard.r2 { border-color:#cbd5e1; }
.tcard.r2:hover { border-color:#94a3b8;box-shadow:0 12px 28px rgba(148,163,184,.15); }
.tcard.r3 { border-color:#fb923c; }
.tcard.r3:hover { border-color:#f97316;box-shadow:0 12px 28px rgba(251,146,60,.15); }

/* Buttons */
.btn-green {
  background:linear-gradient(135deg,var(--g500),var(--t500));
  color:white;font-size:.72rem;font-weight:700;
  padding:8px 18px;border-radius:11px;
  display:inline-flex;align-items:center;gap:7px;
  text-decoration:none;font-family:'Kanit',sans-serif;
  transition:opacity .2s,box-shadow .2s;letter-spacing:.02em;
}
.btn-green:hover { opacity:.88;box-shadow:0 6px 18px rgba(34,197,94,.38); }

.btn-ghost {
  border:1.5px solid var(--bdr);color:var(--sub);
  font-size:.7rem;font-weight:500;padding:7px 14px;border-radius:11px;
  display:inline-flex;align-items:center;gap:6px;
  text-decoration:none;font-family:'Kanit',sans-serif;
  transition:border-color .18s,color .18s,background .18s;
}
.btn-ghost:hover { border-color:var(--g400);color:var(--g700);background:var(--g50); }

/* News image */
.news-img { height:132px;overflow:hidden;border-radius:13px;position:relative; }
.news-img img { width:100%;height:100%;object-fit:cover;transition:transform .45s; }
.news-img:hover img { transform:scale(1.05); }
.news-img::after { content:'';position:absolute;inset:0;border-radius:13px;background:linear-gradient(to top,rgba(0,0,0,.35),transparent); }

/* Empty state */
.empty { border:2px dashed var(--g200);border-radius:16px;text-align:center;padding:3rem 1.5rem;color:var(--muted); }

/* Tip card */
.tip-card {
  background:linear-gradient(140deg,#16a34a,#16a34a);
  border-radius:20px;padding:24px;position:relative;overflow:hidden;
  transition:transform .22s,box-shadow .22s;
}
.tip-card:hover { transform:translateY(-3px);box-shadow:0 14px 36px rgba(22,163,74,.25); }
.tip-card::before {
  content:'';position:absolute;inset:0;
  background:radial-gradient(ellipse 70% 60% at 10% 20%,rgba(74,222,128,.15) 0%,transparent 60%);
}
.tip-inner { position:relative;z-index:1; }

/* ════════════════════════════════════════
   RESPONSIVE DESIGN
════════════════════════════════════════ */

/* สำหรับปุ่มเมนูบนมือถือ */
.menu-toggle {
  display: none;
  width: 38px; height: 38px; border-radius: 11px;
  background: white; border: 1px solid var(--bdr);
  align-items: center; justify-content: center;
  color: var(--sub); font-size: 0.9rem; cursor: pointer;
}

@media (max-width: 1024px) {
  .sidebar {
    transform: translateX(-100%); /* ซ่อน Sidebar ออกไปด้านซ้าย */
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .sidebar.show {
    transform: translateX(0); /* แสดง Sidebar เมื่อกดปุ่ม */
  }
  .page-wrap {
    margin-left: 0 !important; /* ยกเลิกการเว้นระยะขอบซ้าย */
  }
  .menu-toggle {
    display: flex; /* แสดงปุ่มเมนูบนหน้าจอขนาดเล็ก */
  }
  .topbar {
    padding: 0 1.5rem; /* ลดระยะขอบ Topbar */
  }
  /* ปรับ Row 1 (Stat Cards) เป็น 1 คอลัมน์ */
  .rv2 {
    grid-template-columns: 1fr !important;
  }
}

@media (max-width: 768px) {
  /* ปรับเมนูมาแรง (Trending) จาก 3 คอลัมน์ เป็น 1 คอลัมน์ */
  .rv3 div div[style*="grid-template-columns"] {
    grid-template-columns: 1fr !important;
  }
  .topbar-search {
    display: none; /* ซ่อนช่องค้นหาบนมือถือเพื่อประหยัดพื้นที่ */
  }
  .hero-title {
    font-size: 1.4rem !important; /* ลดขนาดหัวข้อแคลอรี่ */
  }
  .cal-card-inner {
    padding: 20px;
  }
}

.ai-modal-overlay {
    position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4); 
    backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
    z-index: 9999; display: none; align-items: center; justify-content: center;
    opacity: 0; transition: opacity 0.4s ease;
}
.ai-modal-card {
    background: #ffffff; border-radius: 28px; width: 90%; max-width: 400px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    overflow: hidden; position: relative;
    transform: scale(0.95) translateY(20px); opacity: 0;
    transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
.ai-modal-overlay.show { opacity: 1; display: flex; }
.ai-modal-overlay.show .ai-modal-card { transform: scale(1) translateY(0); opacity: 1; }

.ai-modal-header {
    background: linear-gradient(135deg, var(--g50), var(--g100));
    padding: 40px 20px 30px; text-align: center; position: relative;
}
.ai-modal-close {
    position: absolute; top: 16px; right: 16px; width: 32px; height: 32px;
    background: #ffffff; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    color: #64748b; font-size: 0.85rem; cursor: pointer; border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: all 0.2s ease;
}
.ai-modal-close:hover { background: #fef2f2; color: #dc2626; transform: scale(1.05); }

.ai-icon-wrapper {
    width: 80px; height: 80px; background: #ffffff; border-radius: 24px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 2.5rem; color: var(--g500); margin: 0 auto;
    box-shadow: 0 12px 24px -6px rgba(34, 197, 94, 0.25);
    transform: rotate(-5deg); transition: transform 0.3s ease;
}
.ai-modal-card:hover .ai-icon-wrapper { transform: rotate(0deg) scale(1.05); }

.ai-modal-badge {
    position: absolute; top: 20px; left: 20px; background: #ffffff;
    padding: 5px 12px; border-radius: 12px; font-size: 0.65rem; font-weight: 800;
    color: var(--t500); letter-spacing: 0.5px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    display: flex; align-items: center; gap: 4px; text-transform: uppercase;
}

.ai-modal-body { padding: 30px 32px 40px; text-align: center; }
.ai-modal-title { font-family: 'Nunito', sans-serif; font-size: 1.35rem; font-weight: 800; color: #1e293b; margin-bottom: 12px; line-height: 1.3; }
.ai-modal-desc { font-size: 0.88rem; color: #64748b; line-height: 1.6; margin-bottom: 28px; }

.ai-btn-primary {
    background: linear-gradient(135deg, var(--g500), var(--t500));
    color: #ffffff; text-decoration: none; padding: 14px 24px; border-radius: 16px;
    font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 8px;
    box-shadow: 0 10px 20px -5px rgba(34, 197, 94, 0.4); transition: all 0.3s ease; width: 100%;
}
.ai-btn-primary:hover { transform: translateY(-3px); box-shadow: 0 15px 25px -5px rgba(34, 197, 94, 0.5); color: #ffffff; }

.ai-btn-secondary {
    background: transparent; border: none; color: #94a3b8; font-weight: 600; font-size: 0.85rem;
    margin-top: 16px; cursor: pointer; transition: color 0.2s ease; font-family: 'Kanit', sans-serif;
}
.ai-btn-secondary:hover { color: #64748b; text-decoration: underline; }
</style>
</head>
<body>

<?php include '../includes/sidebar.php' ?>
    
<div class="page-wrap">

  <header class="topbar">
  <button class="menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
  </button>

    <div class="tb-date">
      <i class="fas fa-calendar-days" style="color:var(--g500);font-size:.75rem;"></i>
      <?php echo date('l, j F Y'); ?>
    </div>
  </header>

  <main>

    <div class="rv rv1" style="margin-bottom:1.8rem;">
      <?php
        // คำนวณเวลาปัจจุบันสำหรับคำทักทายอัจฉริยะ
        $hour = date('H');
        if ($hour >= 6 && $hour < 12) {
            $greet_time = "อรุณสวัสดิ์"; 
            $greet_icon = "🌅";
            $greet_sub = "เริ่มต้นวันใหม่ด้วยมื้ออาหารที่ดี";
        } elseif ($hour >= 12 && $hour < 18) {
            $greet_time = "สวัสดีตอนบ่าย"; 
            $greet_icon = "☀️";
            $greet_sub = "เติมพลังยามบ่ายกันเถอะ";
        } else {
            $greet_time = "สวัสดีตอนเย็น"; 
            $greet_icon = "🌙";
            $greet_sub = "พักผ่อนและดูแลสุขภาพนะ";
        }
      ?>
      <p style="font-size:.75rem;color:var(--muted);font-weight:400;letter-spacing:.04em;margin-bottom:4px;">
        <?= $greet_sub ?> <?= $greet_icon ?>
      </p>
      <h1 style="font-family:'Nunito',sans-serif;font-size:1.7rem;font-weight:800;color:var(--txt);line-height:1.1;">
        <?= $greet_time ?>, <span style="color:var(--g600);">คุณ <?php echo htmlspecialchars($username); ?></span>
      </h1>
      <div class="gline" style="background:var(--g500);height:4px;border-radius:2px;width:52px;margin-top:10px;"></div>
    </div>

    <div class="rv rv2" style="display:grid;grid-template-columns:1.25fr 1fr 1fr;gap:18px;margin-bottom:2rem;">

      <div class="cal-card">
        <div class="cal-card-inner">
          <p style="font-size:.65rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:rgba(255, 255, 255, 0.9);margin-bottom:18px;">
            แคลอรี่วันนี้
          </p>
          <div style="display:flex;align-items:center;gap:22px;">
            <div class="ring-wrap">
              <svg viewBox="0 0 120 120" width="116" height="116">
                <defs>
                  <linearGradient id="greenGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%"   stop-color="#4ade80"/>
                    <stop offset="100%" stop-color="#2dd4bf"/>
                  </linearGradient>
                  <linearGradient id="redGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%"   stop-color="#fca5a5"/>
                    <stop offset="100%" stop-color="#ef4444"/>
                  </linearGradient>
                </defs>
                <circle class="ring-bg"   cx="60" cy="60" r="50"/>
                <circle class="ring-fill" cx="60" cy="60" r="50" style="stroke: url(#<?php echo $over > 0 ? 'redGrad' : 'greenGrad'; ?>);"/>
              </svg>
              <div class="ring-center">
                <span style="font-family:'Nunito',sans-serif;font-size:1.4rem;font-weight:800;color:<?php echo $over > 0 ? '#fca5a5' : '#4ade80'; ?>;line-height:1;">
                  <?php echo round($calPercentRaw); ?><span style="font-size:.58rem;font-family:'Kanit',sans-serif;font-weight:400;color:rgba(255,255,255,.9);">%</span>
                </span>
                <span style="font-size:.56rem;color:rgba(255,255,255,.9);margin-top:2px;letter-spacing:.04em;"><?php echo $over > 0 ? 'เกินเป้า' : 'บรรลุแล้ว'; ?></span>
              </div>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-family:'Nunito',sans-serif;font-size:2rem;font-weight:800;color:white;line-height:1;">
                <?php echo number_format($consumedCal); ?>
                <span style="font-size:.8rem;font-weight:400;font-family:'Kanit',sans-serif;color:rgba(255,255,255,.9);">kcal</span>
              </div>
              <p style="font-size:.72rem;color:rgba(255,255,255,.9);margin-top:7px;">
                เป้าหมาย: <?php echo htmlspecialchars($goalTitle); ?> <br><span style="color:#4ade80;font-weight:600;"><?php echo number_format($targetCal); ?> kcal</span>
              </p>
              <div class="pbar" style="margin-top:14px;">
                <div class="pbar-fill" style="width:<?php echo $barWidth; ?>%; <?php if($over > 0) echo 'background:linear-gradient(90deg, #fca5a5, #ef4444);box-shadow:0 0 10px rgba(239,68,68,0.5);'; ?>"></div>
              </div>
              <div style="display:flex;justify-content:space-between;margin-top:9px;">
                <p style="font-size:.68rem;color:rgba(255,255,255,.9);">
                  <?php if($over > 0): ?>
                    เกิน <span style="color:#fca5a5;font-weight:600;">+<?php echo number_format($over); ?> kcal</span>
                  <?php else: ?>
                    เหลือ <span style="color:#2dd4bf;font-weight:600;"><?php echo number_format($remaining); ?> kcal</span>
                  <?php endif; ?>
                </p>
                <p style="font-size:.68rem;color:rgba(255,255,255,.9);"><?php echo number_format($calPercentRaw,0); ?>%</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card" style="padding:22px;display:flex;flex-direction:column;">
        <p style="font-size:.62rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);margin-bottom:14px;">ข้อมูลสุขภาพ</p>
        <div style="display:flex;flex-direction:column;gap:9px;flex:1;">
          <div class="stat-pill">
            <div class="stat-icon" style="background:#fff1f2;border:1px solid #fecdd3;">
              <i class="fas fa-heart-pulse" style="color:#e11d48;"></i>
            </div>
            <div style="min-width:0;">
              <p style="font-size:.6rem;color:var(--muted);">โรคประจำตัว</p>
              <p style="font-size:.8rem;font-weight:600;color:#dc2626;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                 title="<?php echo htmlspecialchars($conditions); ?>">
                <?php echo htmlspecialchars(mb_strimwidth($conditions,0,22,'…')); ?>
              </p>
            </div>
          </div>
          <div class="stat-pill">
            <div class="stat-icon" style="background:var(--g50);border:1px solid var(--g200);">
              <i class="fas fa-seedling" style="color:var(--g600);"></i>
            </div>
            <div>
              <p style="font-size:.6rem;color:var(--muted);">รูปแบบการกิน</p>
              <p style="font-size:.8rem;font-weight:600;color:var(--g700);margin-top:1px;"><?php echo htmlspecialchars($dietType); ?></p>
            </div>
          </div>
        </div>
        <a href="setup_profile.php" class="btn-ghost" style="margin-top:14px;justify-content:center;">
          <i class="fas fa-pen-to-square" style="font-size:.62rem;"></i> แก้ไขข้อมูล
        </a>
      </div>

      <div class="card" style="padding:22px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px;">
          <p style="font-size:.62rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);">การดื่มน้ำ</p>
          <span style="font-size:.9rem;"></span>
        </div>
        <div style="font-family:'Nunito',sans-serif;font-size:1.9rem;font-weight:800;color:var(--txt);line-height:1;margin-bottom:4px;">
          <?php echo $water_glasses; ?> <span style="color:var(--muted);font-size:1rem;font-weight:400;">/</span> 8
          <span style="font-size:.72rem;font-family:'Kanit',sans-serif;font-weight:400;color:var(--muted);">แก้ว</span>
        </div>
        <p style="font-size:.7rem;color:var(--muted);margin-bottom:10px;">
          <?php echo number_format($water_total); ?> / <?php echo number_format($water_target); ?> ml (<?php echo round($water_percent); ?>%)
        </p>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:13px;">
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="log_water">
            <input type="hidden" name="amount_ml" value="250">
            <button type="submit" style="width:100%;padding:8px;background:var(--g50);border:1px solid var(--bdr);border-radius:10px;font-size:.7rem;font-weight:600;color:var(--g600);cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:6px;font-family:'Kanit',sans-serif;">
              <i class="fas fa-plus"></i> ดื่ม 1 แก้ว
            </button>
          </form>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="log_water">
            <input type="hidden" name="amount_ml" value="500">
            <button type="submit" style="width:100%;padding:8px;background:var(--g50);border:1px solid var(--bdr);border-radius:10px;font-size:.7rem;font-weight:600;color:var(--g600);cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:6px;font-family:'Kanit',sans-serif;">
              <i class="fas fa-plus"></i> ดื่ม 2 แก้ว
            </button>
          </form>
        </div>
        
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-bottom:13px;">
          <?php for($i=1;$i<=8;$i++): ?>
          <div class="wdrop <?php echo $i<=$water_glasses?'on':'off'; ?>"></div>
          <?php endfor; ?>
        </div>
        
        <div class="pbar" style="height:5px;">
          <div style="width:<?php echo $water_percent; ?>%;height:100%;border-radius:99px;background:linear-gradient(90deg,#7dd3fc,#38bdf8);box-shadow:0 0 8px rgba(56,189,248,.3);transition:width .3s;"></div>
        </div>
      </div>
    </div><div class="rv rv3" style="display:grid;grid-template-columns:1fr 328px;gap:2rem;align-items:start;">

      <div style="display:flex;flex-direction:column;gap:2rem;">

        <div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <h2 class="stitle"><i class="fas fa-utensils" style="color: #22c55e;"></i> เมนูที่บันทึกวันนี้</h2>
            <a href="ai_chef.php" class="btn-green">
              <i class="fas fa-plus" style="font-size:.62rem;"></i> เพิ่มเมนู
            </a>
          </div>
          <div class="scroll-box">
            <?php if($menu_res->num_rows>0): while($menu=$menu_res->fetch_assoc()): ?>
            <div class="menu-row" style="<?php echo $menu['recipe_id'] ? 'cursor:pointer;' : ''; ?>" 
                 <?php echo $menu['recipe_id'] ? 'onclick="location.href=\'recipe_detail.php?id='.$menu['recipe_id'].'\'"' : ''; ?>>
              <div class="menu-dot"></div>
              <div style="flex:1;min-width:0;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                  <h4 style="font-size:.85rem;font-weight:600;color:var(--txt);line-height:1.45;">
                    <?php echo htmlspecialchars($menu['menu_name']); ?>
                    <?php if($menu['recipe_id']): ?>
                    <i class="fas fa-external-link-alt" style="font-size:.65rem;color:var(--g500);margin-left:6px;"></i>
                    <?php endif; ?>
                  </h4>
                  <span class="badge-green"><i class="bi bi-fire" style="color: #ff5722;"></i> <?php echo number_format($menu['calories']); ?> kcal</span>
                </div>
                <p style="font-size:.72rem;color:var(--muted);margin-top:5px;line-height:1.6;">
                  <?php echo htmlspecialchars(mb_strimwidth($menu['description']??'',0,95,'…')); ?>
                </p>
              </div>
            </div>
            <?php endwhile; else: ?>
            <div class="empty">
              <div style="font-size:2.2rem;opacity:.25;margin-bottom:10px;">🫙</div>
              <p style="font-size:.85rem;font-weight:500;margin-bottom:4px;">วันนี้ยังไม่ได้บันทึกเมนู</p>
              <p style="font-size:.73rem;color:var(--muted);margin-bottom:16px;">เริ่มบันทึกมื้ออาหารกับเชฟ AI ของคุณได้เลย</p>
              <a href="ai_chef.php" class="btn-green" style="display:inline-flex;">
                <i class="fas fa-robot" style="font-size:.62rem;"></i> ไปคุยกับเชฟ AI
              </a>
            </div>
            <?php endif; ?>
          </div>
        </div>


        <div style="margin-bottom:2rem;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <h2 class="stitle">
              <i class="bi bi-fire" style="color: #ff5722;"></i>เมนูมาแรงประจำวัน
              <span class="badge-sky" style="font-size:.62rem;font-weight:700;padding:3px 10px;">Top 3</span>
            </h2>
            <a href="menu_popular.php" class="btn-ghost">ดูทั้งหมด <i class="fas fa-arrow-right" style="font-size:.58rem;"></i></a>
          </div>

          <?php if(count($most_viewed_menus)>0): ?>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;">
            <?php
            $medals = [
            '<i class="fas fa-medal" style="color: #FFD700;" title="อันดับ 1"></i>', // สีทอง
            '<i class="fas fa-medal" style="color: #C0C0C0;" title="อันดับ 2"></i>', // สีเงิน
            '<i class="fas fa-medal" style="color: #CD7F32;" title="อันดับ 3"></i>'  // สีทองแดง
            ];
            $rcls   = ['r1','r2','r3'];
            $rac    = ['#d97706','#64748b','#ea580c'];
            $rfill  = ['linear-gradient(90deg,#fbbf24,#f59e0b)','linear-gradient(90deg,#94a3b8,#64748b)','linear-gradient(90deg,#fb923c,#f97316)'];
            $rglow  = ['rgba(251,191,36,.35)','rgba(148,163,184,.25)','rgba(251,146,60,.3)'];
            $max_views = !empty($most_viewed_menus) ? $most_viewed_menus[0]['view_count'] : 1;
            foreach($most_viewed_menus as $i=>$m):
              $pct  = $max_views>0 ? round($m['view_count']/$max_views*100) : 100;
              $kcal = intval($m['calories'])>0 ? number_format($m['calories']).' kcal' : '—';
            ?>
            <div class="tcard <?php echo $rcls[$i]; ?>">
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:1.75rem;"><?php echo $medals[$i]; ?></span>
                <span style="font-size:.58rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:<?php echo $rac[$i]; ?>;background:<?php echo $i===0?'#fffbeb':($i===1?'#f8fafc':'#fff7ed'); ?>;border:1px solid <?php echo $i===0?'#fde68a':($i===1?'#e2e8f0':'#fed7aa'); ?>;padding:3px 9px;border-radius:99px;">
                  อันดับ <?php echo $i+1; ?>
                </span>
              </div>

              <div>
                <h4 style="font-size:.82rem;font-weight:700;color:var(--txt);line-height:1.5;min-height:2.5em;">
                  <?php echo htmlspecialchars(mb_strimwidth($m['title'],0,42,'…')); ?>
                </h4>
                <p style="font-size:.7rem;color:var(--muted);margin-top:4px;line-height:1.55;min-height:2.8em;">
                  <?php echo htmlspecialchars(mb_strimwidth($m['description']??'เมนูยอดนิยม',0,58,'…')); ?>
                </p>
              </div>

              <div style="display:flex;gap:7px;flex-wrap:wrap;align-items:center;">
                <span class="badge-green"><i class="bi bi-fire" style="color: #ff5722;"></i> <?php echo $kcal; ?></span>
                <span style="font-size:.62rem;color:var(--muted);display:flex;align-items:center;gap:4px;">
                  <i class="fas fa-eye" style="color:#38bdf8;font-size:.58rem;"></i><?php echo number_format($m['view_count']); ?>
                </span>
              </div>

              <div>
                <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                  <span style="font-size:.6rem;color:var(--muted);">การเข้าชม</span>
                  <span style="font-size:.6rem;color:<?php echo $rac[$i]; ?>;font-weight:700;"><?php echo $pct; ?>%</span>
                </div>
                <div class="pbar" style="height:5px;">
                  <div style="width:<?php echo $pct; ?>%;height:100%;border-radius:99px;background:<?php echo $rfill[$i]; ?>;box-shadow:0 0 6px <?php echo $rglow[$i]; ?>;"></div>
                </div>
              </div>

              <a href="recipe_detail.php?id=<?php echo $m['id']; ?>" class="btn-ghost" style="justify-content:center;">
                ดูสูตร <i class="fas fa-chevron-right" style="font-size:.58rem;"></i>
              </a>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="empty">
            <div style="font-size:2rem;opacity:.2;margin-bottom:10px;">👁️</div>
            <p style="font-size:.82rem;">ยังไม่มีข้อมูลการเข้าชม</p>
          </div>
          <?php endif; ?>
        </div>

        <div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <h2 class="stitle">
              <i class="bi bi-suit-heart-fill" style="color: #dc2626;"></i>เมนูที่ถูกใจมากสุด
              <span class="badge-orange" style="font-size:.62rem;font-weight:700;padding:3px 10px;">Top 3</span>
            </h2>
            <a href="menu_popular.php" class="btn-ghost">ดูทั้งหมด <i class="fas fa-arrow-right" style="font-size:.58rem;"></i></a>
          </div>

          <?php if(count($most_favorited_menus)>0): ?>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;">
            <?php
            $max_favs = !empty($most_favorited_menus) ? $most_favorited_menus[0]['fav_count'] : 1;
            foreach($most_favorited_menus as $i=>$m):
              $pct  = $max_favs>0 ? round($m['fav_count']/$max_favs*100) : 100;
              $kcal = intval($m['calories'])>0 ? number_format($m['calories']).' kcal' : '—';
            ?>
            <div class="tcard <?php echo $rcls[$i]; ?>">
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:1.75rem;"><?php echo $medals[$i]; ?></span>
                <span style="font-size:.58rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:<?php echo $rac[$i]; ?>;background:<?php echo $i===0?'#fffbeb':($i===1?'#f8fafc':'#fff7ed'); ?>;border:1px solid <?php echo $i===0?'#fde68a':($i===1?'#e2e8f0':'#fed7aa'); ?>;padding:3px 9px;border-radius:99px;">
                  อันดับ <?php echo $i+1; ?>
                </span>
              </div>

              <div>
                <h4 style="font-size:.82rem;font-weight:700;color:var(--txt);line-height:1.5;min-height:2.5em;">
                  <?php echo htmlspecialchars(mb_strimwidth($m['title'],0,42,'…')); ?>
                </h4>
                <p style="font-size:.7rem;color:var(--muted);margin-top:4px;line-height:1.55;min-height:2.8em;">
                  <?php echo htmlspecialchars(mb_strimwidth($m['description']??'เมนูยอดนิยม',0,58,'…')); ?>
                </p>
              </div>

              <div style="display:flex;gap:7px;flex-wrap:wrap;align-items:center;">
                <span class="badge-green">🔥 <?php echo $kcal; ?></span>
                <span style="font-size:.62rem;color:var(--muted);display:flex;align-items:center;gap:4px;">
                  <i class="fas fa-heart" style="color:#f43f5e;font-size:.58rem;"></i><?php echo $m['fav_count']; ?>
                </span>
              </div>

              <div>
                <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                  <span style="font-size:.6rem;color:var(--muted);">ความนิยม</span>
                  <span style="font-size:.6rem;color:<?php echo $rac[$i]; ?>;font-weight:700;"><?php echo $pct; ?>%</span>
                </div>
                <div class="pbar" style="height:5px;">
                  <div style="width:<?php echo $pct; ?>%;height:100%;border-radius:99px;background:<?php echo $rfill[$i]; ?>;box-shadow:0 0 6px <?php echo $rglow[$i]; ?>;"></div>
                </div>
              </div>

              <a href="recipe_detail.php?id=<?php echo $m['id']; ?>" class="btn-ghost" style="justify-content:center;">
                ดูสูตร <i class="fas fa-chevron-right" style="font-size:.58rem;"></i>
              </a>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="empty">
            <div style="font-size:2rem;opacity:.2;margin-bottom:10px;">❤️</div>
            <p style="font-size:.82rem;">ยังไม่มีเมนูที่ถูกใจ</p>
          </div>
          <?php endif; ?>
        </div>

      </div><div style="display:flex;flex-direction:column;gap:1.4rem;">

        <div class="rv rv4 tip-card">
          <div class="tip-inner">
            <div style="display:flex;align-items:center;gap:9px;margin-bottom:14px;">
              <span style="background:linear-gradient(135deg,var(--g400),var(--t400));color:#052e16;font-size:.6rem;font-weight:800;letter-spacing:.1em;padding:4px 10px;border-radius:8px;">TIP</span>
              <span style="font-size:.7rem;color:rgba(255,255,255,.45);">คำแนะนำวันนี้</span>
            </div>
            <p style="font-size:.83rem;color:rgba(255,255,255,.75);line-height:1.8;font-style:italic;">
              "การดื่มน้ำ 1–2 แก้วก่อนมื้ออาหาร ช่วยลดความอยากอาหารและเพิ่มประสิทธิภาพการเผาผลาญครับ"
            </p>
          </div>
        </div>

        <div class="rv rv5 card" style="overflow:hidden;padding:0;">
          <div style="padding:13px 18px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--bdr);background:var(--g50);">
            <div style="display:flex;align-items:center;gap:8px;">
              <span style="font-size:.85rem;"><i class="bi bi-newspaper"></i></span>
              <span style="font-size:.76rem;font-weight:700;color:var(--txt);font-family:'Nunito',sans-serif;">ข่าวสารอาหารวันนี้</span>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
              <span class="ldot"></span>
              <span style="font-size:.58rem;color:var(--g600);font-weight:700;letter-spacing:.1em;">LIVE</span>
            </div>
          </div>

          <?php if($daily_news): ?>
          <div style="padding:16px;">
            <?php if(!empty($daily_news['image_url'])): ?>
            <div class="news-img" style="margin-bottom:13px;">
              <img src="<?php echo htmlspecialchars($daily_news['image_url']); ?>"
                   alt="<?php echo htmlspecialchars($daily_news['title']); ?>"
                   onerror="this.closest('.news-img').style.display='none'">
            </div>
            <?php endif; ?>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:9px;">
              <span style="font-size:.6rem;font-weight:600;color:var(--g600);letter-spacing:.04em;">
                <i class="bi bi-calendar-date"></i> <?php echo date('d M Y',strtotime($daily_news['created_at'])); ?>
              </span>
              <span style="font-size:.57rem;color:var(--muted);letter-spacing:.04em;">
              </span>
            </div>
            <h4 style="font-family:'Nunito',sans-serif;font-size:.92rem;font-weight:800;color:var(--txt);line-height:1.45;margin-bottom:9px;">
              <?php echo htmlspecialchars($daily_news['title']); ?>
            </h4>
            <p style="font-size:.73rem;color:var(--muted);line-height:1.75;display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden;">
              <?php echo strip_tags($daily_news['content']); ?>
            </p>
            <div style="border-top:1px solid var(--bdr);margin-top:13px;padding-top:12px;">
              <a href="news_detail.php?id=<?php echo $daily_news['id']; ?>" class="btn-ghost" style="width:100%;justify-content:space-between;">
                <span>อ่านเพิ่มเติม</span>
                <i class="fas fa-arrow-right" style="font-size:.58rem;"></i>
              </a>
            </div>
          </div>
          <?php else: ?>
          <div style="padding:2.5rem 1rem;text-align:center;">
            <div style="font-size:2rem;margin-bottom:10px;opacity:.3;">📭</div>
            <p style="font-size:.78rem;color:var(--muted);">ยังไม่มีข่าวสารวันนี้</p>
            <p style="font-size:.68rem;color:var(--muted);margin-top:4px;">n8n จะอัปเดตทุกวัน 07:00 น.</p>
          </div>
          <?php endif; ?>

          <div style="border-top:1px solid var(--bdr);padding:10px 16px;background:var(--g50);">
            <a href="news.php" class="btn-ghost" style="width:100%;justify-content:center;font-size:.67rem;">
              <i class="fas fa-newspaper" style="font-size:.6rem;"></i> ดูข่าวสารทั้งหมด
            </a>
          </div>
        </div>

      </div>
    </div>
    <?php include '../includes/footer.php'; ?>
  </main>

</div>

<div id="premiumAiModal" class="ai-modal-overlay">
    <div class="ai-modal-card">
        <div class="ai-modal-header">
            <div class="ai-modal-badge"><i class="fas fa-sparkles"></i> New Feature</div>
            <button class="ai-modal-close" onclick="closePremiumAiModal()"><i class="fas fa-times"></i></button>
            <div class="ai-icon-wrapper">
                <i class="fas fa-robot"></i>
            </div>
        </div>
        <div class="ai-modal-body">
            <h3 class="ai-modal-title">ให้ AI จัดมื้ออาหารให้คุณไหม?</h3>
            <p class="ai-modal-desc">
                หมดปัญหา "วันนี้กินอะไรดี?" ให้ <b>AI Chef</b> วิเคราะห์สุขภาพและโรคประจำตัวของคุณ เพื่อจัดตารางเมนูสุดพิเศษในคลิกเดียว
            </p>
            <a href="ai_chef.php" class="ai-btn-primary">
                <i class="fas fa-magic"></i> เริ่มคุยกับ AI Chef
            </a>
            <button class="ai-btn-secondary" onclick="closePremiumAiModal()">
                ไว้คราวหน้า
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // รีเซ็ตคีย์เดิม (ถ้าเคยใช้โค้ดเก่า) และใช้คีย์ใหม่ เพื่อให้ชัวร์ว่ามันจะเด้งขึ้นมาให้ทดสอบครับ
    if (!sessionStorage.getItem('premium_ai_promo_shown')) {
        const modal = document.getElementById('premiumAiModal');
        modal.style.display = 'flex';
        
        // บังคับให้เบราว์เซอร์อ่านค่า (Reflow) เพื่อให้ Animation ทำงานสมบูรณ์
        void modal.offsetWidth;
        
        modal.classList.add('show');
        sessionStorage.setItem('premium_ai_promo_shown', 'true');
    }
});

function closePremiumAiModal() {
    const modal = document.getElementById('premiumAiModal');
    modal.classList.remove('show');
    
    // รอให้ Animation ตอนปิดเล่นจบก่อน ค่อยซ่อน Element
    setTimeout(() => {
        modal.style.display = 'none';
    }, 400); 
}
</script>

<script>
function toggleSidebar() {
  const sidebar = document.querySelector('.sidebar');
  sidebar.classList.toggle('show'); // สลับสถานะ class "show" เพื่อเลื่อน Sidebar เข้า-ออก
}

// ปิด Sidebar อัตโนมัติเมื่อคลิกพื้นที่ด้านนอกบนมือถือ
document.addEventListener('click', (e) => {
  const sidebar = document.querySelector('.sidebar');
  const btn = document.querySelector('.menu-toggle');
  if (window.innerWidth <= 1024 && !sidebar.contains(e.target) && !btn.contains(e.target)) {
    sidebar.classList.remove('show');
  }
});
</script>

</body>
</html>