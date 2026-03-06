<?php
// sidebar_admin.php
$current_page = basename($_SERVER['PHP_SELF']);

$sb_admin_fname = "Admin";
$sb_admin_lname = "";

if (isset($_SESSION['user_id']) && isset($conn)) {
    $sb_adm_sql = "SELECT first_name, last_name FROM users WHERE id = ?";
    $sb_adm_stmt = $conn->prepare($sb_adm_sql);
    $sb_adm_stmt->bind_param("i", $_SESSION['user_id']);
    $sb_adm_stmt->execute();
    $sb_adm_res = $sb_adm_stmt->get_result()->fetch_assoc();
    if ($sb_adm_res) {
        $sb_admin_fname = $sb_adm_res['first_name'] ?? 'Admin';
        $sb_admin_lname = $sb_adm_res['last_name'] ?? '';
    }
}
$sb_admin_initials = mb_strtoupper(mb_substr($sb_admin_fname, 0, 1));
?>

<div class="sidebar-overlay" onclick="closeSidebar()"></div>

<aside class="sidebar" id="adminSidebar">

  <a href="admin_dashboard.php" class="sb-logo">
    <div class="sb-logo-icon"><i class="fas fa-user-shield" style="color: #ffffff;"></i></div>
    <div>
      <div class="sb-logo-text">Admin Panel</div>
      <div class="sb-logo-sub">Control Center</div>
    </div>
  </a>

  <nav class="sb-nav">
    <div class="sb-label">การจัดการระบบ</div>

    <a href="admin_dashboard.php" class="nav-item <?php echo ($current_page === 'admin_dashboard.php') ? 'active' : ''; ?>">
      <span class="ni"><i class="fas fa-chart-line"></i></span>
      <span>Dashboard</span>
    </a>

    <a href="admin_users.php" class="nav-item <?php echo ($current_page === 'admin_users.php') ? 'active' : ''; ?>">
      <span class="ni"><i class="fas fa-users"></i></span>
      <span>จัดการผู้ใช้</span>
    </a>

    <a href="admin_recipes.php" class="nav-item <?php echo ($current_page === 'admin_recipes.php') ? 'active' : ''; ?>">
      <span class="ni"><i class="fas fa-utensils"></i></span>
      <span>จัดการสูตรอาหาร</span>
    </a>

    <a href="admin_ingredients.php" class="nav-item <?php echo ($current_page === 'admin_ingredients.php') ? 'active' : ''; ?>">
      <span class="ni"><i class="fas fa-carrot"></i></span>
      <span>จัดการวัตถุดิบ</span>
    </a>

    <a href="admin_health_data.php" class="nav-item <?php echo ($current_page === 'admin_health_data.php') ? 'active' : ''; ?>">
      <span class="ni"><i class="fas fa-heartbeat"></i></span>
      <span>โรคและอาหารแพ้</span>
    </a>

    <a href="admin_chat_logs.php" class="nav-item <?php echo ($current_page === 'admin_chat_logs.php') ? 'active' : ''; ?>">
      <span class="ni"><i class="fas fa-comments"></i></span>
      <span>Chat Logs</span>
    </a>

    <div class="sb-divider"></div>
    <div class="sb-label">รายงานและตั้งค่า</div>

    <a href="admin_analytics.php" class="nav-item <?php echo ($current_page === 'admin_analytics.php') ? 'active' : ''; ?>">
      <span class="ni"><i class="fas fa-chart-bar"></i></span>
      <span>สถิติและรายงาน</span>
    </a>

    <a href="admin_settings.php" class="nav-item <?php echo ($current_page === 'admin_settings.php') ? 'active' : ''; ?>">
      <span class="ni"><i class="fas fa-cog"></i></span>
      <span>ตั้งค่าระบบ</span>
    </a>

    <div class="sb-divider"></div>
    
    <a href="../pages/dashboard.php" class="nav-item" style="background:linear-gradient(135deg,var(--g50),rgba(20,184,166,.08));border:1.5px dashed var(--g300);">
      <span class="ni" style="background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;border:none;">
        <i class="fas fa-arrow-right-arrow-left"></i>
      </span>
      <span style="color:var(--g600);font-weight:600;">ไปหน้าผู้ใช้</span>
      <i class="fas fa-external-link-alt" style="margin-left:auto;font-size:.65rem;color:var(--g400);"></i>
    </a>
  </nav>

  <div class="sb-user">
    <div class="sb-av"><?= htmlspecialchars($sb_admin_initials ?: 'A') ?></div>
    <div style="min-width:0; flex:1;">
      <div class="sb-un">
        <?= htmlspecialchars($sb_admin_fname . ' ' . $sb_admin_lname) ?>
      </div>
      <div style="font-size:.7rem; color:var(--muted); font-family: 'Kanit', sans-serif;">ผู้ดูแลระบบ</div>
    </div>
    <a href="../pages/logout.php" title="ออกจากระบบ" style="margin-left:auto; width:34px; height:34px; border-radius:10px; border:1px solid var(--g200); background:#fff; display:flex; align-items:center; justify-content:center; color:var(--g600); text-decoration:none; font-size:.8rem; transition:all .2s;">
      <i class="fas fa-sign-out-alt"></i>
    </a>
  </div>

</aside>

<style>
:root {
  --g50: #f0fdf4; --g100: #dcfce7; --g200: #bbf7d0; --g300:#86efac;
  --g500: #22c55e; --g600: #16a34a; --g700: #15803d;
  --t500: #14b8a6;
  --sb-w: 260px;
  --sub: #4b6b4e; --muted: #8da98f;
}

