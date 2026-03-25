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

/* ── ✅ Calorie target & Goal ── */
$hp = $conn->prepare("
    SELECT hp.daily_calorie_target, COALESCE(g.title, 'ไม่ระบุเป้าหมาย') as goal_title 
    FROM health_profiles hp 
    LEFT JOIN goals g ON hp.goal_preference = g.goal_key 
    WHERE hp.user_id = ?
");
$hp->bind_param("i", $user_id);
$hp->execute();
$hp_row    = $hp->get_result()->fetch_assoc();
$targetCal = $hp_row['daily_calorie_target'] ?? 2000;
$goalTitle = $hp_row['goal_title'] ?? 'ไม่ระบุเป้าหมาย';

/* ── Selected date ── */
$selected_date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])
    ? $_GET['date'] : date('Y-m-d');
$today = date('Y-m-d');

/* ── Handle quick-log from recipes & AI menus (POST) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'log_recipe') {
        $rid_raw   = $_POST['recipe_id'];
        $meal_type = $_POST['meal_type'] ?? 'lunch';
        
        // รับค่าวันที่จากหน้าเว็บ เพื่อให้การบันทึกย้อนหลังถูกต้องเสมอ
        $log_date  = $_POST['log_date'] ?? date('Y-m-d');
        $log_time  = date('H:i:s');
        $logged_at = $log_date . ' ' . $log_time;

        if (strpos($rid_raw, 'ai_') === 0) {
            $ai_id = (int)str_replace('ai_', '', $rid_raw);
            
            $ai_fetch = $conn->prepare("SELECT menu_name, description, calories FROM ai_saved_menus WHERE id = ? AND user_id = ?");
            $ai_fetch->bind_param("ii", $ai_id, $user_id);
            $ai_fetch->execute();
            $ai_data = $ai_fetch->get_result()->fetch_assoc();
            
            if ($ai_data) {
                // เช็คว่าเคยเพิ่มเมนูนี้ไปในตาราง recipes หรือยัง จะได้ไม่เพิ่มซ้ำซ้อน
                $check_r = $conn->prepare("SELECT id FROM recipes WHERE title = ? AND calories = ? LIMIT 1");
                $check_r->bind_param("si", $ai_data['menu_name'], $ai_data['calories']);
                $check_r->execute();
                $res_r = $check_r->get_result();
                
                if ($res_r->num_rows > 0) {
                    $rid = $res_r->fetch_assoc()['id'];
                } else {
                    $empty_str = '';
                    $ins = $conn->prepare("INSERT INTO recipes (title, description, instructions, calories) VALUES (?, ?, ?, ?)");
                    $ins->bind_param("sssi", $ai_data['menu_name'], $ai_data['description'], $empty_str, $ai_data['calories']);
                    $ins->execute();
                    $rid = $conn->insert_id;
                }
                
            } else {
                echo json_encode(['status' => 'error']);
                exit();
            }
        } else {
            $rid = (int)$rid_raw;
        }

        $stmt = $conn->prepare("INSERT INTO meal_logs (user_id, recipe_id, meal_type, logged_at) VALUES (?,?,?,?)");
        $stmt->bind_param("iiss", $user_id, $rid, $meal_type, $logged_at);
        echo json_encode(['status' => $stmt->execute() ? 'success' : 'error']);
        exit();
    }

    if ($action === 'delete_log') {
        $lid  = (int)$_POST['log_id'];
        $stmt = $conn->prepare("DELETE FROM meal_logs WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $lid, $user_id);
        echo json_encode(['status' => $stmt->execute() ? 'success' : 'error']);
        exit();
    }

    if ($action === 'delete_ai_menu') {
        $mid  = (int)$_POST['menu_id'];
        $stmt = $conn->prepare("DELETE FROM ai_saved_menus WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $mid, $user_id);
        echo json_encode(['status' => $stmt->execute() ? 'success' : 'error']);
        exit();
    }

    echo json_encode(['status' => 'unknown_action']);
    exit();
}

/* ── AI saved menus for selected date ── */
$ai_stmt = $conn->prepare(
    "SELECT * FROM ai_saved_menus WHERE user_id = ? AND DATE(created_at) = ? ORDER BY created_at DESC"
);
$ai_stmt->bind_param("is", $user_id, $selected_date);
$ai_stmt->execute();
$ai_menus = $ai_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* ── meal_logs (from recipes) for selected date ── */
$log_stmt = $conn->prepare(
    "SELECT ml.id as log_id, ml.meal_type, ml.logged_at,
            r.id as recipe_id, r.title, r.description, r.calories
     FROM meal_logs ml
     JOIN recipes r ON ml.recipe_id = r.id
     WHERE ml.user_id = ? AND DATE(ml.logged_at) = ?
     ORDER BY ml.logged_at DESC"
);
$log_stmt->bind_param("is", $user_id, $selected_date);
$log_stmt->execute();
$recipe_logs = $log_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* ── ✅ Summary (Over-Calorie logic added) ── */
$ai_cal      = array_sum(array_column($ai_menus,    'calories'));
$rec_cal     = array_sum(array_column($recipe_logs, 'calories'));
$total_cal   = $rec_cal; // คำนวณจากที่กินจริงเท่านั้น (ตัด AI ออก)
$cal_pct_raw = $targetCal > 0 ? round($total_cal / $targetCal * 100) : 0;
$cal_pct     = min(100, $cal_pct_raw);
$bar_width   = min($cal_pct_raw, 100);
$remaining   = max(0, $targetCal - $total_cal);
$over        = max(0, $total_cal - $targetCal);
$ring_off    = round(314 * (1 - min($cal_pct_raw, 100) / 100));

/* ── Group recipe logs by meal_type ── */
$meal_types = [
    'breakfast' => ['label' => 'มื้อเช้า',   'icon' => '<i class="bi bi-brightness-alt-high-fill" style="color: #e7a636;"></i>', 'color' => '#f59e0b'],
    'lunch'     => ['label' => 'มื้อกลางวัน','icon' => '<i class="bi bi-sun-fill" style="color: #e7a636;"></i>', 'color' => '#22c55e'],
    'snack'     => ['label' => 'ของว่าง',    'icon' => '<i class="fa-solid fa-apple-whole" style="color: #D73535;"></i>', 'color' => '#f97316'],
    'dinner'    => ['label' => 'มื้อเย็น',   'icon' => '<i class="bi bi-moon-stars-fill" style="color: #ffc534;"></i>', 'color' => '#6366f1'],
];
$grouped = [];
foreach ($recipe_logs as $log) {
    $grouped[$log['meal_type']][] = $log;
}

