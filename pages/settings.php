<?php
session_start();
include '../config/connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* ── Fetch user data ── */
$u_stmt = $conn->prepare("SELECT username, email, first_name, last_name, birth_date, profile_image FROM users WHERE id = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$user_data = $u_stmt->get_result()->fetch_assoc();

$username      = $user_data['username'] ?? '';
$email         = $user_data['email'] ?? '';
$first_name    = $user_data['first_name'] ?? '';
$last_name     = $user_data['last_name'] ?? '';
$birth_date    = $user_data['birth_date'] ?? '';
$profile_image = $user_data['profile_image'] ?? '';
$initials      = mb_strtoupper(mb_substr($first_name,0,1)).mb_strtoupper(mb_substr($last_name,0,1));

/* ── Success/Error messages ── */
$success_msg = $_SESSION['settings_success'] ?? '';
$error_msg   = $_SESSION['settings_error'] ?? '';
unset($_SESSION['settings_success'], $_SESSION['settings_error']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ตั้งค่า — FoodAI</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
.rv1{animation-delay:.04s;}.rv2{animation-delay:.10s;}.rv3{animation-delay:.17s;}.rv4{animation-delay:.24s;}

.stitle{font-family:'Nunito',sans-serif;font-size:1rem;font-weight:800;color:var(--txt);display:flex;align-items:center;gap:8px;}
.gline{height:3px;background:linear-gradient(90deg,var(--g500),transparent);border-radius:99px;}

.card{background:#fff;border:1px solid var(--bdr);border-radius:20px;padding:28px;}

/* Avatar upload */
.avatar-upload{position:relative;width:120px;height:120px;margin:0 auto;}
.avatar-preview{width:120px;height:120px;border-radius:50%;overflow:hidden;border:3px solid var(--g300);box-shadow:0 4px 16px rgba(34,197,94,.2);background:linear-gradient(135deg,var(--g400),var(--t400));display:flex;align-items:center;justify-content:center;color:#fff;font-family:'Nunito',sans-serif;font-size:2rem;font-weight:800;position:relative;}
.avatar-preview img{width:100%;height:100%;object-fit:cover;}
.avatar-btn{position:absolute;bottom:0;right:0;width:36px;height:36px;border-radius:50%;background:var(--g500);color:#fff;border:3px solid #fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .18s;font-size:.8rem;}
.avatar-btn:hover{background:var(--g600);transform:scale(1.05);}
.avatar-btn input{display:none;}

/* Form */
.form-group{margin-bottom:20px;}
.form-label{font-size:.78rem;font-weight:600;color:var(--sub);margin-bottom:7px;display:block;}
.form-input{width:100%;padding:11px 14px;background:var(--g50);border:1.5px solid var(--bdr);border-radius:12px;font-family:'Kanit',sans-serif;font-size:.85rem;color:var(--txt);outline:none;transition:border-color .18s;}
.form-input:focus{border-color:var(--g400);box-shadow:0 0 0 3px rgba(74,222,128,.12);}
.form-input:disabled{opacity:.6;cursor:not-allowed;}

.btn-green{background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;font-size:.82rem;font-weight:700;padding:12px 28px;border-radius:13px;border:none;cursor:pointer;transition:opacity .2s,box-shadow .2s;box-shadow:0 4px 16px rgba(34,197,94,.3);font-family:'Kanit',sans-serif;width:100%;}
.btn-green:hover{opacity:.88;box-shadow:0 6px 20px rgba(34,197,94,.4);}

/* Alert */
.alert{padding:14px 18px;border-radius:13px;font-size:.82rem;margin-bottom:20px;display:flex;align-items:center;gap:12px;}
.alert.success{background:#f0fdf4;border:1.5px solid var(--g300);color:var(--g700);}
.alert.error{background:#fee2e2;border:1.5px solid #fecaca;color:#dc2626;}

/* Responsive */
@media (max-width: 1024px){
  .sidebar{width:70px;}
  .sb-logo-text,.sb-label,.nav-item span:not(.ni),.nb,.sb-un{display:none;}
  .sb-logo{justify-content:center;padding:20px 10px;}
  .nav-item{justify-content:center;padding:10px;}
  .sb-user{flex-direction:column;gap:8px;padding:12px 8px;}
  .page-wrap{margin-left:70px;}
  .topbar{padding:0 1.5rem;}
}

@media (max-width: 768px){
  .sidebar{transform:translateX(-100%);transition:transform .3s;}
  .sidebar.open{transform:translateX(0);}
  .page-wrap{margin-left:0;}
  .card{padding:20px;}
  .topbar{padding:0 1rem;}
}

::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-thumb{background:var(--g200);border-radius:99px;}

/* ── สไตล์ปุ่ม Hamburger Menu ── */
.menu-toggle {
  display: none;
  width: 38px; height: 38px; border-radius: 11px;
  background: white; border: 1px solid var(--bdr);
  align-items: center; justify-content: center;
  color: var(--sub); font-size: 0.9rem; cursor: pointer;
}
/* ── การจัดการ Layout บนมือถือ (จอเล็กกว่า 1024px) ── */
@media (max-width: 1024px) {
  .sidebar {
    transform: translateX(-100%); /* ซ่อน Sidebar ออกไปด้านซ้าย */
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .sidebar.show {
    transform: translateX(0); /* เลื่อน Sidebar กลับเข้ามาเมื่อมีคลาส .show */
  }
  .page-wrap {
    margin-left: 0 !important; /* ให้เนื้อหาหลักขยายเต็มจอ */
  }
  .menu-toggle {
    display: flex; /* แสดงปุ่มเมนูบนมือถือ */
  }
}
</style>
</head>
<body>

<?php include '../includes/sidebar.php' ?>

<!-- MAIN -->
<div class="page-wrap">

  <header class="topbar">
    <a href="dashboard.php" class="tb-back"><i class="fas fa-arrow-left"></i></a>
    <div>
      <div style="font-family:'Nunito',sans-serif;font-size:.95rem;font-weight:800;color:var(--txt);">ตั้งค่าบัญชี</div>
      <div style="font-size:.7rem;color:var(--muted);">จัดการข้อมูลส่วนตัวของคุณ</div>
    </div>
  </header>

  <main style="padding:2rem;max-width:800px;width:100%;margin:0 auto;">

    <!-- HEADER -->
    <div class="rv rv1" style="margin-bottom:1.8rem;">
      <p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">การตั้งค่า</p>
      <h1 style="font-family:'Nunito',sans-serif;font-size:1.6rem;font-weight:800;color:var(--txt);line-height:1.1;">
        ข้อมูลบัญชีผู้ใช้
      </h1>
      <div class="gline" style="width:50px;margin-top:10px;"></div>
    </div>

    <?php if ($success_msg): ?>
    <div class="rv rv2 alert success">
      <i class="fas fa-check-circle" style="font-size:1.2rem;"></i>
      <span><?= htmlspecialchars($success_msg) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
    <div class="rv rv2 alert error">
      <i class="fas fa-exclamation-circle" style="font-size:1.2rem;"></i>
      <span><?= htmlspecialchars($error_msg) ?></span>
    </div>
    <?php endif; ?>

    <!-- PROFILE IMAGE -->
    <div class="rv rv2 card" style="text-align:center;margin-bottom:1.5rem;">
      <h2 class="stitle" style="margin-bottom:20px;justify-content:center;">📷 รูปโปรไฟล์</h2>
      
      <form action="update_settings.php" method="POST" enctype="multipart/form-data" id="avatarForm">
        <input type="hidden" name="action" value="upload_avatar">
        <div class="avatar-upload">
          <div class="avatar-preview" id="avatarPreview">
            <?php if ($profile_image && file_exists("../public/uploads/avatars/" . $profile_image)): ?>
            <img src="../public/uploads/avatars/<?= htmlspecialchars($profile_image) ?>" alt="Avatar">
            <?php else: ?>
            <?= htmlspecialchars($initials ?: '?') ?>
            <?php endif; ?>
          </div>
          <label class="avatar-btn" title="เปลี่ยนรูปภาพ">
            <i class="fas fa-camera"></i>
            <input type="file" name="avatar" accept="image/*" onchange="previewAvatar(this); document.getElementById('avatarForm').submit();">
          </label>
        </div>
      </form>
      <p style="font-size:.7rem;color:var(--muted);margin-top:14px;">รองรับ JPG, PNG (ขนาดไม่เกิน 2MB)</p>
    </div>

    <!-- PERSONAL INFO -->
    <div class="rv rv3 card" style="margin-bottom:1.5rem;">
      <h2 class="stitle" style="margin-bottom:20px;">ข้อมูลส่วนตัว</h2>
      
      <form action="update_settings.php" method="POST">
        <input type="hidden" name="action" value="update_profile">
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
          <div class="form-group">
            <label class="form-label">ชื่อจริง</label>
            <input type="text" name="first_name" value="<?= htmlspecialchars($first_name) ?>" class="form-input" required>
          </div>
          
          <div class="form-group">
            <label class="form-label">นามสกุล</label>
            <input type="text" name="last_name" value="<?= htmlspecialchars($last_name) ?>" class="form-input" required>
          </div>
        </div>
        
        <div class="form-group">
          <label class="form-label">วันเกิด</label>
          <input type="date" name="birth_date" value="<?= htmlspecialchars($birth_date) ?>" class="form-input">
        </div>
        
        <button type="submit" class="btn-green">
          <i class="fas fa-save" style="margin-right:8px;"></i> บันทึกข้อมูล
        </button>
      </form>
    </div>

    <!-- ACCOUNT INFO -->
    <div class="rv rv4 card" style="margin-bottom:1.5rem;">
      <h2 class="stitle" style="margin-bottom:20px;">ข้อมูลบัญชี</h2>
      
      <div class="form-group">
        <label class="form-label">ชื่อผู้ใช้</label>
        <input type="text" value="<?= htmlspecialchars($username) ?>" class="form-input" disabled>
      </div>
      
      <div class="form-group">
        <label class="form-label">อีเมล</label>
        <input type="email" value="<?= htmlspecialchars($email) ?>" class="form-input" disabled>
      </div>
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <a href="forgot_password.php" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;border:1.5px solid var(--bdr);border-radius:11px;text-decoration:none;font-size:.78rem;font-weight:600;color:var(--sub);transition:all .18s;">
          <i class="fas fa-key"></i> เปลี่ยนรหัสผ่าน
        </a>
        
        <a href="logout.php" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;border:1.5px solid #fecaca;background:#fee2e2;border-radius:11px;text-decoration:none;font-size:.78rem;font-weight:600;color:#dc2626;transition:all .18s;">
          <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
        </a>
      </div>
    </div>

  </main>
</div>

<script>
function previewAvatar(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('avatarPreview').innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

</body>
</html>