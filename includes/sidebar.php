<?php
// sidebar.php
// ตรวจสอบชื่อหน้าปัจจุบันเพื่อทำสถานะ Active
$current_page = basename($_SERVER['PHP_SELF']);

// ✅ ดึงข้อมูลจาก database
if (isset($_SESSION['user_id']) && isset($conn)) {
    // 🌟 แก้ไข: ดึงคอลัมน์ role ออกมาตรวจสอบแทน email
    $user_sql = "SELECT username, role, profile_image FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("i", $_SESSION['user_id']);
    $user_stmt->execute();
    $user_res = $user_stmt->get_result()->fetch_assoc();
    
    if ($user_res) {
        $username = !empty($user_res['username']) ? $user_res['username'] : "User";
        $profile_image = $user_res['profile_image'] ?? '';
        
        $is_admin = false;
        // 🌟 แก้ไข: เช็กจากคอลัมน์ role ถ้ามีค่าเป็น 1 แปลว่าเป็นแอดมิน (แสดงปุ่ม Admin Panel)
        if ($user_res['role'] == 1) { 
            $is_admin = true;
        }
    }
}

// ดึงตัวแปรสำหรับการแสดงผล (ในกรณีที่หาข้อมูลไม่พบ)
$username = $username ?? "User";
$profile_image = $profile_image ?? '';
$initials = mb_strtoupper(mb_substr($username, 0, 2));
$menu_count = $menu_count ?? 0;
?>

<style>
/* ════════════════════════════════════════
   SIDEBAR DESIGN TOKENS
════════════════════════════════════════ */
:root {
  --g50:  #f0fdf4; --g100: #dcfce7; --g200: #bbf7d0;
  --g400: #4ade80; --g500: #22c55e; --g600: #16a34a; --g700: #15803d;
  --t400: #2dd4bf; --t500: #14b8a6;
  --sb-bg: #ffffff; --sb-w: 248px; --sb-bdr: #e5ede6;
  --txt: #1a2e1a; --sub: #4b6b4e; --muted: #8da98f;
  --orange: #f97316; --bdr: #e8f0e9;
}

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
  color: var(--g700); letter-spacing: -.02em; line-height: 1;
}
.sb-logo-sub {
  font-size: .6rem; font-weight: 600;
  color: var(--muted); letter-spacing: .08em;
  text-transform: uppercase; margin-top: 2px;
}

/* Nav item */
.sb-label {
  font-size: .6rem; font-weight: 700;
  letter-spacing: .12em; text-transform: uppercase;
  color: var(--muted); padding: 18px 22px 8px;
}
.sb-nav { padding: 6px 12px; display: flex; flex-direction: column; gap: 2px; flex:1; overflow-y: auto; }

.nav-item {
  display: flex; align-items: center; gap: 11px;
  padding: 10px 12px; border-radius: 12px;
  text-decoration: none; color: var(--sub);
  font-size: .82rem; font-weight: 500;
  transition: all .18s; position: relative;
}
.nav-item:hover {
  background: var(--g50); color: var(--g700); transform: translateX(2px);
}
.nav-item.active {
  background: var(--g50); color: var(--g600); font-weight: 600;
  box-shadow: inset 3px 0 0 var(--g500);
}
.nav-item.active .nav-icon-wrap {
  background: linear-gradient(135deg, var(--g500), var(--t500));
  color: white; box-shadow: 0 3px 10px rgba(34,197,94,0.38);
}
.nav-icon-wrap {
  width: 34px; height: 34px; border-radius: 10px;
  background: var(--g50); border: 1px solid var(--bdr);
  display: flex; align-items: center; justify-content: center;
  font-size: .8rem; flex-shrink: 0; color: var(--g600);
}
.nav-badge {
  margin-left: auto; background: var(--g500); color: white;
  font-size: .6rem; font-weight: 700; padding: 2px 7px; border-radius: 99px;
}
.nav-badge.orange { background: var(--orange); }
.sb-divider { height: 1px; background: var(--sb-bdr); margin: 6px 12px; }

