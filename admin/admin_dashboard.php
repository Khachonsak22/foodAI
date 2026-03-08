<?php
session_start();
include '../config/connect.php';

// Check if user is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get admin info
$admin_stmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
$admin_stmt->bind_param("i", $user_id);
$admin_stmt->execute();
$admin_data = $admin_stmt->get_result()->fetch_assoc();

// Verify @admin.com domain
if (!str_ends_with($admin_data['email'], '@admin.com')) {
    header("Location: ../pages/dashboard.php");
    exit();
}

/* ══════════════════════════════════════════════════════════════════
   STATISTICS & ANALYTICS
   ══════════════════════════════════════════════════════════════════ */

// Total users
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE email NOT LIKE '%@admin.com'")->fetch_assoc()['count'];

// Total recipes
$total_recipes = $conn->query("SELECT COUNT(*) as count FROM recipes")->fetch_assoc()['count'];

// Total ingredients
$total_ingredients = $conn->query("SELECT COUNT(*) as count FROM ingredients")->fetch_assoc()['count'];

// Active users (last 7 days)
$active_users = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM meal_logs WHERE logged_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['count'];

// Total AI chats
$total_chats = $conn->query("SELECT COUNT(*) as count FROM chat_logs WHERE sender = 'user'")->fetch_assoc()['count'];