.sidebar {
  width: var(--sb-w);
  min-height: 100vh;
  background: #fff;
  border-right: 1px solid #e5ede6;
  display: flex;
  flex-direction: column;
  position: fixed;
  left: 0; top: 0; bottom: 0;
  z-index: 1050; /* อัปเกรด z-index เพื่อไม่ให้โดน topbar ทับเวลาจอมือถือ */
  box-shadow: 4px 0 24px rgba(34,197,94,.06);
  transition: transform .3s ease;
  font-family: 'Kanit', sans-serif !important; /* ล็อคฟอนต์ไม่ให้เพี้ยน */
}

.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.5);
  backdrop-filter: blur(3px);
  z-index: 1040; /* ให้อยู่ใต้ sidebar แตทับเว็บทั้งหมด */
  opacity: 0;
  transition: opacity .3s ease;
}

/* ปรับ Admin Panel ให้เป็นลิงก์กดได้ */
.sb-logo { 
  padding: 24px 22px 20px; 
  border-bottom: 1px solid #e5ede6; 
  display: flex; 
  align-items: center; 
  gap: 11px; 
  text-decoration: none !important;
  transition: background 0.2s ease;
}
.sb-logo:hover {
  background: var(--g50);
}

.sb-logo-icon {
  width: 44px; height: 44px; border-radius: 12px;
  background: linear-gradient(135deg, var(--g500), var(--t500));
  display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem; box-shadow: 0 4px 12px rgba(34,197,94,.35);
  flex-shrink: 0;
  transition: transform 0.2s ease;
}
.sb-logo:hover .sb-logo-icon {
  transform: scale(1.05);
}

.sb-logo-text { 
  font-family: 'Nunito', sans-serif !important; 
  font-size: 1.18rem !important; 
  font-weight: 800 !important; 
  color: var(--g700); 
  letter-spacing: -.02em; 
  line-height: 1; 
}

.sb-logo-sub {
  font-family: 'Kanit', sans-serif !important;
  font-size: 0.75rem !important;
  color: var(--muted);
  margin-top: 4px;
}

.sb-label {
  font-family: 'Kanit', sans-serif !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .05em;
  padding: 14px 14px 6px;
}

.sb-nav { 
  padding: 8px 12px; 
  display: flex; 
  flex-direction: column; 
  gap: 4px; 
  flex: 1; 
  overflow-y: auto; 
}

.nav-item { 
  display: flex; 
  align-items: center; 
  gap: 12px; 
  padding: 10px 14px; 
  border-radius: 12px; 
  text-decoration: none !important; 
  color: var(--sub); 
  font-family: 'Kanit', sans-serif !important;
  font-size: 0.95rem !important; /* ล็อคขนาดฟอนต์เมนูให้เท่ากันทุกหน้า */
  font-weight: 500 !important; 
  transition: all .2s; 
}

.nav-item span:not(.ni) {
  white-space: nowrap;
}

.nav-item:hover { 
  background: var(--g50); 
  color: var(--g600); 
}

.nav-item.active { 
  background: var(--g50); 
  color: var(--g600); 
  font-weight: 600 !important; 
}

.nav-item.active .ni { 
  background: var(--g600); 
  color: #fff; 
  border-color: var(--g600); 
  box-shadow: 0 4px 10px rgba(22, 163, 74, 0.25);
}

.ni {
  width: 34px; height: 34px; border-radius: 10px;
  background: var(--g50); border: 1px solid var(--g200);
  display: flex; align-items: center; justify-content: center;
  font-size: 0.85rem !important; flex-shrink: 0; transition: all .2s; color: var(--g600);
}

.nav-item:hover .ni { 
  background: var(--g100); 
}

.sb-user { 
  border-top: 1px solid #e5ede6; 
  padding: 16px; 
  background: var(--g50); 
  display: flex; 
  align-items: center; 
  gap: 12px; 
}

.sb-av {
  width: 40px; height: 40px; border-radius: 50%;
  background: linear-gradient(135deg, var(--g500), var(--t500));
  display: flex; align-items: center; justify-content: center;
  font-size: 0.9rem !important; font-weight: 800 !important; color: #fff;
  flex-shrink: 0;
  box-shadow: 0 4px 10px rgba(34,197,94,.25);
}

.sb-divider { 
  height: 1px; 
  background: #e5ede6; 
  margin: 8px 12px; 
}

.sb-un {
  font-family: 'Kanit', sans-serif !important; 
  font-size: 0.85rem !important; 
  font-weight: 600 !important; 
  color: var(--g700); 
  white-space: nowrap; 
  overflow: hidden; 
  text-overflow: ellipsis; 
}

/* เอฟเฟกต์ปุ่ม Logout */
.sb-user a:hover {
  background: #fee2e2 !important;
  color: #dc2626 !important;
  border-color: #fecaca !important;
}

/* Mobile Styles */
@media (max-width: 1024px) {
  .sidebar {
    transform: translateX(-100%);
  }
  
  .sidebar.open {
    transform: translateX(0);
  }
  
  .sidebar-overlay.show {
    display: block;
    opacity: 1;
  }
}

@media (min-width: 1025px) {
  .sidebar-overlay {
    display: none !important;
  }
}
</style>

<script>
function toggleSidebar() {
  const sidebar = document.getElementById('adminSidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  
  sidebar.classList.toggle('open');
  overlay.classList.toggle('show');
}

function closeSidebar() {
  const sidebar = document.getElementById('adminSidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  
  sidebar.classList.remove('open');
  overlay.classList.remove('show');
}

// Close sidebar when clicking a link on mobile (ป้องกันกดเมนูแล้ว sidebar ค้าง)
document.addEventListener('DOMContentLoaded', function() {
  const navItems = document.querySelectorAll('.nav-item');
  navItems.forEach(item => {
    item.addEventListener('click', function() {
      if (window.innerWidth <= 1024) {
        closeSidebar();
      }
    });
  });
});
</script>