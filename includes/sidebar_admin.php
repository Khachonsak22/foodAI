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

<div class="sidebar-overlay" id="adminSidebarOverlay"></div>

<aside class="sidebar" id="adminSidebar">

  <a href="admin_dashboard.php" class="sb-logo">
    <div class="sb-logo-icon"><i class="fas fa-user-shield" style="color: #ffffff;"></i></div>
    <div>
      <div class="sb-logo-text">Admin Panel</div>
      <div class="sb-logo-sub">Control Center</div>
    </div>
  </a>

  <nav class="sb-nav scroll-box">
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

    <a href="admin_goals.php" class="nav-item <?php echo ($current_page === 'admin_goals.php') ? 'active' : ''; ?>">
      <span class="ni"><i class="fa-solid fa-hospital"></i></span>
      <span>จัดการข้อมูลสุขภาพ</span>
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
    
    <a href="../pages/dashboard.php" class="nav-item" style="background:linear-gradient(135deg,#f0fdf4,rgba(20,184,166,.08)) !important; border:1px dashed #86efac !important;">
      <span class="ni" style="background:linear-gradient(135deg,#22c55e,#14b8a6) !important; color:#fff !important; border:none !important;">
        <i class="fas fa-exchange-alt"></i>
      </span>
      <span style="color:#16a34a !important; font-weight:600 !important;">ไปหน้าผู้ใช้</span>
      <i class="fas fa-external-link-alt" style="margin-left:auto !important; font-size:12px !important; color:#8da98f !important;"></i>
    </a>
  </nav>

  <div class="sb-user">
    <div class="sb-av"><?= htmlspecialchars($sb_admin_initials ?: 'A') ?></div>
    <div style="min-width:0; flex:1;">
      <div class="sb-un">
        <?= htmlspecialchars($sb_admin_fname . ' ' . $sb_admin_lname) ?>
      </div>
      <div class="sb-user-role">
        <i class="fas fa-circle" style="font-size:6px !important;color:#4ade80;vertical-align:middle;margin-right:4px;"></i>ผู้ดูแลระบบ
      </div>
    </div>
    <a href="../pages/logout.php" class="logout-btn" title="ออกจากระบบ">
      <i class="fas fa-sign-out-alt"></i>
    </a>
  </div>

</aside>

<style>
/* =========================================
   SIDEBAR CORE STYLES (Locked 100%)
========================================= */
#adminSidebar {
  width: 260px !important;
  min-width: 260px !important;
  max-width: 260px !important;
  height: 100vh !important;
  background: #fff !important;
  border-right: 1px solid #e5ede6 !important;
  display: flex !important;
  flex-direction: column !important;
  position: fixed !important;
  left: 0 !important; 
  top: 0 !important; 
  bottom: 0 !important;
  z-index: 10000 !important;
  box-shadow: 4px 0 24px rgba(34,197,94,.06) !important;
  transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1) !important;
  font-family: 'Kanit', sans-serif !important;
  overflow: hidden !important;
}

/* =========================================
   OVERLAY (MOBILE BACKGROUND)
========================================= */
#adminSidebarOverlay {
  position: fixed !important;
  top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important;
  width: 100vw !important; height: 100vh !important;
  background-color: rgba(0, 0, 0, 0.5) !important;
  backdrop-filter: blur(3px) !important;
  -webkit-backdrop-filter: blur(3px) !important;
  z-index: 9998 !important;
  opacity: 0 !important;
  visibility: hidden !important;
  transition: opacity 0.3s ease, visibility 0.3s ease !important;
  cursor: pointer !important;
}

#adminSidebarOverlay.show {
  opacity: 1 !important;
  visibility: visible !important;
}

