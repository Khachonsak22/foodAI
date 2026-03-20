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
$initials      = mb_strtoupper(mb_substr($username,0,2));

/* ── Success/Error messages ── */
$success_msg = $_SESSION['settings_success'] ?? '';
$error_msg   = $_SESSION['settings_error'] ?? '';
unset($_SESSION['settings_success'], $_SESSION['settings_error']);

$current_page = 'settings.php';
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

.page-wrap{margin-left:var(--sb-w);flex:1;position:relative;z-index:1;min-width:0;}
.topbar{height:62px;background:rgba(255,255,255,.9);backdrop-filter:blur(12px);border-bottom:1px solid #e5ede6;display:flex;align-items:center;padding:0 2.5rem;gap:14px;position:sticky;top:0;z-index:50;}
.tb-back{width:38px;height:38px;border-radius:11px;background:#fff;border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;color:var(--sub);text-decoration:none;font-size:.8rem;transition:all .18s;}
.tb-back:hover{background:var(--g50);border-color:var(--g200);color:var(--g600);}

@keyframes slideUp{from{opacity:0;transform:translateY(18px);}to{opacity:1;transform:translateY(0);}}
.rv{opacity:0;animation:slideUp .5s cubic-bezier(.22,1,.36,1) forwards;}
.rv1{animation-delay:.04s;}.rv2{animation-delay:.10s;}.rv3{animation-delay:.17s;}

.stitle{font-family:'Nunito',sans-serif;font-size:1rem;font-weight:800;color:var(--txt);display:flex;align-items:center;gap:8px;}
.gline{height:3px;background:linear-gradient(90deg,var(--g500),transparent);border-radius:99px;}

.card{background:#fff;border:1px solid var(--bdr);border-radius:20px;padding:32px;box-shadow:0 4px 20px rgba(34,197,94,.06);}

/* Avatar upload */
.avatar-upload{position:relative;width:140px;height:140px;margin:0 auto;}
.avatar-preview{width:140px;height:140px;border-radius:50%;overflow:hidden;border:4px solid var(--g300);box-shadow:0 6px 20px rgba(34,197,94,.25);background:linear-gradient(135deg,var(--g400),var(--t400));display:flex;align-items:center;justify-content:center;color:#fff;font-family:'Nunito',sans-serif;font-size:2.5rem;font-weight:800;position:relative;}
.avatar-preview img{width:100%;height:100%;object-fit:cover;}
.avatar-btn{position:absolute;bottom:4px;right:4px;width:42px;height:42px;border-radius:50%;background:var(--g500);color:#fff;border:4px solid #fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:.9rem;box-shadow:0 4px 12px rgba(34,197,94,.4);}
.avatar-btn:hover{background:var(--g600);transform:scale(1.08);}
.avatar-btn input{display:none;}

/* Form */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
.form-group{margin-bottom:20px;}
.form-label{font-size:.8rem;font-weight:700;color:var(--sub);margin-bottom:8px;display:flex;align-items:center;gap:6px;}
.form-label i{font-size:.7rem;color:var(--g500);}
.form-input{width:100%;padding:13px 16px;background:var(--g50);border:2px solid var(--bdr);border-radius:12px;font-family:'Kanit',sans-serif;font-size:.88rem;color:var(--txt);outline:none;transition:all .2s;}
.form-input:focus{border-color:var(--g400);background:#fff;box-shadow:0 0 0 4px rgba(74,222,128,.12);}
.form-input:disabled{opacity:.65;cursor:not-allowed;background:#f8f9fa;}
.form-hint{font-size:.7rem;color:var(--muted);margin-top:5px;}

.btn-primary{background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;font-size:.85rem;font-weight:700;padding:14px 28px;border-radius:13px;border:none;cursor:pointer;transition:all .2s;box-shadow:0 4px 16px rgba(34,197,94,.3);font-family:'Kanit',sans-serif;width:100%;}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 22px rgba(34,197,94,.4);}

.btn-secondary{background:#fff;color:var(--sub);border:2px solid var(--bdr);font-size:.82rem;font-weight:600;padding:11px 22px;border-radius:12px;cursor:pointer;transition:all .2s;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-family:'Kanit',sans-serif;}
.btn-secondary:hover{background:var(--g50);border-color:var(--g300);color:var(--g600);}

.btn-danger{background:#fff;color:#dc2626;border:2px solid #fecaca;font-size:.82rem;font-weight:600;padding:11px 22px;border-radius:12px;cursor:pointer;transition:all .2s;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-family:'Kanit',sans-serif;}
.btn-danger:hover{background:#fee2e2;border-color:#f87171;}

/* Alert */
.alert{padding:16px 20px;border-radius:14px;font-size:.85rem;margin-bottom:24px;display:flex;align-items:center;gap:12px;animation:slideUp .4s ease;}
.alert.success{background:#dcfce7;border:2px solid var(--g300);color:var(--g700);}
.alert.error{background:#fee2e2;border:2px solid #fecaca;color:#dc2626;}
.alert i{font-size:1.2rem;flex-shrink:0;}

.divider{height:2px;background:linear-gradient(90deg,transparent,var(--g200),transparent);margin:28px 0;}

.info-box{background:var(--g50);border:1.5px solid var(--g200);border-radius:12px;padding:14px 18px;margin-top:24px;}
.info-box h4{font-size:.82rem;font-weight:700;color:var(--g700);margin-bottom:8px;display:flex;align-items:center;gap:8px;}
.info-box p{font-size:.75rem;color:var(--sub);line-height:1.6;}

/* Responsive */
@media (max-width: 1024px){
  .page-wrap{margin-left:0;}
  .form-row{grid-template-columns:1fr;}
}

@media (max-width: 768px){
  .card{padding:24px 20px;}
  .topbar{padding:0 1rem;}
  .avatar-upload{width:120px;height:120px;}
  .avatar-preview{width:120px;height:120px;font-size:2rem;}
}

::-webkit-scrollbar{width:4px;}
::-webkit-scrollbar-thumb{background:var(--g200);border-radius:99px;}
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
      <div style="font-size:.7rem;color:var(--muted);">จัดการข้อมูลส่วนตัวและบัญชีผู้ใช้</div>
    </div>
  </header>

  <main style="padding:2.5rem;max-width:720px;width:100%;margin:0 auto;">

    <!-- HEADER -->
    <div class="rv rv1" style="margin-bottom:2rem;">
      <p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;letter-spacing:.05em;">การตั้งค่า</p>
      <h1 style="font-family:'Nunito',sans-serif;font-size:1.8rem;font-weight:800;color:var(--txt);line-height:1.2;">
        โปรไฟล์และบัญชี
      </h1>
      <div class="gline" style="width:60px;margin-top:12px;"></div>
    </div>

    <?php if ($success_msg): ?>
    <div class="rv rv2 alert success">
      <i class="fas fa-check-circle"></i>
      <span><?= htmlspecialchars($success_msg) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
    <div class="rv rv2 alert error">
      <i class="fas fa-exclamation-circle"></i>
      <span><?= htmlspecialchars($error_msg) ?></span>
    </div>
    <?php endif; ?>

    <!-- PROFILE IMAGE -->
    <div class="rv rv2 card" style="text-align:center;margin-bottom:1.8rem;">
      <h2 class="stitle" style="margin-bottom:24px;justify-content:center;">
        <i class="fas fa-image"></i> รูปโปรไฟล์
      </h2>
      
      <form action="update_settings.php" method="POST" enctype="multipart/form-data" id="avatarForm">
        <input type="hidden" name="action" value="upload_avatar">
        <div class="avatar-upload">
          <div class="avatar-preview" id="avatarPreview">
            <?php if ($profile_image && file_exists("../public/uploads/avatars/" . $profile_image)): ?>
            <img src="../public/uploads/avatars/<?= htmlspecialchars($profile_image) ?>?t=<?= time() ?>" alt="Avatar">
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
      <p style="font-size:.72rem;color:var(--muted);margin-top:16px;">รองรับ JPG, PNG (ขนาดไม่เกิน 2MB)</p>
    </div>

    <!-- UNIFIED PROFILE & ACCOUNT INFO -->
    <div class="rv rv3 card">
      <h2 class="stitle" style="margin-bottom:24px;">
        <i class="fas fa-user-circle"></i> ข้อมูลบัญชีและโปรไฟล์
      </h2>
      
      <form action="update_settings.php" method="POST">
        <input type="hidden" name="action" value="update_profile">
        
        <!-- Username (แก้ไขได้) -->
        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-at"></i> ชื่อผู้ใช้
          </label>
          <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" class="form-input" required>
          <div class="form-hint">ชื่อที่ใช้แสดงในระบบและ sidebar</div>
        </div>

        <!-- Email (ไม่สามารถแก้ไข) -->
        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-envelope"></i> อีเมล
          </label>
          <input type="email" value="<?= htmlspecialchars($email) ?>" class="form-input" disabled>
          <div class="form-hint">ไม่สามารถเปลี่ยนอีเมลได้</div>
        </div>

        <div class="divider"></div>

        <!-- ชื่อ-นามสกุล (ไม่สามารถแก้ไข) -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">
              <i class="fas fa-user"></i> ชื่อจริง
            </label>
            <input type="text" value="<?= htmlspecialchars($first_name) ?>" class="form-input" disabled>
            <div class="form-hint">ไม่สามารถเปลี่ยนได้</div>
          </div>
          
          <div class="form-group">
            <label class="form-label">
              <i class="fas fa-user"></i> นามสกุล
            </label>
            <input type="text" value="<?= htmlspecialchars($last_name) ?>" class="form-input" disabled>
            <div class="form-hint">ไม่สามารถเปลี่ยนได้</div>
          </div>
        </div>
        
        <!-- วันเกิด -->
        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-calendar-days"></i> วันเกิด
          </label>
          <input type="date" name="birth_date" value="<?= htmlspecialchars($birth_date) ?>" class="form-input">
        </div>
        
        <!-- บันทึก -->
        <button type="submit" class="btn-primary">
          <i class="fas fa-save" style="margin-right:8px;"></i> บันทึกการเปลี่ยนแปลง
        </button>
      </form>

      <div class="divider"></div>

      <!-- Action Buttons -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <a href="forgot_password.php" class="btn-secondary">
          <i class="fas fa-key"></i> เปลี่ยนรหัสผ่าน
        </a>
        
        <a href="logout.php" class="btn-danger" onclick="return confirm('คุณต้องการออกจากระบบใช่หรือไม่?')">
          <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
        </a>
      </div>

      <!-- Info Box -->
      <div class="info-box">
        <h4><i class="fas fa-info-circle"></i> ข้อมูลสำคัญ</h4>
        <p>• <strong>ชื่อผู้ใช้</strong> จะแสดงใน sidebar และส่วนต่างๆ ของระบบ</p>
        <p>• <strong>ชื่อ-นามสกุล</strong> ไม่สามารถเปลี่ยนได้เพื่อความปลอดภัย</p>
        <p>• <strong>อีเมล</strong> ใช้สำหรับเข้าสู่ระบบและรับการแจ้งเตือน</p>
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