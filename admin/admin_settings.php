<?php
session_start();
include '../config/connect.php';

// ตรวจสอบเมื่อมีการกดปุ่ม "บันทึกการตั้งค่า"
if(isset($_POST['save_api_settings'])) {
    $new_api_key = $_POST['api_key'];
    $new_api_model = $_POST['api_model'];
    
    // อัปเดตข้อมูลลงฐานข้อมูล
    $update_stmt = $conn->prepare("UPDATE system_settings SET api_key = :api_key, api_model = :api_model WHERE id = 1");
    $update_stmt->bindParam(':api_key', $new_api_key);
    $update_stmt->bindParam(':api_model', $new_api_model);
    $update_stmt->execute();
    
    echo "<script>alert('อัปเดต API Key และ Model เรียบร้อยแล้ว!'); window.location.href='admin_settings.php';</script>";
}

// ดึงข้อมูลปัจจุบันมาแสดงโชว์ในช่องกรอก
$stmt = $conn->prepare("SELECT api_key, api_model FROM system_settings WHERE id = 1");
$stmt->execute();
$setting = $stmt->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit(); }
$admin_stmt = $conn->prepare("SELECT email, first_name FROM users WHERE id = ?");
$admin_stmt->bind_param("i", $_SESSION['user_id']);
$admin_stmt->execute();
$admin_data = $admin_stmt->get_result()->fetch_assoc();
if (!str_ends_with($admin_data['email'], '@admin.com')) { header("Location: ../pages/dashboard.php"); exit(); }

$success_msg = '';
$error_msg = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'clear_logs') {
        $days = (int)($_POST['days'] ?? 30);
        $stmt = $conn->prepare("DELETE FROM chat_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->bind_param("i", $days);
        if ($stmt->execute()) {
            $success_msg = "ลบ logs เก่ากว่า $days วันแล้ว (ลบไป " . $stmt->affected_rows . " รายการ)";
        } else {
            $error_msg = "เกิดข้อผิดพลาด";
        }
    }
    
    if ($action === 'optimize_db') {
        $conn->query("OPTIMIZE TABLE users, recipes, ingredients, meal_logs, chat_logs");
        $success_msg = "ปรับปรุงฐานข้อมูลเรียบร้อย";
    }
}