/* =========================================
   COMPONENTS INSIDE SIDEBAR
========================================= */
#adminSidebar .sb-logo { 
  padding: 24px 22px 20px !important; 
  border-bottom: 1px solid #e5ede6 !important; 
  display: flex !important; 
  align-items: center !important; 
  gap: 11px !important; 
  text-decoration: none !important;
  transition: background 0.2s ease !important;
}
#adminSidebar .sb-logo:hover { background: #f0fdf4 !important; }

#adminSidebar .sb-logo-icon {
  width: 44px !important; height: 44px !important; border-radius: 12px !important;
  background: linear-gradient(135deg, #22c55e, #14b8a6) !important;
  display: flex !important; align-items: center !important; justify-content: center !important;
  font-size: 19px !important; color: #fff !important;
  box-shadow: 0 4px 12px rgba(34,197,94,.35) !important;
  flex-shrink: 0 !important;
}

/* ล็อกฟอนต์ให้เหมือนฝั่ง User เป๊ะๆ */
#adminSidebar .sb-logo-text { 
  font-family: 'Nunito', sans-serif !important; 
  font-size: 19px !important; font-weight: 800 !important; 
  color: #15803d !important; letter-spacing: -.02em !important; 
  line-height: 1 !important; margin: 0 !important;
}

#adminSidebar .sb-logo-sub {
  font-family: 'Kanit', sans-serif !important;
  font-size: 10px !important; font-weight: 600 !important;
  color: #8da98f !important; letter-spacing: .08em !important;
  text-transform: uppercase !important; margin-top: 4px !important;
}

#adminSidebar .sb-label {
  font-family: 'Kanit', sans-serif !important;
  font-size: 11px !important; font-weight: 700 !important;
  color: #8da98f !important; text-transform: uppercase !important;
  letter-spacing: .12em !important; padding: 18px 14px 8px !important;
  margin: 0 !important; line-height: 1.5 !important;
}

#adminSidebar .sb-nav { 
  padding: 8px 12px !important; display: flex !important; 
  flex-direction: column !important; gap: 4px !important; flex: 1 !important; 
}

#adminSidebar .nav-item { 
  display: flex !important; align-items: center !important; gap: 12px !important; 
  padding: 10px 14px !important; border-radius: 12px !important; 
  text-decoration: none !important; color: #4b6b4e !important; 
  font-family: 'Kanit', sans-serif !important; font-size: 14px !important; 
  font-weight: 500 !important; transition: all .2s !important; margin: 0 !important;
}
#adminSidebar .nav-item span:not(.ni) { white-space: nowrap !important; }
#adminSidebar .nav-item:hover { background: #f0fdf4 !important; color: #16a34a !important; }
#adminSidebar .nav-item.active { background: #f0fdf4 !important; color: #16a34a !important; font-weight: 600 !important; }
#adminSidebar .nav-item.active .ni { 
  background: #16a34a !important; color: #fff !important; 
  border-color: #16a34a !important; box-shadow: 0 4px 10px rgba(22, 163, 74, 0.25) !important;
}

#adminSidebar .ni {
  width: 34px !important; height: 34px !important; border-radius: 10px !important;
  background: #f0fdf4 !important; border: 1px solid #bbf7d0 !important;
  display: flex !important; align-items: center !important; justify-content: center !important;
  font-size: 14px !important; flex-shrink: 0 !important; transition: all .2s !important; color: #16a34a !important;
}

#adminSidebar .sb-user { 
  border-top: 1px solid #e5ede6 !important; padding: 16px !important; 
  background: #f0fdf4 !important; display: flex !important; 
  align-items: center !important; gap: 12px !important; margin-top: auto !important;
}

#adminSidebar .sb-av {
  width: 40px !important; height: 40px !important; border-radius: 50% !important;
  background: linear-gradient(135deg, #22c55e, #14b8a6) !important;
  display: flex !important; align-items: center !important; justify-content: center !important;
  font-size: 15px !important; font-weight: 800 !important; color: #fff !important;
  flex-shrink: 0 !important; box-shadow: 0 4px 10px rgba(34,197,94,.25) !important;
}

