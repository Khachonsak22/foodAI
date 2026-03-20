<?php
session_start();
include '../config/connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$admin_stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$admin_stmt->bind_param("i", $user_id);
$admin_stmt->execute();
$admin_data = $admin_stmt->get_result()->fetch_assoc();

if ($admin_data['role'] != 1) {
    header("Location: ../pages/dashboard.php");
    exit();
}

/* ══════════════════════════════════════════════════════════════════
   FILTERS & PAGINATION
   ══════════════════════════════════════════════════════════════════ */
$search = $_GET['search'] ?? '';
$sender_filter = $_GET['sender'] ?? 'all';
$user_filter = $_GET['user_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 30;
$offset = ($page - 1) * $per_page;

$where = "WHERE 1=1";
if ($search) {
    $search_safe = $conn->real_escape_string($search);
    $where .= " AND cl.message LIKE '%$search_safe%'";
}
if ($sender_filter !== 'all') {
    $where .= " AND cl.sender = '" . ($sender_filter === 'user' ? 'user' : 'ai') . "'";
}
if ($user_filter && is_numeric($user_filter)) {
    $where .= " AND cl.user_id = " . (int)$user_filter;
}
if ($date_from) {
    $where .= " AND DATE(cl.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
}
if ($date_to) {
    $where .= " AND DATE(cl.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
}

$total = $conn->query("SELECT COUNT(*) as c FROM chat_logs cl $where")->fetch_assoc()['c'];
$total_pages = ceil($total / $per_page);

$logs = $conn->query("
    SELECT cl.id, cl.user_id, cl.sender, cl.message, cl.created_at,
           u.username, u.email
    FROM chat_logs cl
    LEFT JOIN users u ON cl.user_id = u.id
    $where
    ORDER BY cl.created_at DESC
    LIMIT $per_page OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

$stats = [
    'total' => $conn->query("SELECT COUNT(*) as c FROM chat_logs")->fetch_assoc()['c'],
    'user' => $conn->query("SELECT COUNT(*) as c FROM chat_logs WHERE sender='user'")->fetch_assoc()['c'],
    'ai' => $conn->query("SELECT COUNT(*) as c FROM chat_logs WHERE sender='ai'")->fetch_assoc()['c'],
    'users' => $conn->query("SELECT COUNT(DISTINCT user_id) as c FROM chat_logs")->fetch_assoc()['c'],
];

$active_users = $conn->query("
    SELECT DISTINCT u.id, u.username, (SELECT COUNT(*) FROM chat_logs WHERE user_id = u.id) as cnt
    FROM users u INNER JOIN chat_logs cl ON u.id = cl.user_id
    ORDER BY cnt DESC LIMIT 20
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chat Logs — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--g50:#f0fdf4;--g200:#bbf7d0;--g300:#86efac;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--bg:#f5f8f5;--bdr:#e8f0e9;--txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;--sb-w:260px;}
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
.card{background:#fff;border:1px solid var(--bdr);border-radius:18px;padding:22px;}
.chat-msg{padding:14px 16px;border:1.5px solid var(--bdr);border-radius:13px;margin-bottom:12px;}
.chat-msg.user{background:#eff6ff;border-color:#bfdbfe;}
.chat-msg.ai{background:#f0fdf4;border-color:var(--g200);}
.stat-mini{background:#fff;border:1px solid var(--bdr);border-radius:12px;padding:16px;text-align:center;}
.stat-mini-val{font-family:'Nunito',sans-serif;font-size:1.5rem;font-weight:800;}
.filter-box{background:#fff;border:1.5px solid var(--bdr);border-radius:12px;padding:0 14px;height:40px;display:flex;align-items:center;gap:10px;}
.filter-box select,.filter-box input{border:none;outline:none;background:transparent;font-family:'Kanit',sans-serif;font-size:.8rem;}
.btn{padding:8px 16px;border-radius:10px;font-size:.78rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;border:none;}
.pagination{display:flex;gap:6px;justify-content:center;margin-top:20px;}
.page-btn{width:36px;height:36px;border-radius:10px;border:1px solid var(--bdr);background:#fff;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:600;text-decoration:none;color:var(--sub);}
.page-btn:hover{background:var(--g50);}
.page-btn.active{background:var(--g500);color:#fff;}
.hamburger{display:none;width:40px;height:40px;border-radius:10px;background:#fff;border:1.5px solid var(--bdr);align-items:center;justify-content:center;cursor:pointer;}
@media (max-width:1024px){
  .page-wrap{margin-left:0;}
  .hamburger{display:flex;}
}
@media (max-width:768px){
  .topbar{padding:0 1rem;}
  main{padding:1.5rem 1rem;}
  .card-header-custom{flex-direction:column;gap:1rem;align-items:flex-start;}
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
    <div style="flex:1;">
      <div style="font-family:'Nunito',sans-serif;font-size:1.1rem;font-weight:800;">Chat Logs</div>
      <div style="font-size:.72rem;color:var(--muted);">ตรวจสอบการสนทนา</div>
    </div>
  </header>
  <main style="padding:2rem 2.5rem;">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:2rem;">
      <div class="stat-mini"><div class="stat-mini-val" style="color:#2563eb;"><?= number_format($stats['total']) ?></div><div style="font-size:.7rem;color:var(--muted);margin-top:4px;">ทั้งหมด</div></div>
      <div class="stat-mini"><div class="stat-mini-val" style="color:var(--g600);"><?= number_format($stats['user']) ?></div><div style="font-size:.7rem;color:var(--muted);margin-top:4px;">จากผู้ใช้</div></div>
      <div class="stat-mini"><div class="stat-mini-val" style="color:#14b8a6;"><?= number_format($stats['ai']) ?></div><div style="font-size:.7rem;color:var(--muted);margin-top:4px;">จาก AI</div></div>
      <div class="stat-mini"><div class="stat-mini-val" style="color:#9333ea;"><?= number_format($stats['users']) ?></div><div style="font-size:.7rem;color:var(--muted);margin-top:4px;">ผู้ใช้</div></div>
    </div>
    <div class="card" style="margin-bottom:1.5rem;">
      <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
        <div class="filter-box"><i class="fas fa-search" style="color:var(--muted);font-size:.75rem;"></i><input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหา..."></div>
        <div class="filter-box"><i class="fas fa-user" style="color:var(--muted);font-size:.75rem;"></i><select name="sender" onchange="this.form.submit()"><option value="all" <?= $sender_filter==='all'?'selected':'' ?>>ทั้งหมด</option><option value="user" <?= $sender_filter==='user'?'selected':'' ?>>User</option><option value="ai" <?= $sender_filter==='ai'?'selected':'' ?>>AI</option></select></div>
        <div class="filter-box"><i class="fas fa-users" style="color:var(--muted);font-size:.75rem;"></i><select name="user_id" onchange="this.form.submit()"><option value="">เลือกผู้ใช้</option><?php foreach ($active_users as $au): ?><option value="<?= $au['id'] ?>" <?= $user_filter==$au['id']?'selected':'' ?>><?= htmlspecialchars($au['username']) ?> (<?= $au['cnt'] ?>)</option><?php endforeach; ?></select></div>
        <div class="filter-box"><i class="fas fa-calendar" style="color:var(--muted);font-size:.75rem;"></i><input type="date" name="date_from" value="<?= $date_from ?>" onchange="this.form.submit()"></div>
        <div class="filter-box"><i class="fas fa-calendar" style="color:var(--muted);font-size:.75rem;"></i><input type="date" name="date_to" value="<?= $date_to ?>" onchange="this.form.submit()"></div>
        <button type="submit" class="btn" style="background:var(--g500);color:#fff;justify-content:center;"><i class="fas fa-filter"></i> กรอง</button>
        <?php if ($search || $sender_filter !== 'all' || $user_filter || $date_from || $date_to): ?>
        <a href="admin_chat_logs.php" class="btn" style="background:#fee2e2;color:#dc2626;text-decoration:none;justify-content:center;"><i class="fas fa-times"></i> ล้าง</a>
        <?php endif; ?>
      </form>
    </div>
    <div class="card">
      <?php if (empty($logs)): ?>
      <div style="text-align:center;padding:3rem;"><div style="font-size:3rem;opacity:.2;">💬</div><p style="font-size:.9rem;color:var(--muted);">ไม่พบข้อมูล</p></div>
      <?php else: ?>
      <?php foreach ($logs as $log): ?>
      <div class="chat-msg <?= $log['sender'] ?>">
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;border-radius:50%;background:<?= $log['sender']==='user'?'#2563eb':'var(--g500)' ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.7rem;"><?= $log['sender']==='user'?'👤':'🤖' ?></div>
            <div><div style="font-size:.82rem;font-weight:600;"><?= $log['sender']==='user' ? htmlspecialchars($log['username'] ?? 'Unknown') : 'AI Chef' ?></div><div style="font-size:.68rem;color:var(--muted);"><?= htmlspecialchars($log['email'] ?? '-') ?> • <?= date('j M Y H:i', strtotime($log['created_at'])) ?></div></div>
          </div>
          <div style="font-size:.65rem;color:var(--muted);background:rgba(0,0,0,.03);padding:4px 10px;border-radius:8px;">ID: <?= $log['id'] ?></div>
        </div>
        <div style="font-size:.82rem;line-height:1.6;padding-left:42px;"><?= nl2br(htmlspecialchars($log['message'])) ?></div>
      </div>
      <?php endforeach; ?>
      <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
        <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
        <a href="?page=<?= $i ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?><a href="?page=<?= $page+1 ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>