// ==========================================
// 1. โรคประจำตัวที่พบบ่อย (ดึงและแยกข้อมูลจริงตามคอมม่า)
// ==========================================
$hc_query = $conn->query("
    SELECT health_conditions 
    FROM health_profiles 
    WHERE health_conditions IS NOT NULL 
    AND health_conditions != ''
");

$hc_counts = [];
while ($row = $hc_query->fetch_assoc()) {
    $conditions = explode(',', $row['health_conditions']); // จับแยกด้วยลูกน้ำ
    foreach ($conditions as $c) {
        $c = trim($c);
        // กรองคำที่แปลว่า "ไม่มี" ทิ้ง เพื่อให้แสดงเฉพาะโรคจริงๆ ที่มีความหมาย
        if ($c !== '' && !in_array(mb_strtolower($c, 'UTF-8'), ['none', 'ไม่มี', 'ไม่มีโรคประจำตัว', 'ไม่มีโรค', '-'])) {
            if (!isset($hc_counts[$c])) {
                $hc_counts[$c] = 0;
            }
            $hc_counts[$c]++;
        }
    }
}
arsort($hc_counts); // เรียงลำดับจากคนเป็นเยอะสุดไปน้อยสุด

$health_conditions = [];
foreach ($hc_counts as $name => $count) {
    $health_conditions[] = ['health_conditions' => $name, 'count' => $count];
    if (count($health_conditions) >= 5) break; // ดึงมาแค่ 5 อันดับแรก
}

// Recent users (last 10)
$recent_users = $conn->query("
    SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.created_at,
           (SELECT COUNT(*) FROM meal_logs WHERE user_id = u.id) as meal_count
    FROM users u
    WHERE u.email NOT LIKE '%@admin.com'
    ORDER BY u.created_at DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// ==========================================
// 2. เมนูยอดนิยม (ดึงจากยอด view_count ในตาราง recipes)
// ==========================================
$popular_recipes = $conn->query("
    SELECT id, title, calories, view_count 
    FROM recipes 
    ORDER BY view_count DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — FoodAI</title>
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
  --sb-w:260px;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,#c8e6c9 1px,transparent 1px);background-size:28px 28px;opacity:.35;}

/* Sidebar */
.sidebar{width:var(--sb-w);min-height:100vh;background:#fff;border-right:1px solid #e5ede6;display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;z-index:100;box-shadow:4px 0 24px rgba(34,197,94,.06);}
.sb-logo{padding:24px 22px 20px;border-bottom:1px solid #e5ede6;display:flex;align-items:center;gap:11px;}
.sb-logo-icon{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#ef4444,#dc2626);display:flex;align-items:center;justify-content:center;font-size:1.2rem;box-shadow:0 4px 12px rgba(239,68,68,.35);}
.sb-logo-text{font-family:'Nunito',sans-serif;font-size:1.18rem;font-weight:800;color:#dc2626;letter-spacing:-.02em;line-height:1;}
.sb-logo-sub{font-size:.6rem;font-weight:600;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin-top:2px;}

.sb-nav{padding:6px 12px;display:flex;flex-direction:column;gap:2px;flex:1;overflow-y:auto;}
.nav-item{display:flex;align-items:center;gap:11px;padding:11px 14px;border-radius:12px;text-decoration:none;color:var(--sub);font-size:.82rem;font-weight:500;transition:all .18s;}
.nav-item:hover{background:#fef2f2;color:#dc2626;}
.nav-item.active{background:#fef2f2;color:#dc2626;font-weight:600;}
.nav-item.active .ni{background:#dc2626;color:#fff;}
.ni{width:34px;height:34px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;transition:all .18s;color:#dc2626;}
.nav-item:hover .ni{background:#fecaca;}

.sb-user{border-top:1px solid #e5ede6;padding:16px;background:#fef2f2;}
.sb-av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#ef4444,#dc2626);display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:800;color:#fff;font-family:'Nunito',sans-serif;}

.page-wrap{margin-left:var(--sb-w);flex:1;position:relative;z-index:1;min-width:0;}
.topbar{height:66px;background:rgba(255,255,255,.95);backdrop-filter:blur(12px);border-bottom:1px solid #e5ede6;display:flex;align-items:center;padding:0 2.5rem;gap:14px;position:sticky;top:0;z-index:50;}

@keyframes slideUp{from{opacity:0;transform:translateY(18px);}to{opacity:1;transform:translateY(0);}}
.rv{opacity:0;animation:slideUp .5s cubic-bezier(.22,1,.36,1) forwards;}
.rv1{animation-delay:.04s;}.rv2{animation-delay:.10s;}.rv3{animation-delay:.17s;}.rv4{animation-delay:.24s;}

.card{background:#fff;border:1px solid var(--bdr);border-radius:18px;padding:22px;transition:box-shadow .2s;}
.card:hover{box-shadow:0 8px 24px rgba(34,197,94,.08);}

/* Stat card */
.stat-card{background:#fff;border:1px solid var(--bdr);border-radius:16px;padding:20px;transition:all .2s;cursor:pointer;}
.stat-card:hover{border-color:var(--g300);box-shadow:0 6px 20px rgba(34,197,94,.1);transform:translateY(-2px);}
.stat-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:12px;}
.stat-val{font-family:'Nunito',sans-serif;font-size:1.8rem;font-weight:800;color:var(--txt);line-height:1;}
.stat-lbl{font-size:.75rem;color:var(--muted);margin-top:6px;}

/* Table */
.table-wrap{overflow-x:auto;}
.table{width:100%;font-size:.82rem;}
.table th{text-align:left;padding:12px;background:var(--g50);color:var(--sub);font-weight:600;border-bottom:2px solid var(--g200);}
.table td{padding:12px;border-bottom:1px solid var(--bdr);}
.table tr:hover{background:var(--g50);}

.badge{font-size:.68rem;font-weight:700;padding:4px 10px;border-radius:8px;display:inline-block;}

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

::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-thumb{background:var(--g200);border-radius:99px;}

.tb-date {
  font-size: .72rem; color: var(--muted); font-weight: 400;
  display: flex; align-items: center; gap: 6px;
  margin-left: auto;
  white-space: nowrap;
}
</style>
</head>
<body>

<?php include '../includes/sidebar_admin.php' ?>

<div class="page-wrap">
  
  <header class="topbar">
    <button class="hamburger" onclick="toggleSidebar()">
      <i class="fas fa-bars"></i>
    </button>
    <div>
      <div style="font-family:'Nunito',sans-serif;font-size:1.1rem;font-weight:800;color:var(--txt);">Dashboard</div>
      <div style="font-size:.72rem;color:var(--muted);">ภาพรวมระบบ FoodAI</div>
    </div>
    <div class="tb-date">
      <i class="fas fa-calendar-days" style="color:var(--g500);font-size:.75rem;"></i>
      <?php echo date('l, j F Y'); ?>
    </div>
  </header>
  
  <main style="padding:2rem 2.5rem 3.5rem;">
    
    <div class="rv rv1" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;margin-bottom:2rem;">
      
      <div class="stat-card">
        <div class="stat-icon" style="background:#eff6ff;border:1px solid #bfdbfe;"><i class="bi bi-people-fill" style="color: #4447d2;"></i></div>
        <div class="stat-val" style="color:#2563eb;"><?= number_format($total_users) ?></div>
        <div class="stat-lbl">ผู้ใช้ทั้งหมด</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon" style="background:#f0fdf4;border:1px solid var(--g200);"><i class="bi bi-book-half" style="color: #22c55e;"></i></div>
        <div class="stat-val" style="color:var(--g600);"><?= number_format($total_recipes) ?></div>
        <div class="stat-lbl">สูตรอาหาร</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon" style="background:#fef3c7;border:1px solid #fde68a;"><i class="fa-solid fa-carrot" style="color: #ff5722;"></i></div>
        <div class="stat-val" style="color:#ca8a04;"><?= number_format($total_ingredients) ?></div>
        <div class="stat-lbl">วัตถุดิบ</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon" style="background:#f0fdfa;border:1px solid #99f6e4;"><i class="fas fa-check-square" style="color: #22c55e;"></i></div>
        <div class="stat-val" style="color:var(--t600);"><?= number_format($active_users) ?></div>
        <div class="stat-lbl">Active (7 วัน)</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon" style="background:#fdf4ff;border:1px solid #f0abfc;"><i class="bi bi-chat-dots-fill" style="color: #df6df3;"></i></div>
        <div class="stat-val" style="color:#c026d3;"><?= number_format($total_chats) ?></div>
        <div class="stat-lbl">AI Chats</div>
      </div>
      
    </div>
    
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:2rem;">
      
      <div class="rv rv2 card">
        <h2 style="font-family:'Nunito',sans-serif;font-size:1rem;font-weight:800;color:var(--txt);margin-bottom:16px;">
          <i class="fas fa-user-plus" style="color:var(--g500);margin-right:8px;"></i> ผู้ใช้ล่าสุด
        </h2>
        
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Meals</th>
                <th>วันที่สมัคร</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_users as $u): ?>
              <tr>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td style="font-size:.75rem;color:var(--muted);"><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="badge" style="background:var(--g50);color:var(--g700);"><?= $u['meal_count'] ?></span></td>
                <td style="font-size:.75rem;"><?= date('j M Y', strtotime($u['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      
      <div class="rv rv2 card">
        <h2 style="font-family:'Nunito',sans-serif;font-size:1rem;font-weight:800;color:var(--txt);margin-bottom:16px;">
          <i class="fas fa-fire" style="color:#f97316;margin-right:8px;"></i> เมนูยอดนิยม
        </h2>
        
        <div style="display:flex;flex-direction:column;gap:12px;">
          <?php foreach ($popular_recipes as $idx => $r): ?>
          <div style="background:var(--g50);border:1px solid var(--g200);border-radius:12px;padding:12px;display:flex;align-items:center;gap:12px;">
            <div style="font-family:'Nunito',sans-serif;font-size:1.2rem;font-weight:800;color:var(--g600);min-width:32px;">
              #<?= $idx + 1 ?>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:.85rem;font-weight:600;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <?= htmlspecialchars($r['title']) ?>
              </div>
              <div style="font-size:.72rem;color:var(--muted);margin-top:2px;">
                <?= $r['calories'] ?> kcal • <?= $r['view_count'] ?> ครั้ง
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      
    </div>
    
    <div class="rv rv3 card">
      <h2 style="font-family:'Nunito',sans-serif;font-size:1rem;font-weight:800;color:var(--txt);margin-bottom:16px;">
        <i class="fas fa-heartbeat" style="color:#dc2626;margin-right:8px;"></i> โรคประจำตัวที่พบบ่อย
      </h2>
      
      <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <?php foreach ($health_conditions as $hc): ?>
        <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:11px;padding:10px 16px;">
          <div style="font-size:.85rem;font-weight:600;color:#ea580c;"><?= htmlspecialchars($hc['health_conditions']) ?></div>
          <div style="font-size:.7rem;color:#9a3412;margin-top:3px;"><?= $hc['count'] ?> คน</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    
  </main>
</div>

<script>
function toggleSidebar() {
  document.querySelector('.sidebar').classList.toggle('open');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(e) {
  if (window.innerWidth <= 768) {
    const sidebar = document.querySelector('.sidebar');
    const hamburger = document.querySelector('.hamburger');
    if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
      sidebar.classList.remove('open');
    }
  }
});
</script>

</body>
</html>