#adminSidebar .sb-un {
  font-family: 'Kanit', sans-serif !important; font-size: 14px !important; 
  font-weight: 600 !important; color: #15803d !important; 
  white-space: nowrap !important; overflow: hidden !important; 
  text-overflow: ellipsis !important; line-height: 1.2 !important;
}

#adminSidebar .sb-user-role {
  font-family: 'Kanit', sans-serif !important; font-size: 11px !important; 
  font-weight: 500 !important; color: #16a34a !important; margin-top: 2px !important;
}

#adminSidebar .sb-divider { height: 1px !important; background: #e5ede6 !important; margin: 8px 12px !important; }

#adminSidebar .logout-btn {
  margin-left: auto !important; width: 36px !important; height: 36px !important; 
  border-radius: 10px !important; border: 1px solid #bbf7d0 !important; 
  background: #fff !important; display: flex !important; align-items: center !important; 
  justify-content: center !important; color: #16a34a !important; text-decoration: none !important; 
  font-size: 14px !important; transition: all .2s !important; flex-shrink: 0 !important;
}
#adminSidebar .logout-btn:hover { background: #fee2e2 !important; color: #dc2626 !important; border-color: #fecaca !important; }

/* Scroll container แบบเดียวกับ User */
#adminSidebar .scroll-box { overflow-y:auto !important; padding-right:3px !important; }
#adminSidebar .scroll-box::-webkit-scrollbar { width:4px !important; }
#adminSidebar .scroll-box::-webkit-scrollbar-track { background:transparent !important; }
#adminSidebar .scroll-box::-webkit-scrollbar-thumb { background:#bbf7d0 !important; border-radius:99px !important; }

/* =========================================
   RESPONSIVE LOGIC
========================================= */
@media (max-width: 1024px) {
  #adminSidebar { transform: translateX(-100%) !important; }
  #adminSidebar.open { transform: translateX(0) !important; }
}
@media (min-width: 1025px) {
  #adminSidebarOverlay { display: none !important; visibility: hidden !important; opacity: 0 !important; }
  #adminSidebar { transform: translateX(0) !important; }
}
</style>

<script>
// สคริปต์ Hamburger นำมาจาก sidebar.php ของฝั่งผู้ใช้ 100%
document.addEventListener('DOMContentLoaded', function() {
  
  // 1. ดักจับปุ่ม Hamburger ทั้งหมด (หาคลาส .menu-toggle หรือ .hamburger)
  const toggles = document.querySelectorAll('.menu-toggle, .hamburger, [onclick*="toggleSidebar"]');
  toggles.forEach(btn => {
    btn.removeAttribute('onclick'); // ล้างคำสั่งเก่าทิ้ง
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      toggleAdminSidebar();
    });
  });

  // 2. ดักจับ Overlay เพื่อปิดเมนู
  const overlay = document.getElementById('adminSidebarOverlay');
  if (overlay) {
    overlay.addEventListener('click', function() {
      closeAdminSidebar();
    });
  }

  // 3. ปิดเมนูอัตโนมัติเวลากดเลือกลิงก์ (สำหรับมือถือ)
  const navItems = document.querySelectorAll('#adminSidebar .nav-item');
  navItems.forEach(item => {
    item.addEventListener('click', function() {
      if (window.innerWidth <= 1024) {
        closeAdminSidebar();
      }
    });
  });
});

function toggleAdminSidebar() {
  const sidebar = document.getElementById('adminSidebar');
  const overlay = document.getElementById('adminSidebarOverlay');
  
  if(sidebar) sidebar.classList.toggle('open');
  if(overlay) overlay.classList.toggle('show');
}

function closeAdminSidebar() {
  const sidebar = document.getElementById('adminSidebar');
  const overlay = document.getElementById('adminSidebarOverlay');
  
  if(sidebar) sidebar.classList.remove('open');
  if(overlay) overlay.classList.remove('show');
}
</script>