/* ── 7-day history for mini chart ── */
$week_data = [];
for ($i = 6; $i >= 0; $i--) {
    $d   = date('Y-m-d', strtotime("-$i days"));
    $lbl = date('D', strtotime($d));
    $lbl_th = ['Mon'=>'จ','Tue'=>'อ','Wed'=>'พ','Thu'=>'พฤ','Fri'=>'ศ','Sat'=>'ส','Sun'=>'อา'][$lbl] ?? $lbl;

    $s2  = $conn->prepare("SELECT COALESCE(SUM(r.calories),0) as c FROM meal_logs ml JOIN recipes r ON ml.recipe_id=r.id WHERE ml.user_id=? AND DATE(ml.logged_at)=?");
    $s2->bind_param("is", $user_id, $d);
    $s2->execute();
    $c2  = (int)$s2->get_result()->fetch_assoc()['c'];

    $week_data[] = ['date' => $d, 'label' => $lbl_th, 'cal' => $c2, 'is_today' => $d === $today, 'is_sel' => $d === $selected_date];
}
$max_week = max(array_column($week_data, 'cal') ?: [1]);

/* ── Quick-add recipes list ── */
$r_stmt = $conn->prepare("SELECT id, title, calories FROM recipes ORDER BY title ASC");
$r_stmt->execute();
$all_recipes = $r_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* ── Quick-add AI menus list ดึงมาแค่ของวันที่เลือกดูเท่านั้น ── */
$all_ai_stmt = $conn->prepare("SELECT id, menu_name, calories FROM ai_saved_menus WHERE user_id = ? AND DATE(created_at) = ? ORDER BY created_at DESC");
$all_ai_stmt->bind_param("is", $user_id, $selected_date);
$all_ai_stmt->execute();
$all_ai_menus = $all_ai_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>บันทึกมื้ออาหาร — FoodAI</title>
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
  --txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;--muted2:#c5d5c6;
  --sb-w:248px;--sb-bdr:#e5ede6;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,#c8e6c9 1px,transparent 1px);background-size:28px 28px;opacity:.35;}