// Get system info
$db_size = $conn->query("
    SELECT 
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
    FROM information_schema.TABLES
    WHERE table_schema = DATABASE()
")->fetch_assoc()['size_mb'];

$table_counts = [
    'users' => $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'],
    'recipes' => $conn->query("SELECT COUNT(*) as c FROM recipes")->fetch_assoc()['c'],
    'ingredients' => $conn->query("SELECT COUNT(*) as c FROM ingredients")->fetch_assoc()['c'],
    'meal_logs' => $conn->query("SELECT COUNT(*) as c FROM meal_logs")->fetch_assoc()['c'],
    'chat_logs' => $conn->query("SELECT COUNT(*) as c FROM chat_logs")->fetch_assoc()['c'],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--g50:#f0fdf4;--g200:#bbf7d0;--g500:#22c55e;--g600:#16a34a;--bg:#f5f8f5;--bdr:#e8f0e9;--txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;--sb-w:260px;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,#c8e6c9 1px,transparent 1px);background-size:28px 28px;opacity:.35;}
.sidebar{width:var(--sb-w);min-height:100vh;background:#fff;border-right:1px solid #e5ede6;display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;z-index:100;}
.sb-logo{padding:24px 22px 20px;border-bottom:1px solid #e5ede6;display:flex;align-items:center;gap:11px;}
.sb-logo-icon{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#ef4444,#dc2626);display:flex;align-items:center;justify-content:center;font-size:1.2rem;}
.sb-logo-text{font-family:'Nunito',sans-serif;font-size:1.18rem;font-weight:800;color:#dc2626;}
.sb-nav{padding:6px 12px;display:flex;flex-direction:column;gap:2px;flex:1;}
.nav-item{display:flex;align-items:center;gap:11px;padding:11px 14px;border-radius:12px;text-decoration:none;color:var(--sub);font-size:.82rem;font-weight:500;}
.nav-item:hover{background:#fef2f2;color:#dc2626;}
.nav-item.active{background:#fef2f2;color:#dc2626;font-weight:600;}
.nav-item.active .ni{background:#dc2626;color:#fff;}
.ni{width:34px;height:34px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;display:flex;align-items:center;justify-content:center;font-size:.8rem;color:#dc2626;}
.page-wrap{margin-left:var(--sb-w);flex:1;position:relative;z-index:1;min-width:0;}
.topbar{height:66px;background:rgba(255,255,255,.95);backdrop-filter:blur(12px);border-bottom:1px solid #e5ede6;display:flex;align-items:center;padding:0 2.5rem;gap:14px;position:sticky;top:0;z-index:50;}
.card{background:#fff;border:1px solid var(--bdr);border-radius:18px;padding:22px;margin-bottom:20px;}
.setting-item{padding:18px;border-bottom:1px solid var(--bdr);display:flex;justify-content:space-between;align-items:center;gap:20px;}
.setting-item:last-child{border-bottom:none;}
.btn{padding:8px 16px;border-radius:10px;font-size:.78rem;font-weight:600;cursor:pointer;border:none;}
.hamburger{display:none;width:40px;height:40px;border-radius:10px;background:#fff;border:1.5px solid var(--bdr);align-items:center;justify-content:center;cursor:pointer;}
@media (max-width: 1024px){
  .sidebar{width:70px;}
  .sb-logo-text,.nav-item span:not(.ni){display:none;}
  .sb-logo{justify-content:center;padding:20px 10px;}
  .nav-item{justify-content:center;padding:10px;}
  .page-wrap{margin-left:70px;}
}
@media (max-width: 768px){
  .hamburger{display:flex;}
  .sidebar{transform:translateX(-100%);width:var(--sb-w);transition:transform .3s;}
  .sidebar.open{transform:translateX(0);}
  .page-wrap{margin-left:0;}
  .topbar{padding:0 1rem;height:60px;}
  .card{padding:16px;}
  .setting-item{flex-direction:column;align-items:flex-start;}
}
</style>
</head>
<body>
  
<?php include '../includes/sidebar_admin.php' ?>

<div class="page-wrap">
  <header class="topbar">
    <button class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('open')">
      <i class="fas fa-bars"></i>
    </button>
    <div>
      <div style="font-family:'Nunito',sans-serif;font-size:1.1rem;font-weight:800;">Settings</div>
      <div style="font-size:.72rem;color:var(--muted);">ตั้งค่าระบบ</div>
    </div>
  </header>
  <main style="padding:2rem 2.5rem;max-width:900px;">
    <?php if ($success_msg): ?>
    <div style="background:#f0fdf4;border:1.5px solid var(--g200);color:var(--g600);padding:14px 18px;border-radius:13px;margin-bottom:20px;font-size:.82rem;">
      ✅ <?= htmlspecialchars($success_msg) ?>
    </div>
    <?php endif; ?>
    
    <div class="card">
      <h2 style="font-family:'Nunito',sans-serif;font-size:1.1rem;font-weight:800;margin-bottom:16px;">💾 Database Info</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;background:var(--g50);padding:16px;border-radius:12px;margin-bottom:16px;">
        <div><div style="font-size:.7rem;color:var(--muted);">ขนาดฐานข้อมูล</div><div style="font-size:1.1rem;font-weight:700;color:var(--g600);"><?= $db_size ?> MB</div></div>
        <div><div style="font-size:.7rem;color:var(--muted);">Users</div><div style="font-size:1.1rem;font-weight:700;"><?= number_format($table_counts['users']) ?></div></div>
        <div><div style="font-size:.7rem;color:var(--muted);">Recipes</div><div style="font-size:1.1rem;font-weight:700;"><?= number_format($table_counts['recipes']) ?></div></div>
        <div><div style="font-size:.7rem;color:var(--muted);">Ingredients</div><div style="font-size:1.1rem;font-weight:700;"><?= number_format($table_counts['ingredients']) ?></div></div>
        <div><div style="font-size:.7rem;color:var(--muted);">Meal Logs</div><div style="font-size:1.1rem;font-weight:700;"><?= number_format($table_counts['meal_logs']) ?></div></div>
        <div><div style="font-size:.7rem;color:var(--muted);">Chat Logs</div><div style="font-size:1.1rem;font-weight:700;"><?= number_format($table_counts['chat_logs']) ?></div></div>
      </div>
      <div class="setting-item">
        <div>
          <div style="font-size:.88rem;font-weight:600;">Optimize Database</div>
          <div style="font-size:.72rem;color:var(--muted);margin-top:4px;">ปรับปรุงประสิทธิภาพฐานข้อมูล</div>
        </div>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="action" value="optimize_db">
          <button type="submit" class="btn" style="background:#fef3c7;color:#92400e;border:1px solid #fbbf24;">
            <i class="fas fa-wrench"></i> Optimize
          </button>
        </form>
      </div>
    </div>
    
    <div class="card">
      <h2 style="font-family:'Nunito',sans-serif;font-size:1.1rem;font-weight:800;margin-bottom:16px;">🗑️ Data Management</h2>
      <div class="setting-item">
        <div>
          <div style="font-size:.88rem;font-weight:600;">Clear Old Chat Logs</div>
          <div style="font-size:.72rem;color:var(--muted);margin-top:4px;">ลบ logs ที่เก่ากว่า 30/60/90 วัน</div>
        </div>
        <div style="display:flex;gap:8px;">
          <form method="POST" style="display:inline;" onsubmit="return confirm('ลบ logs เก่ากว่า 30 วัน?');">
            <input type="hidden" name="action" value="clear_logs">
            <input type="hidden" name="days" value="30">
            <button type="submit" class="btn" style="background:#fee2e2;color:#dc2626;border:1px solid #fecaca;">30 วัน</button>
          </form>
          <form method="POST" style="display:inline;" onsubmit="return confirm('ลบ logs เก่ากว่า 60 วัน?');">
            <input type="hidden" name="action" value="clear_logs">
            <input type="hidden" name="days" value="60">
            <button type="submit" class="btn" style="background:#fee2e2;color:#dc2626;border:1px solid #fecaca;">60 วัน</button>
          </form>
          <form method="POST" style="display:inline;" onsubmit="return confirm('ลบ logs เก่ากว่า 90 วัน?');">
            <input type="hidden" name="action" value="clear_logs">
            <input type="hidden" name="days" value="90">
            <button type="submit" class="btn" style="background:#fee2e2;color:#dc2626;border:1px solid #fecaca;">90 วัน</button>
          </form>
        </div>
      </div>
    </div>
    
    <form method="POST" action="">
    <div class="form-group">
        <label>Google Gemini API Key:</label>
        <input type="text" name="api_key" class="form-control" value="<?php echo htmlspecialchars($setting['api_key']); ?>" required>
    </div>

    <div class="form-group">
        <label>AI Model:</label>
        <select name="api_model" class="form-control" required>
            <option value="gemini-2.5-flash" <?php if($setting['api_model'] == 'gemini-2.5-flash') echo 'selected'; ?>>Gemini 2.5 Flash (แนะนำ)</option>
            <option value="gemini-2.0-flash" <?php if($setting['api_model'] == 'gemini-2.0-flash') echo 'selected'; ?>>Gemini 2.0 Flash</option>
            <option value="gemini-1.5-flash" <?php if($setting['api_model'] == 'gemini-1.5-flash') echo 'selected'; ?>>Gemini 1.5 Flash</option>
        </select>
    </div>

    <button type="submit" name="save_api_settings" class="btn btn-primary">บันทึกการตั้งค่า API</button>
    </form>
    
    <div class="card" style="border-color:#fecaca;">
      <h2 style="font-family:'Nunito',sans-serif;font-size:1.1rem;font-weight:800;margin-bottom:16px;color:#dc2626;">⚠️ Danger Zone</h2>
      <div class="setting-item">
        <div>
          <div style="font-size:.88rem;font-weight:600;color:#dc2626;">Backup Database</div>
          <div style="font-size:.72rem;color:var(--muted);margin-top:4px;">สำรองข้อมูลก่อนทำการเปลี่ยนแปลงใดๆ</div>
        </div>
        <button onclick="alert('รัน: mysqldump -u root myfood > backup_$(date +%Y%m%d).sql')" class="btn" style="background:#fef3c7;color:#92400e;border:1px solid #fbbf24;">
          <i class="fas fa-download"></i> Backup
        </button>
      </div>
      <div class="setting-item" style="border-bottom:none;">
        <div>
          <div style="font-size:.88rem;font-weight:600;color:#dc2626;">System Information</div>
          <div style="font-size:.72rem;color:var(--muted);margin-top:4px;">
            PHP <?= phpversion() ?> • MySQL <?= $conn->server_info ?> • Admin: <?= htmlspecialchars($admin_data['email']) ?>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
</body>
</html>