/* User section */
.sb-user {
  border-top: 1px solid var(--sb-bdr);
  padding: 16px; display: flex; align-items: center; gap: 11px; background: var(--g50);
}

/* ✅ Avatar รองรับทั้งรูปภาพและ initials */
.sb-avatar {
  width: 38px; height: 38px; border-radius: 50%;
  background: linear-gradient(135deg, var(--g400), var(--t400));
  display: flex; align-items: center; justify-content: center;
  font-size: .82rem; font-weight: 800; color: white;
  flex-shrink: 0; font-family: 'Nunito', sans-serif;
  box-shadow: 0 2px 8px rgba(34,197,94,0.3);
  overflow: hidden;
  position: relative;
}
.sb-avatar img {
  width: 100%; height: 100%;
  object-fit: cover;
  position: absolute;
  top: 0; left: 0;
}

/* ✅ แสดง username */
.sb-user-name { 
  font-family: 'Kanit', sans-serif !important; 
  font-size: .8rem !important; 
  font-weight: 600 !important; 
  color: var(--txt); 
  white-space: nowrap; 
  overflow: hidden; 
  text-overflow: ellipsis;
  max-width: 120px;
}

.sb-logout { margin-left: auto; width: 30px; height: 30px; border-radius: 8px; border: 1px solid var(--bdr); display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: .72rem; text-decoration: none; transition: all .18s; }
.sb-logout:hover { background: #fee2e2; border-color: #fecaca; color: #dc2626; }

/* Scroll container */
.scroll-box { display:flex;flex-direction:column;gap:9px;max-height:360px;overflow-y:auto;padding-right:3px; }
::-webkit-scrollbar { width:4px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:var(--g200);border-radius:99px; }

</style>

<aside class="sidebar">
  <div class="sb-logo">
    <div class="sb-logo-icon"><i class="fas fa-utensils" style="color: #ffffff;"></i></div>
    <div>
      <div class="sb-logo-text">FoodAI</div>
      <div class="sb-logo-sub">Smart Food Assistant</div>
    </div>
  </div>

  <nav class="sb-nav">
    <div class="sb-label">เมนูหลัก</div>

    <a href="dashboard.php" class="nav-item <?php echo ($current_page==='dashboard.php')?'active':''; ?>">
      <span class="nav-icon-wrap"><i class="fas fa-house-chimney"></i></span>
      <span>หน้าหลัก</span>
    </a>

    <a href="ai_chef.php" class="nav-item <?php echo ($current_page==='ai_chef.php')?'active':''; ?>">
      <span class="nav-icon-wrap"><i class="fas fa-robot"></i></span>
      <span>เชฟ AI</span>
      <span class="nav-badge orange">AI</span>
    </a>

    <a href="recipes.php" class="nav-item <?php echo ($current_page==='recipes.php' || $current_page==='recipe_detail.php')?'active':''; ?>">
      <span class="nav-icon-wrap"><i class="fas fa-book-open"></i></span>
      <span>สูตรอาหาร</span>
    </a>

    <a href="smart_fridge.php" class="nav-item <?php echo ($current_page==='smart_fridge.php')?'active':''; ?>">
      <span class="nav-icon-wrap"><i class="fa-solid fa-snowflake"></i></span>
      <span>ตู้เย็นอัจฉริยะ</span>
    </a>

    <a href="meal_log.php" class="nav-item <?php echo ($current_page==='meal_log.php')?'active':''; ?>">
      <span class="nav-icon-wrap"><i class="fas fa-clipboard-list"></i></span>
      <span>บันทึกมื้ออาหาร</span>
      <?php if($menu_count > 0): ?>
        <span class="nav-badge"><?php echo $menu_count; ?></span>
      <?php endif; ?>
    </a>

    <a href="nutrition.php" class="nav-item <?php echo ($current_page==='nutrition.php')?'active':''; ?>">
      <span class="nav-icon-wrap"><i class="fas fa-chart-pie"></i></span>
      <span>โภชนาการ</span>
    </a>

    <div class="sb-divider"></div>
    <div class="sb-label">ข้อมูล</div>

    <a href="news.php" class="nav-item <?php echo ($current_page==='news.php')?'active':''; ?>">
      <span class="nav-icon-wrap"><i class="fas fa-newspaper"></i></span>
      <span>ข่าวสารสุขภาพ</span>
    </a>

    <a href="menu_popular.php" class="nav-item <?php echo ($current_page==='menu_popular.php')?'active':''; ?>">
      <span class="nav-icon-wrap"><i class="fas fa-fire-flame-curved"></i></span>
      <span>เมนูยอดนิยม</span>
    </a>

    <div class="sb-divider"></div>
    <div class="sb-label">บัญชี</div>

    <a href="setup_profile.php" class="nav-item <?php echo ($current_page==='setup_profile.php')?'active':''; ?>">
      <span class="nav-icon-wrap"><i class="fas fa-user-gear"></i></span>
      <span>ข้อมูลสุขภาพ</span>
    </a>

    <a href="settings.php" class="nav-item <?php echo ($current_page==='settings.php')?'active':''; ?>">
      <span class="nav-icon-wrap"><i class="fas fa-sliders"></i></span>
      <span>ตั้งค่า</span>
    </a>

    <?php if ($is_admin): ?>
    <div class="sb-divider"></div>
    
    <a href="../admin/admin_dashboard.php" class="nav-item" style="background:linear-gradient(135deg,rgba(239,68,68,.05),rgba(220,38,38,.08));border:1.5px dashed #fca5a5;">
      <span class="nav-icon-wrap" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border:none;">
        <i class="fas fa-user-shield"></i>
      </span>
      <span style="color:#dc2626;font-weight:600;">Admin Panel</span>
      <i class="fas fa-crown" style="margin-left:auto;font-size:.65rem;color:#f97316;"></i>
    </a>
    <?php endif; ?>
  </nav>

  <div class="sb-user">
    <div class="sb-avatar">
      <?php if ($profile_image && file_exists("../public/uploads/avatars/" . $profile_image)): ?>
        <img src="../public/uploads/avatars/<?= htmlspecialchars($profile_image) ?>?t=<?= time() ?>" alt="<?= htmlspecialchars($username) ?>">
      <?php else: ?>
        <?= htmlspecialchars($initials ?: 'U') ?>
      <?php endif; ?>
    </div>
    
    <div style="min-width:0;">
      <div class="sb-user-name" title="<?= htmlspecialchars($username) ?>">
        <?= htmlspecialchars($username) ?>
      </div>
      <div style="color:var(--g600);font-size:.62rem;font-weight:500;margin-top:1px;">
        <i class="fas fa-circle" style="font-size:.4rem;color:var(--g400);vertical-align:middle;margin-right:3px;"></i>
        ออนไลน์
      </div>
    </div>
    <a href="logout.php" class="sb-logout" title="ออกจากระบบ" onclick="confirmLogout(event, this.href)">
      <i class="fas fa-arrow-right-from-bracket"></i>
    </a>
  </div>
</aside>

<script>
// ฟังก์ชันเปิด-ปิด Sidebar
function toggleSidebar() {
  const sidebar = document.querySelector('.sidebar');
  if (sidebar) {
    sidebar.classList.toggle('show');
  }
}

// ฟังก์ชันปิด Sidebar อัตโนมัติเมื่อคลิกพื้นที่ด้านนอก
document.addEventListener('click', (e) => {
  const sidebar = document.querySelector('.sidebar');
  const btn = document.querySelector('.menu-toggle');
  
  if (window.innerWidth <= 1024 && sidebar && btn) {
    if (!sidebar.contains(e.target) && !btn.contains(e.target)) {
      sidebar.classList.remove('show');
    }
  }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmLogout(event, url) {
  event.preventDefault();
  
  Swal.fire({
    title: 'ออกจากระบบ',
    text: "คุณต้องการออกจากระบบใช่หรือไม่?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#9ca3af',
    confirmButtonText: 'ออกจากระบบ',
    cancelButtonText: 'ยกเลิก'
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = url;
    }
  });
}
</script>
<style>
  .swal2-container { font-family: 'Kanit', sans-serif !important; }
</style>