/* ─── Sidebar (shared) ─── */
.sidebar{width:var(--sb-w);min-height:100vh;background:#fff;border-right:1px solid var(--sb-bdr);display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;z-index:100;box-shadow:4px 0 24px rgba(34,197,94,.06);}
.sb-logo{padding:24px 22px 20px;border-bottom:1px solid var(--sb-bdr);display:flex;align-items:center;gap:11px;}
.sb-logo-icon{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--g500),var(--t500));display:flex;align-items:center;justify-content:center;font-size:1.1rem;box-shadow:0 4px 12px rgba(34,197,94,.35);flex-shrink:0;}
.sb-logo-text{font-family:'Nunito',sans-serif;font-size:1.18rem;font-weight:800;color:var(--g700);letter-spacing:-.02em;line-height:1;}
.sb-logo-sub{font-size:.6rem;font-weight:600;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin-top:2px;}
.sb-label{font-size:.6rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);padding:18px 22px 8px;}
.sb-nav{padding:6px 12px;display:flex;flex-direction:column;gap:2px;flex:1;overflow-y:auto;}
.nav-item{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:12px;text-decoration:none;color:var(--sub);font-size:.82rem;font-weight:500;transition:all .18s;}
.nav-item:hover{background:var(--g50);color:var(--g700);transform:translateX(2px);}
.nav-item.active{background:var(--g50);color:var(--g600);font-weight:600;box-shadow:inset 3px 0 0 var(--g500);}
.nav-item.active .ni{background:linear-gradient(135deg,var(--g500),var(--t500));color:white;box-shadow:0 3px 10px rgba(34,197,94,.38);}
.ni{width:34px;height:34px;border-radius:10px;background:var(--g50);border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;transition:all .18s;color:var(--g600);}
.nav-item:hover .ni{background:var(--g100);border-color:var(--g200);}
.nb{margin-left:auto;background:var(--g500);color:#fff;font-size:.6rem;font-weight:700;padding:2px 7px;border-radius:99px;}
.nb.orange{background:#f97316;}
.sb-div{height:1px;background:var(--sb-bdr);margin:6px 12px;}
.sb-user{border-top:1px solid var(--sb-bdr);padding:16px;display:flex;align-items:center;gap:11px;background:var(--g50);}
.sb-av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--t400));display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:800;color:#fff;flex-shrink:0;font-family:'Nunito',sans-serif;box-shadow:0 2px 8px rgba(34,197,94,.3);}
.sb-un{font-size:.78rem;font-weight:600;color:var(--txt);line-height:1.2;}
.sb-out{margin-left:auto;width:30px;height:30px;border-radius:8px;border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:.72rem;text-decoration:none;transition:all .18s;}
.sb-out:hover{background:#fee2e2;border-color:#fecaca;color:#dc2626;}

/* ─── Page shell ─── */
.page-wrap{margin-left:var(--sb-w);flex:1;position:relative;z-index:1;min-width:0;}
.topbar{height:62px;background:rgba(255,255,255,.9);backdrop-filter:blur(12px);border-bottom:1px solid var(--sb-bdr);display:flex;align-items:center;padding:0 2.5rem;gap:14px;position:sticky;top:0;z-index:50;}
.tb-back{width:38px;height:38px;border-radius:11px;background:#fff;border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;color:var(--sub);text-decoration:none;font-size:.8rem;transition:all .18s;flex-shrink:0;}
.tb-back:hover{background:var(--g50);border-color:var(--g200);color:var(--g600);}

main { padding: 2rem 2.5rem 3.5rem; width: 100%; max-width: 100%; margin: 0 auto; }

/* ─── Animations ─── */
@keyframes slideUp{from{opacity:0;transform:translateY(18px);}to{opacity:1;transform:translateY(0);}}
.rv{opacity:0;animation:slideUp .5s cubic-bezier(.22,1,.36,1) forwards;}
.rv1{animation-delay:.04s;}.rv2{animation-delay:.10s;}.rv3{animation-delay:.17s;}
.rv4{animation-delay:.24s;}.rv5{animation-delay:.31s;}.rv6{animation-delay:.38s;}

/* ─── Cards ─── */
.card { background: var(--card); border: 1px solid var(--bdr); border-radius: 20px; transition: box-shadow .2s, transform .2s, border-color .2s; }
.card:hover { transform: translateY(-3px); box-shadow: 0 12px 25px -5px rgba(21, 128, 61, 0.1), 0 8px 10px -6px rgba(13, 148, 136, 0.15); border-color: rgba(13, 148, 136, 0.35); }

/* ─── Calorie ring ─── */
.ring-bg{fill:none;stroke:var(--bdr);stroke-width:9;}
.ring-fill{fill:none;stroke-width:9;stroke-linecap:round;stroke-dasharray:314;stroke-dashoffset:<?php echo $ring_off;?>; transition:stroke-dashoffset 1.2s cubic-bezier(.4,0,.2,1);}
.ring-wrap{position:relative;width:110px;height:110px;flex-shrink:0;}
.ring-wrap svg{transform:rotate(-90deg);}
.ring-center{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;}

/* ─── Bar chart ─── */
.bar-col{display:flex;flex-direction:column;align-items:center;gap:5px;flex:1;cursor:pointer;}
.bar-track{width:100%;background:var(--g50);border-radius:99px 99px 4px 4px;display:flex;flex-direction:column;justify-content:flex-end;height:56px;overflow:hidden;border:1px solid var(--bdr);}
.bar-fill{border-radius:99px 99px 0 0;transition:height .6s cubic-bezier(.4,0,.2,1);}
.bar-lbl{font-size:.6rem;color:var(--muted);font-weight:500;text-align:center;}
.bar-col.sel .bar-lbl{color:var(--g600);font-weight:700;}
.bar-col.today .bar-fill{background:linear-gradient(to top,var(--g600),var(--g400));}
.bar-col.sel:not(.today) .bar-fill{background:linear-gradient(to top,var(--t600),var(--t400));}
.bar-col:not(.sel):not(.today) .bar-fill{background:var(--g200);}

/* ─── Date nav ─── */
.date-nav{display:flex;align-items:center;gap:10px;}
.date-btn{width:34px;height:34px;border-radius:10px;border:1px solid var(--bdr);background:#fff;display:flex;align-items:center;justify-content:center;color:var(--sub);font-size:.78rem;cursor:pointer;transition:all .18s;text-decoration:none;}
.date-btn:hover{background:var(--g50);border-color:var(--g300);color:var(--g600);}
.date-display{font-family:'Nunito',sans-serif;font-size:.92rem;font-weight:800;color:var(--txt);}

/* ─── Progress bar ─── */
.pbar{height:6px;background:rgba(34,197,94,.1);border-radius:99px;overflow:hidden;}
.pbar-fill{height:100%;border-radius:99px;transition:width 1.1s cubic-bezier(.4,0,.2,1);}

/* ─── Meal section ─── */
.meal-section{background:var(--card);border:1px solid var(--bdr);border-radius:18px;overflow:hidden;transition:box-shadow .2s;}
.meal-section:hover{box-shadow:0 6px 20px rgba(34,197,94,.07);}
.meal-header{padding:14px 18px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--bdr);}
.meal-body{padding:12px 14px;display:flex;flex-direction:column;gap:8px;}
.meal-icon-wrap{width:36px;height:36px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex-shrink:0;}
.meal-title{font-family:'Nunito',sans-serif;font-size:.9rem;font-weight:800;color:var(--txt);}
.meal-cal{font-size:.72rem;color:var(--muted);margin-left:auto;}

/* ─── Log row ─── */
.log-row{background:var(--g50);border:1px solid var(--bdr);border-radius:13px;padding:11px 14px;display:flex;align-items:flex-start;gap:11px;transition:all .18s;position:relative;}
.log-row:hover{border-color:var(--g200);background:var(--g100);}
.log-dot{width:8px;height:8px;border-radius:50%;background:var(--g500);margin-top:5px;flex-shrink:0;box-shadow:0 0 5px rgba(34,197,94,.5);}
.log-name{font-size:.83rem;font-weight:600;color:var(--txt);line-height:1.4;}
.log-desc{font-size:.71rem;color:var(--muted);margin-top:3px;line-height:1.55;}
.log-cal{font-size:.68rem;font-weight:700;background:var(--g50);border:1px solid var(--g200);color:var(--g700);padding:3px 9px;border-radius:8px;white-space:nowrap;flex-shrink:0;}
.del-btn{position:absolute;top:9px;right:10px;width:26px;height:26px;border-radius:8px;border:none;background:transparent;color:var(--muted2);cursor:pointer;font-size:.7rem;display:flex;align-items:center;justify-content:center;transition:all .18s;opacity:0;}
.log-row:hover .del-btn{opacity:1;}
.del-btn:hover{background:#fee2e2;color:#dc2626;}

/* ─── Add meal area ─── */
.add-area{border:2px dashed var(--muted2);border-radius:13px;padding:11px 14px;display:flex;align-items:center;gap:9px;cursor:pointer;transition:all .2s;color:var(--muted);}
.add-area:hover{border-color:var(--g300);background:var(--g50);color:var(--g600);}
.add-area-txt{font-size:.78rem;font-weight:500;}

/* ─── Scroll area ─── */
.scroll-box{max-height:320px;overflow-y:auto;display:flex;flex-direction:column;gap:8px;padding-right:2px;}
::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-thumb{background:var(--g200);border-radius:99px;}

/* ─── Section title ─── */
.stitle{font-family:'Nunito',sans-serif;font-size:1rem;font-weight:800;color:var(--txt);display:flex;align-items:center;gap:8px;}

/* ─── Badges ─── */
.badge-green{background:var(--g50);border:1px solid var(--g200);color:var(--g700);font-size:.63rem;font-weight:700;padding:3px 9px;border-radius:99px;white-space:nowrap;}
.badge-orange{background:#fff7ed;border:1px solid #fed7aa;color:#c2410c;font-size:.63rem;font-weight:700;padding:3px 9px;border-radius:99px;white-space:nowrap;}

/* ─── Buttons ─── */
.btn-green{background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;font-size:.72rem;font-weight:700;padding:8px 18px;border-radius:11px;display:inline-flex;align-items:center;gap:7px;text-decoration:none;font-family:'Kanit',sans-serif;transition:opacity .2s,box-shadow .2s;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(34,197,94,.28);}
.btn-green:hover{opacity:.88;box-shadow:0 6px 18px rgba(34,197,94,.38);}
.btn-ghost{border:1.5px solid var(--bdr);color:var(--sub);font-size:.7rem;font-weight:500;padding:7px 14px;border-radius:11px;display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-family:'Kanit',sans-serif;transition:all .18s;cursor:pointer;background:#fff;}
.btn-ghost:hover{border-color:var(--g400);color:var(--g700);background:var(--g50);}

/* ─── Modal ─── */
.modal-bg{position:fixed;inset:0;background:rgba(10,20,10,.4);backdrop-filter:blur(4px);z-index:200;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .22s;}
.modal-bg.open{opacity:1;pointer-events:all;}
.modal{background:#fff;border-radius:22px;padding:28px;width:100%;max-width:420px;box-shadow:0 24px 60px rgba(0,0,0,.18);transform:translateY(16px);transition:transform .25s cubic-bezier(.22,1,.36,1);}
.modal-bg.open .modal{transform:translateY(0);}
.modal-title{font-family:'Nunito',sans-serif;font-size:1.05rem;font-weight:800;color:var(--txt);margin-bottom:18px;display:flex;align-items:center;gap:9px;}
.field-input{width:100%;padding:10px 13px;background:var(--g50);border:1.5px solid var(--bdr);border-radius:12px;font-family:'Kanit',sans-serif;font-size:.84rem;color:var(--txt);outline:none;transition:border-color .18s;}
.field-input:focus{border-color:var(--g400);box-shadow:0 0 0 3px rgba(74,222,128,.12);}
.field-label{font-size:.72rem;font-weight:600;color:var(--sub);margin-bottom:5px;display:block;}
.meal-type-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:18px;}
.mt-option input{display:none;}
.mt-card{text-align:center;padding:9px 4px;border-radius:12px;border:2px solid var(--bdr);cursor:pointer;transition:all .18s;background:#fff;}
.mt-card:hover{border-color:var(--g300);background:var(--g50);}
.mt-option input:checked + .mt-card{border-color:var(--g500);background:var(--g50);box-shadow:0 0 0 3px rgba(34,197,94,.12);}
.mt-icon{font-size:1.3rem;display:block;margin-bottom:3px;}
.mt-lbl{font-size:.65rem;font-weight:600;color:var(--sub);}
.mt-option input:checked + .mt-card .mt-lbl{color:var(--g700);}

/* ─── Empty state ─── */
.empty{border:2px dashed var(--g200);border-radius:14px;text-align:center;padding:2rem 1rem;color:var(--muted);}

/* ── Responsive CSS (ปรับปรุงความสมดุลทุกหน้าจอ) ── */
.menu-toggle { display: none; width: 38px; height: 38px; border-radius: 11px; background: white; border: 1px solid var(--bdr); align-items: center; justify-content: center; color: var(--sub); font-size: 0.9rem; cursor: pointer; flex-shrink: 0; margin-right: 8px; }

@media (max-width: 1024px) {
  .sidebar { transform: translateX(-100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
  .sidebar.show { transform: translateX(0); }
  .page-wrap { margin-left: 0 !important; }
  .menu-toggle { display: flex; }
  .rv3 { grid-template-columns: 1fr !important; } 
}

@media (max-width: 768px) {
  main { padding: 1.5rem 1.2rem 3rem !important; }
  .topbar { padding: 0 1.5rem; }
  .rv2 { grid-template-columns: 1fr !important; } 
  .meal-type-grid { grid-template-columns: repeat(2, 1fr) !important; } 
  .card { padding: 20px !important; } 
}

@media (max-width: 480px) {
  .topbar { flex-wrap: wrap; height: auto; padding: 12px 1rem; gap: 10px; }
  .topbar > div:nth-child(3) { flex: 1; min-width: 0; } /* ให้ชื่อหน้าขยายและไม่ล้นกรอบ */
  .topbar > div:last-child { width: 100%; margin-left: 0 !important; margin-top: 4px; }
  .topbar > div:last-child .btn-green { width: 100%; justify-content: center; padding: 10px; font-size: .8rem; }
  
  .rv1 { flex-direction: column; align-items: flex-start !important; gap: 12px; }
  .date-nav { width: 100%; justify-content: center; flex-wrap: wrap; gap: 8px; }
  .date-nav .btn-ghost { width: 100%; justify-content: center; text-align: center; margin-top: 4px; }
  
  .ring-wrap { width: 90px; height: 90px; }
  .ring-wrap svg { width: 90px; height: 90px; }
  .ring-center span { font-size: 1.2rem !important; }
  
  .meal-header { flex-wrap: wrap; }
  .meal-header > div:last-child { width: 100%; justify-content: space-between; margin-left: 0 !important; margin-top: 8px; }
  
  /* ทำให้ปุ่มลบเมนูโชว์ตลอดเวลาบนมือถือ (เพราะไม่มี hover) */
  .del-btn { opacity: 1; background: var(--g50); border: 1px solid var(--bdr); }
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
      <div style="font-family:'Nunito',sans-serif;font-size:.95rem;font-weight:800;color:var(--txt);">บันทึกมื้ออาหาร</div>
      <div style="font-size:.7rem;color:var(--muted);">ติดตามแคลอรี่รายวันของคุณ</div>
    </div>
    <div style="margin-left:auto;display:flex;gap:9px;align-items:center;">
      <a href="ai_chef.php" class="btn-green">
        <i class="fas fa-robot" style="font-size:.65rem;"></i> เพิ่มด้วยเชฟ AI
      </a>
    </div>
  </header>

  <main style="padding:2rem 2.5rem 3.5rem;max-width:1240px;width:100%;">

    <div class="rv rv1" style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:1.8rem;gap:16px;flex-wrap:wrap;">
      <div>
        <p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">บันทึกการกิน</p><br>
        <h1 style="font-family:'Nunito',sans-serif;font-size:1.6rem;font-weight:800;color:var(--txt);line-height:1.1;">
          <?= $selected_date === $today ? 'วันนี้' : date('j F Y', strtotime($selected_date)) ?>
        </h1>
        <div style="height:3px;background:linear-gradient(90deg,var(--g500),transparent);border-radius:99px;width:50px;margin-top:10px;"></div>
      </div>
      <div class="date-nav">
        <a href="?date=<?= date('Y-m-d', strtotime($selected_date.' -1 day')) ?>" class="date-btn">
          <i class="fas fa-chevron-left"></i>
        </a>
        <div class="date-display"><?= date('d M', strtotime($selected_date)) ?></div>
        <?php if ($selected_date < $today): ?>
        <a href="?date=<?= date('Y-m-d', strtotime($selected_date.' +1 day')) ?>" class="date-btn">
          <i class="fas fa-chevron-right"></i>
        </a>
        <?php else: ?>
        <span class="date-btn" style="opacity:.3;cursor:not-allowed;"><i class="fas fa-chevron-right"></i></span>
        <?php endif; ?>
        <?php if ($selected_date !== $today): ?>
        <a href="?date=<?= $today ?>" class="btn-ghost" style="font-size:.68rem;padding:6px 12px;">กลับวันนี้</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="rv rv2" style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:2rem;">

      <div class="card" style="padding:26px;">
        <div style="position:relative;z-index:1;">
          <p style="font-size:.65rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);margin-bottom:16px;">
            สรุปแคลอรี่
            <?= $selected_date === $today ? '— วันนี้' : '— '.date('d/m', strtotime($selected_date)) ?>
          </p>
          <div style="display:flex;align-items:center;gap:22px;">
            <div class="ring-wrap">
              <svg viewBox="0 0 120 120" width="110" height="110">
                <defs>
                  <linearGradient id="gGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#4ade80"/>
                    <stop offset="100%" stop-color="#2dd4bf"/>
                  </linearGradient>
                  <linearGradient id="rGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#fca5a5"/>
                    <stop offset="100%" stop-color="#ef4444"/>
                  </linearGradient>
                </defs>
                <circle class="ring-bg" cx="60" cy="60" r="50"/>
                <circle class="ring-fill" cx="60" cy="60" r="50" style="stroke: url(#<?= $over > 0 ? 'rGrad' : 'gGrad' ?>);" />
              </svg>
              <div class="ring-center">
                <span style="font-family:'Nunito',sans-serif;font-size:1.4rem;font-weight:800;color:<?= $over > 0 ? '#ef4444' : 'var(--g600)' ?>;line-height:1;"><?= $cal_pct_raw ?><span style="font-size:.55rem;font-family:'Kanit',sans-serif;font-weight:400;color:var(--muted);">%</span></span>
                <span style="font-size:.54rem;color:var(--muted);margin-top:2px;"><?= $over > 0 ? 'เกินเป้า' : 'บรรลุแล้ว' ?></span>
              </div>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-family:'Nunito',sans-serif;font-size:1.85rem;font-weight:800;color:<?= $over > 0 ? '#ef4444' : 'var(--txt)' ?>;line-height:1;">
                <?= number_format($total_cal) ?>
                <span style="font-size:.75rem;font-weight:600;font-family:'Kanit',sans-serif;color:var(--muted);">kcal</span>
              </div>
              <p style="font-size:.7rem;color:var(--sub);margin-top:6px;">เป้าหมาย: <?= htmlspecialchars($goalTitle) ?> <span style="color:var(--g600);font-weight:700;"><?= number_format($targetCal) ?> kcal</span></p>
              <div style="height:5px;background:var(--bdr);border-radius:99px;overflow:hidden;margin-top:12px;">
                <div style="width:<?= $bar_width ?>%;height:100%;border-radius:99px;<?= $over > 0 ? 'background:linear-gradient(90deg, #fca5a5, #ef4444);box-shadow:0 0 8px rgba(239,68,68,.4);' : 'background:linear-gradient(90deg,var(--g400),var(--t400));box-shadow:0 0 8px rgba(74,222,128,.4);' ?>"></div>
              </div>
              <div style="display:flex;justify-content:space-between;margin-top:8px;">
                <span style="font-size:.66rem;color:var(--sub);">ทานไปแล้ว <span style="color:<?= $over > 0 ? '#ef4444' : 'var(--g600)' ?>;font-weight:600;"><?= number_format($rec_cal) ?></span> kcal</span>
                <?php if($over > 0): ?>
                  <span style="font-size:.66rem;color:var(--sub);">เกิน <span style="color:#ef4444;font-weight:700;">+<?= number_format($over) ?></span></span>
                <?php else: ?>
                  <span style="font-size:.66rem;color:var(--sub);">เหลือ <span style="color:var(--t600);font-weight:700;"><?= number_format($remaining) ?></span></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card" style="padding:22px;">
        <p style="font-size:.62rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);margin-bottom:16px;">7 วันที่ผ่านมา</p>
        <div style="display:flex;gap:8px;align-items:flex-end;height:80px;margin-bottom:10px;">
          <?php foreach ($week_data as $wd):
            $h   = $max_week > 0 ? max(6, round($wd['cal'] / $max_week * 56)) : 6;
            $cls = $wd['is_today'] ? 'today' : '';
            $cls .= $wd['is_sel'] ? ' sel' : '';
          ?>
          <div class="bar-col <?= trim($cls) ?>" onclick="window.location='?date=<?= $wd['date'] ?>'">
            <div class="bar-track">
              <div class="bar-fill" style="height:<?= $h ?>px;"></div>
            </div>
            <span class="bar-lbl"><?= $wd['label'] ?></span>
            <?php if ($wd['cal'] > 0): ?>
            <span style="font-size:.55rem;color:var(--muted);line-height:1;"><?= number_format($wd['cal']) ?></span>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
          <div style="display:flex;align-items:center;gap:5px;font-size:.65rem;color:var(--muted);">
            <div style="width:10px;height:10px;border-radius:3px;background:linear-gradient(to top,var(--g600),var(--g400));"></div>วันนี้
          </div>
          <div style="display:flex;align-items:center;gap:5px;font-size:.65rem;color:var(--muted);">
            <div style="width:10px;height:10px;border-radius:3px;background:linear-gradient(to top,var(--t600),var(--t400));"></div>วันที่เลือก
          </div>
          <div style="display:flex;align-items:center;gap:5px;font-size:.65rem;color:var(--muted);">
            <div style="width:10px;height:10px;border-radius:3px;background:var(--g200);"></div>วันอื่น
          </div>
          <div style="margin-left:auto;font-size:.65rem;color:var(--muted);">เป้า <span style="color:var(--g600);font-weight:600;"><?= number_format($targetCal) ?></span> kcal</div>
        </div>
      </div>
    </div>

    <div class="rv rv3" style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:start;">

      <div style="display:flex;flex-direction:column;gap:14px;">

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;">
          <h2 class="stitle"><i class="fas fa-utensils" style="color: #22c55e;"></i> มื้ออาหารจากสูตร</h2>
          <button class="btn-green" onclick="openModal()">
            <i class="fas fa-plus" style="font-size:.62rem;"></i> บันทึกจากสูตร
          </button>
        </div>

        <?php foreach ($meal_types as $mt_key => $mt): ?>
        <?php
          $mt_logs  = $grouped[$mt_key] ?? [];
          $mt_total = array_sum(array_column($mt_logs, 'calories'));
          $mt_pct   = $targetCal > 0 ? min(100, round($mt_total / $targetCal * 100)) : 0;
        ?>
        <div class="meal-section">
          <div class="meal-header">
            <div class="meal-icon-wrap" style="background:<?= $mt['color'] ?>18;border:1px solid <?= $mt['color'] ?>33;">
              <?= $mt['icon'] ?>
            </div>
            <div>
              <div class="meal-title"><?= $mt['label'] ?></div>
              <?php if ($mt_total > 0): ?>
              <div style="font-size:.65rem;color:var(--muted);margin-top:1px;"><?= $mt_total ?> kcal</div>
              <?php endif; ?>
            </div>
            <?php if ($mt_total > 0): ?>
            <div style="margin-left:auto;display:flex;align-items:center;gap:8px;">
              <div style="width:70px;">
                <div class="pbar">
                  <div class="pbar-fill" style="width:<?= $mt_pct ?>%;background:<?= $mt['color'] ?>;box-shadow:0 0 6px <?= $mt['color'] ?>66;"></div>
                </div>
              </div>
              <span style="font-size:.62rem;color:var(--muted);min-width:28px;"><?= $mt_pct ?>%</span>
            </div>
            <?php endif; ?>
          </div>

          <div class="meal-body">
            <?php foreach ($mt_logs as $log): ?>
            <div class="log-row" id="logrow-<?= $log['log_id'] ?>">
              <div class="log-dot" style="background:<?= $mt['color'] ?>;box-shadow:0 0 5px <?= $mt['color'] ?>66;"></div>
              <div style="flex:1;min-width:0;padding-right:28px;">
                <div class="log-name"><?= htmlspecialchars($log['title']) ?></div>
                <?php if (!empty($log['description'])): ?>
                <div class="log-desc"><?= htmlspecialchars(mb_strimwidth($log['description'], 0, 75, '…')) ?></div>
                <?php endif; ?>
                <div style="margin-top:6px;display:flex;align-items:center;gap:7px;flex-wrap:wrap;">
                  <span class="badge-green"><i class="bi bi-fire" style="color: #ff5722;"></i> <?= number_format($log['calories']) ?> kcal</span>
                  <span style="font-size:.62rem;color:var(--muted2);">
                    <?= date('H:i', strtotime($log['logged_at'])) ?>
                  </span>
                </div>
              </div>
              <button class="del-btn" onclick="deleteLog(<?= $log['log_id'] ?>, 'logrow-<?= $log['log_id'] ?>')" title="ลบ">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <?php endforeach; ?>

            <div class="add-area" onclick="openModal('<?= $mt_key ?>')">
              <div style="width:26px;height:26px;border-radius:8px;background:var(--g50);border:1.5px dashed var(--muted2);display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-plus" style="font-size:.65rem;"></i>
              </div>
              <span class="add-area-txt">เพิ่ม<?= $mt['label'] ?></span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div style="display:flex;flex-direction:column;gap:1.2rem;">

        <div class="card rv rv4" style="overflow:hidden;padding:0;">
          <div style="padding:14px 18px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--bdr);background:var(--g50);">
            <div class="stitle" style="font-size:.88rem;"><i class="fas fa-robot" style="color: #22c55e;"></i> เมนูจากเชฟ AI</div>
            <div style="display:flex;align-items:center;gap:6px;">
              <span style="font-size:.65rem;color:var(--muted);font-weight:600;">(ไม่รวมในแคลอรี่)</span>
            </div>
          </div>

          <div style="padding:14px;">
            <?php if (count($ai_menus) > 0): ?>
            <div class="scroll-box">
              <?php foreach ($ai_menus as $menu): ?>
              <div class="log-row" id="aimenu-<?= $menu['id'] ?>">
                <div class="log-dot"></div>
                <div style="flex:1;min-width:0;padding-right:24px;">
                  <div class="log-name"><?= htmlspecialchars($menu['menu_name']) ?></div>
                  <?php if (!empty($menu['description'])): ?>
                  <div class="log-desc"><?= htmlspecialchars(mb_strimwidth($menu['description'],0,70,'…')) ?></div>
                  <?php endif; ?>
                  <div style="margin-top:5px;display:flex;align-items:center;gap:7px;">
                    <span class="badge-green"><i class="bi bi-fire" style="color: #ff5722;"></i> <?= number_format($menu['calories']) ?> kcal</span>
                    <span style="font-size:.6rem;color:var(--muted2);"><?= date('H:i', strtotime($menu['created_at'])) ?></span>
                  </div>
                </div>
                <button class="del-btn" onclick="deleteAiMenu(<?= $menu['id'] ?>, 'aimenu-<?= $menu['id'] ?>')" title="ลบ">
                  <i class="fas fa-times"></i>
                </button>
              </div>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty">
              <div style="font-size:2rem;opacity:.2;margin-bottom:8px;"><i class="fas fa-robot" style="color: #22c55e;"></i></div>
              <p style="font-size:.78rem;font-weight:500;margin-bottom:3px;">ยังไม่มีเมนูจาก AI</p>
              <p style="font-size:.7rem;color:var(--muted);">บันทึกเมนูจากหน้าเชฟ AI</p>
            </div>
            <?php endif; ?>
          </div>

          <div style="border-top:1px solid var(--bdr);padding:10px 16px;background:var(--g50);">
            <a href="ai_chef.php" class="btn-ghost" style="width:100%;justify-content:center;font-size:.68rem;">
              <i class="fas fa-robot" style="font-size:.6rem;"></i> ไปที่เชฟ AI
            </a>
          </div>
        </div>

        <div class="card rv rv5" style="padding:20px;">
          <p style="font-size:.62rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);margin-bottom:14px;">สถิติวันนี้</p>
          <div style="display:flex;flex-direction:column;gap:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-size:.78rem;color:var(--sub);display:flex;align-items:center;gap:7px;">
                <span style="width:26px;height:26px;border-radius:8px;background:var(--g50);border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;font-size:.72rem;"><i class="fas fa-robot" style="color: #22c55e;"></i></span>เมนู AI แนะนำ
              </span>
              <span style="font-family:'Nunito',sans-serif;font-size:.88rem;font-weight:800;color:var(--muted);"><?= count($ai_menus) ?> เมนู</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-size:.78rem;color:var(--sub);display:flex;align-items:center;gap:7px;">
                <span style="width:26px;height:26px;border-radius:8px;background:var(--g50);border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;font-size:.72rem;"><i class="bi bi-clipboard-minus-fill" style="color: #22c55e;"></i></span>ที่ทานจริง
              </span>
              <span style="font-family:'Nunito',sans-serif;font-size:.88rem;font-weight:800;color:var(--t600);"><?= count($recipe_logs) ?> เมนู</span>
            </div>
            <div style="height:1px;background:var(--bdr);"></div>
            <div style="background:var(--g50);border:1px solid var(--g200);border-radius:12px;padding:10px 13px;display:flex;justify-content:space-between;align-items:center;">
              <span style="font-size:.72rem;color:var(--g700);font-weight:600;">แคลอรี่ที่ทานไป</span>
              <span style="font-family:'Nunito',sans-serif;font-size:1rem;font-weight:800;color:var(--g600);"><?= number_format($total_cal) ?> kcal</span>
            </div>

            <?php if ($remaining > 0): ?>
            <div style="font-size:.7rem;color:var(--muted);text-align:center;">
              เหลือช่องว่าง <span style="color:var(--t600);font-weight:700;"><?= number_format($remaining) ?> kcal</span> ก่อนถึงเป้าหมาย
            </div>
            <?php elseif ($over > 0): ?>
            <div style="font-size:.7rem;color:#ef4444;font-weight:600;text-align:center;background:#fee2e2;border-radius:10px;padding:7px;">
              ระวัง! คุณทานเกินเป้าหมายไป <?= number_format($over) ?> kcal
            </div>
            <?php else: ?>
            <div style="font-size:.7rem;color:var(--g600);font-weight:600;text-align:center;background:var(--g50);border-radius:10px;padding:7px;">
              ถึงเป้าหมายแล้วพอดีเป๊ะ!
            </div>
            <?php endif; ?>

          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<div class="modal-bg" id="addModal" onclick="closeModal(event)">
  <div class="modal">
    <div class="modal-title">
      <span style="width:34px;height:34px;border-radius:10px;background:var(--g50);border:1px solid var(--g200);display:flex;align-items:center;justify-content:center;font-size:.9rem;">📋</span>
      เพิ่มจากสูตรอาหาร
    </div>

    <label class="field-label" style="margin-bottom:8px;">มื้ออาหาร</label>
    <div class="meal-type-grid" style="margin-bottom:18px;">
      <?php foreach ($meal_types as $k => $m): ?>
      <label class="mt-option">
        <input type="radio" name="meal_type_sel" value="<?= $k ?>" <?= $k==='lunch'?'checked':'' ?>>
        <div class="mt-card">
          <span class="mt-icon"><?= $m['icon'] ?></span>
          <span class="mt-lbl"><?= $m['label'] ?></span>
        </div>
      </label>
      <?php endforeach; ?>
    </div>

    <label class="field-label">ค้นหาสูตรอาหาร</label>
    <div style="position:relative;margin-bottom:12px;">
      <i class="fas fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.75rem;"></i>
      <input type="text" id="recipeSearch" class="field-input" placeholder="พิมพ์ชื่อเมนู..." style="padding-left:35px;" oninput="filterRecipes(this.value)">
    </div>

    <div id="recipeList" style="max-height:240px;overflow-y:auto;display:flex;flex-direction:column;gap:6px;margin-bottom:18px;">
      
      <?php foreach ($all_ai_menus as $ai): ?>
      <div class="log-row recipe-option" data-name="<?= htmlspecialchars(strtolower($ai['menu_name'])) ?> ai ไอ"
           onclick="selectRecipe('ai_<?= $ai['id'] ?>', '<?= htmlspecialchars(addslashes($ai['menu_name'])) ?>', <?= $ai['calories'] ?>)"
           style="cursor:pointer;user-select:none;background:#f0fdfa;border-color:#5eead4;" id="ropt-ai_<?= $ai['id'] ?>">
        <div class="log-dot" style="background:#0d9488;"></div>
        <div style="flex:1;min-width:0;">
          <div class="log-name"><?= htmlspecialchars($ai['menu_name']) ?> <span style="font-size:0.6rem;color:#0d9488;border:1px solid #5eead4;padding:1px 4px;border-radius:4px;margin-left:4px;">จาก AI</span></div>
        </div>
        <span class="badge-green" style="background:#ccfbf1;color:#0f766e;border-color:#99f6e4;"><i class="bi bi-fire" style="color: #ff5722;"></i> <?= $ai['calories'] ?> kcal</span>
      </div>
      <?php endforeach; ?>

      <?php foreach ($all_recipes as $r): ?>
      <div class="log-row recipe-option" data-name="<?= htmlspecialchars(strtolower($r['title'])) ?>"
           onclick="selectRecipe('<?= $r['id'] ?>', '<?= htmlspecialchars(addslashes($r['title'])) ?>', <?= $r['calories'] ?>)"
           style="cursor:pointer;user-select:none;" id="ropt-<?= $r['id'] ?>">
        <div class="log-dot"></div>
        <div style="flex:1;min-width:0;">
          <div class="log-name"><?= htmlspecialchars($r['title']) ?></div>
        </div>
        <span class="badge-green"><i class="bi bi-fire" style="color: #ff5722;"></i> <?= $r['calories'] ?> kcal</span>
      </div>
      <?php endforeach; ?>
    </div>

    <div id="selectedDisplay" style="display:none;background:var(--g50);border:2px solid var(--g300);border-radius:13px;padding:12px 14px;margin-bottom:16px;">
      <div style="font-size:.7rem;color:var(--g600);font-weight:700;margin-bottom:4px;">เมนูที่เลือก</div>
      <div id="selectedName" style="font-size:.88rem;font-weight:600;color:var(--txt);"></div>
      <div id="selectedCal"  style="font-size:.7rem;color:var(--muted);margin-top:2px;"></div>
    </div>

    <div style="display:flex;gap:9px;">
      <button class="btn-ghost" onclick="closeModal()" style="flex:1;justify-content:center;">ยกเลิก</button>
      <button class="btn-green" onclick="submitLog()" id="submitLogBtn" style="flex:1;justify-content:center;opacity:.5;pointer-events:none;">
        <i class="fas fa-plus" style="font-size:.62rem;"></i> บันทึก
      </button>
    </div>
  </div>
</div>

<script>
let selectedRecipeId  = null;
let selectedMealType  = 'lunch';

/* ── Modal ── */
function openModal(preset) {
  if (preset) {
    document.querySelectorAll('[name="meal_type_sel"]').forEach(r => r.checked = r.value === preset);
    selectedMealType = preset;
  }
  document.getElementById('addModal').classList.add('open');
  document.getElementById('recipeSearch').focus();
}
function closeModal(e) {
  if (!e || e.target === document.getElementById('addModal')) {
    document.getElementById('addModal').classList.remove('open');
  }
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

/* ── Recipe selection ── */
function selectRecipe(id, name, cal) {
  selectedRecipeId = id;
  document.querySelectorAll('.recipe-option').forEach(el => {
    el.style.borderColor = el.id === 'ropt-'+id ? 'var(--g400)' : '';
    el.style.background  = el.id === 'ropt-'+id ? 'var(--g100)' : '';
  });
  document.getElementById('selectedDisplay').style.display = 'block';
  document.getElementById('selectedName').textContent = name;
  document.getElementById('selectedCal').innerHTML  = '<i class="bi bi-fire" style="color: #ff5722;"></i> ' + cal.toLocaleString() + ' kcal';
  const btn = document.getElementById('submitLogBtn');
  btn.style.opacity = '1';
  btn.style.pointerEvents = 'all';
}

function filterRecipes(q) {
  const lower = q.toLowerCase();
  document.querySelectorAll('.recipe-option').forEach(el => {
    el.style.display = el.dataset.name.includes(lower) ? '' : 'none';
  });
}

document.querySelectorAll('[name="meal_type_sel"]').forEach(r => {
  r.addEventListener('change', () => selectedMealType = r.value);
});

/* ── Submit log ── */
async function submitLog() {
  if (!selectedRecipeId) return;
  const mt   = document.querySelector('[name="meal_type_sel"]:checked')?.value || 'lunch';
  const btn  = document.getElementById('submitLogBtn');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
  btn.style.opacity = '.7';

  const fd = new FormData();
  fd.append('action', 'log_recipe');
  fd.append('recipe_id', selectedRecipeId);
  fd.append('meal_type', mt);
  
  const urlParams = new URLSearchParams(window.location.search);
  const selectedDate = urlParams.get('date') || '<?= $today ?>';
  fd.append('log_date', selectedDate);

  try {
    const res  = await fetch('', { method:'POST', body: fd });
    const data = await res.json();
    if (data.status === 'success') {
      closeModal();
      location.reload();
    } else {
      alert('เกิดข้อผิดพลาด กรุณาลองใหม่');
      btn.innerHTML = '<i class="fas fa-plus"></i> บันทึก';
      btn.style.opacity = '1';
    }
  } catch(e) {
    alert('เกิดข้อผิดพลาด');
  }
}

/* ── Delete recipe log ── */
async function deleteLog(logId, rowId) {
  if (!confirm('ลบรายการนี้ออกจากบันทึกใช่ไหม?')) return;
  const fd = new FormData();
  fd.append('action', 'delete_log');
  fd.append('log_id', logId);
  const res  = await fetch('', { method:'POST', body: fd });
  const data = await res.json();
  if (data.status === 'success') {
    const el = document.getElementById(rowId);
    el.style.opacity = '0'; el.style.transform = 'translateX(10px)'; el.style.transition = 'all .25s';
    setTimeout(() => { el.remove(); updateTotals(); }, 260);
  }
}

/* ── Delete AI menu ── */
async function deleteAiMenu(menuId, rowId) {
  if (!confirm('ลบเมนูนี้ออกจากบันทึกใช่ไหม?')) return;
  const fd = new FormData();
  fd.append('action', 'delete_ai_menu');
  fd.append('menu_id', menuId);
  const res  = await fetch('', { method:'POST', body: fd });
  const data = await res.json();
  if (data.status === 'success') {
    const el = document.getElementById(rowId);
    el.style.opacity = '0'; el.style.transform = 'translateX(10px)'; el.style.transition = 'all .25s';
    setTimeout(() => { el.remove(); }, 260);
  }
}

function updateTotals() { location.reload(); }
</script>
</body>
</html>