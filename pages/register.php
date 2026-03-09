<?php
session_start();
include '../config/connect.php';

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    // ✅ ตรวจสอบรหัสผ่านให้เข้มงวดขึ้น
    if ($password !== $confirmPassword) {
        $error = "รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน";
    } elseif (strlen($password) < 8) {
        $error = "รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "รหัสผ่านต้องมีตัวพิมพ์ใหญ่อย่างน้อย 1 ตัว (A-Z)";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = "รหัสผ่านต้องมีตัวพิมพ์เล็กอย่างน้อย 1 ตัว (a-z)";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "รหัสผ่านต้องมีตัวเลขอย่างน้อย 1 ตัว (0-9)";
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $error = "รหัสผ่านต้องมีอักขระพิเศษอย่างน้อย 1 ตัว (!@#$%^&*)";
    } else {
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $checkStmt->bind_param("ss", $email, $username);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $error = "อีเมลหรือชื่อผู้ใช้นี้ถูกใช้งานแล้ว";
        } else {
            $passwordHash = hash('sha256', $password);

            $insertStmt = $conn->prepare("INSERT INTO users (first_name, last_name, username, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            $insertStmt->bind_param("sssss", $firstName, $lastName, $username, $email, $passwordHash);

            if ($insertStmt->execute()) {
                $success = "สมัครสมาชิกสำเร็จ! กำลังพาท่านไปหน้าเข้าสู่ระบบ...";
                header("refresh:2;url=login.php");
            } else {
                $error = "เกิดข้อผิดพลาด: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>สมัครสมาชิก — FoodAI</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;--g400:#4ade80;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--t400:#2dd4bf;--t500:#14b8a6;--bg:#f5f8f5;--txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1.5rem;position:relative;overflow-x:hidden;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background:linear-gradient(135deg,#f0fdf4 0%,#dcfce7 50%,#e0f2fe 100%);opacity:.6;}
body::after{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle at 20% 50%,rgba(74,222,128,.15) 0%,transparent 50%),radial-gradient(circle at 80% 80%,rgba(45,212,191,.12) 0%,transparent 50%);animation:bgPulse 8s ease-in-out infinite;}
@keyframes bgPulse{0%,100%{opacity:1;}50%{opacity:.6;}}

.float-shape{position:fixed;border-radius:50%;opacity:.08;pointer-events:none;z-index:0;backdrop-filter:blur(80px);}
.float-shape:nth-child(1){width:400px;height:400px;background:linear-gradient(135deg,var(--g400),var(--t400));top:-150px;right:-150px;animation:float1 20s ease-in-out infinite;}
.float-shape:nth-child(2){width:300px;height:300px;background:linear-gradient(135deg,var(--t500),var(--g500));bottom:-100px;left:-100px;animation:float2 18s ease-in-out infinite;}
.float-shape:nth-child(3){width:250px;height:250px;background:linear-gradient(135deg,var(--g300),var(--g400));top:50%;right:20%;animation:float3 15s ease-in-out infinite;}
@keyframes float1{0%,100%{transform:translate(0,0) scale(1);}50%{transform:translate(-40px,40px) scale(1.1);}}
@keyframes float2{0%,100%{transform:translate(0,0) scale(1);}50%{transform:translate(40px,-40px) scale(1.15);}}
@keyframes float3{0%,100%{transform:translate(0,0) scale(1);}50%{transform:translate(-30px,-30px) scale(.9);}}

.container{max-width:620px;width:100%;position:relative;z-index:1;}
.card{background:rgba(255,255,255,.95);backdrop-filter:blur(20px);border:1px solid rgba(232,240,233,.8);border-radius:32px;padding:3rem 2.8rem;box-shadow:0 25px 70px rgba(34,197,94,.15),0 10px 30px rgba(0,0,0,.05);position:relative;overflow:hidden;}
.card::before{content:'';position:absolute;top:0;left:0;right:0;height:6px;background:linear-gradient(90deg,var(--g500),var(--t500),var(--g400));opacity:.8;}

.header{text-align:center;margin-bottom:2.5rem;}
.logo{width:75px;height:75px;border-radius:20px;background:linear-gradient(135deg,var(--g500),var(--t500));display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 1.5rem;box-shadow:0 8px 24px rgba(34,197,94,.35),0 0 0 6px rgba(74,222,128,.1);animation:logoFloat 4s ease-in-out infinite;}
@keyframes logoFloat{0%,100%{transform:translateY(0);}50%{transform:translateY(-8px);}}

.title{font-family:'Nunito',sans-serif;font-size:1.9rem;font-weight:800;color:var(--txt);margin-bottom:.5rem;}
.subtitle{font-size:.88rem;color:var(--muted);line-height:1.6;}

.alert{padding:14px 18px;border-radius:12px;font-size:.82rem;margin-bottom:1.8rem;display:flex;align-items:center;gap:12px;animation:slideDown .4s ease;border:2px solid;}
@keyframes slideDown{from{opacity:0;transform:translateY(-12px);}to{opacity:1;transform:translateY(0);}}
.alert.error{background:#fee2e2;border-color:#fecaca;color:#dc2626;}
.alert.success{background:#d1fae5;border-color:#86efac;color:#15803d;}
.alert i{font-size:1.1rem;flex-shrink:0;}

.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;}
.form-group{margin-bottom:1.3rem;}
.form-label{font-size:.78rem;font-weight:600;color:var(--sub);margin-bottom:8px;display:flex;align-items:center;gap:6px;}
.form-label i{color:var(--g500);font-size:.7rem;}
.input-wrapper{position:relative;}
.input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.85rem;transition:color .2s;}
.form-input{width:100%;padding:12px 16px 12px 42px;background:#fff;border:2px solid #e8f0e9;border-radius:12px;font-family:'Kanit',sans-serif;font-size:.85rem;color:var(--txt);outline:none;transition:all .3s;}
.form-input:focus{border-color:var(--g400);box-shadow:0 0 0 4px rgba(74,222,128,.12);background:#fff;}
.form-input:focus + .input-icon{color:var(--g500);}

/* ✅ Password Strength Indicator */
.password-requirements{
  margin-top:10px;
  padding:12px 14px;
  background:var(--g50);
  border:1px solid var(--g200);
  border-radius:10px;
  font-size:.75rem;
}
.password-requirements h4{
  font-size:.8rem;
  font-weight:700;
  color:var(--g700);
  margin-bottom:8px;
  display:flex;
  align-items:center;
  gap:6px;
}
.req-item{
  display:flex;
  align-items:center;
  gap:6px;
  margin:4px 0;
  color:var(--sub);
  transition:color .2s;
}
.req-item i{
  font-size:.7rem;
  color:#cbd5e1;
  transition:color .2s;
}
.req-item.valid{
  color:var(--g600);
  font-weight:600;
}
.req-item.valid i{
  color:var(--g500);
}
.password-strength{
  margin-top:8px;
  height:4px;
  background:#e5e7eb;
  border-radius:2px;
  overflow:hidden;
}
.strength-bar{
  height:100%;
  width:0;
  transition:all .3s;
  border-radius:2px;
}
.strength-bar.weak{width:25%;background:#ef4444;}
.strength-bar.fair{width:50%;background:#f59e0b;}
.strength-bar.good{width:75%;background:#3b82f6;}
.strength-bar.strong{width:100%;background:var(--g500);}

.btn-register{background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;font-size:.88rem;font-weight:700;padding:14px 28px;border-radius:12px;border:none;cursor:pointer;transition:all .3s;box-shadow:0 6px 20px rgba(34,197,94,.35);width:100%;font-family:'Kanit',sans-serif;position:relative;overflow:hidden;margin-top:.8rem;}
.btn-register::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,transparent,rgba(255,255,255,.25),transparent);transform:translateX(-100%);transition:transform .7s;}
.btn-register:hover::before{transform:translateX(100%);}
.btn-register:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(34,197,94,.45);}
.btn-register:disabled{opacity:.5;cursor:not-allowed;transform:none;}

.login-link{text-align:center;margin-top:1.8rem;padding:16px;background:var(--g50);border-radius:12px;border:2px dashed var(--g200);}
.login-link p{font-size:.85rem;color:var(--sub);}
.login-link a{color:var(--g600);font-weight:700;text-decoration:none;transition:all .2s;}
.login-link a:hover{color:var(--g700);text-decoration:underline;}

@media (max-width:640px){
  .form-grid{grid-template-columns:1fr;}
  .card{padding:2.5rem 2rem;}
  body{padding:1.5rem 1rem;}
}
</style>
</head>
<body>

<div class="float-shape"></div>
<div class="float-shape"></div>
<div class="float-shape"></div>

<div class="container">
  <div class="card">
    <div class="header">
      <div class="logo"><i class="fas fa-utensils" style="color: #ffffff;"></i></div>
      <h1 class="title">สร้างบัญชีใหม่</h1>
      <p class="subtitle">เริ่มต้นใช้งาน FoodAI เพื่อวางแผนอาหารเพื่อสุขภาพที่ดี</p>
    </div>

    <?php if($error): ?>
    <div class="alert error">
      <i class="fas fa-exclamation-circle"></i>
      <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>
    
    <?php if($success): ?>
    <div class="alert success">
      <i class="fas fa-check-circle"></i>
      <span><?= htmlspecialchars($success) ?></span>
    </div>
    <?php endif; ?>

    <form action="" method="POST" id="registerForm">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-user"></i> ชื่อจริง
          </label>
          <div class="input-wrapper">
            <input type="text" name="first_name" class="form-input" placeholder="ชื่อ" required>
            <i class="fas fa-user input-icon"></i>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-user"></i> นามสกุล
          </label>
          <div class="input-wrapper">
            <input type="text" name="last_name" class="form-input" placeholder="นามสกุล" required>
            <i class="fas fa-user input-icon"></i>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">
          <i class="fas fa-at"></i> ชื่อผู้ใช้ (Username)
        </label>
        <div class="input-wrapper">
          <input type="text" name="username" class="form-input" placeholder="username123" required>
          <i class="fas fa-id-badge input-icon"></i>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">
          <i class="fas fa-envelope"></i> อีเมล
        </label>
        <div class="input-wrapper">
          <input type="email" name="email" class="form-input" placeholder="your@email.com" required>
          <i class="fas fa-at input-icon"></i>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">
          <i class="fas fa-lock"></i> รหัสผ่าน
        </label>
        <div class="input-wrapper">
          <input type="password" name="password" id="password" class="form-input" placeholder="••••••••" required>
          <i class="fas fa-key input-icon"></i>
        </div>
        
        <!-- ✅ Password Requirements -->
        <div class="password-requirements">
          <h4><i class="fas fa-shield-alt"></i> ความแข็งแกร่งของรหัสผ่าน</h4>
          <div class="req-item" id="req-length">
            <i class="fas fa-circle"></i>
            <span>อย่างน้อย 8 ตัวอักษร</span>
          </div>
          <div class="req-item" id="req-upper">
            <i class="fas fa-circle"></i>
            <span>ตัวพิมพ์ใหญ่ (A-Z)</span>
          </div>
          <div class="req-item" id="req-lower">
            <i class="fas fa-circle"></i>
            <span>ตัวพิมพ์เล็ก (a-z)</span>
          </div>
          <div class="req-item" id="req-number">
            <i class="fas fa-circle"></i>
            <span>ตัวเลข (0-9)</span>
          </div>
          <div class="req-item" id="req-special">
            <i class="fas fa-circle"></i>
            <span>อักขระพิเศษ (!@#$%^&*)</span>
          </div>
          <div class="password-strength">
            <div class="strength-bar" id="strengthBar"></div>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">
          <i class="fas fa-lock"></i> ยืนยันรหัสผ่าน
        </label>
        <div class="input-wrapper">
          <input type="password" name="confirm_password" id="confirmPassword" class="form-input" placeholder="••••••••" required>
          <i class="fas fa-key input-icon"></i>
        </div>
        <div id="matchMessage" style="margin-top:8px;font-size:.75rem;"></div>
      </div>

      <button type="submit" class="btn-register" id="submitBtn">
        <i class="fas fa-user-plus" style="margin-right:8px;"></i> ลงทะเบียน
      </button>
    </form>

    <div class="login-link">
      <p>
        มีบัญชีอยู่แล้ว? <a href="login.php">
          <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
        </a>
      </p>
    </div>
  </div>
</div>

<script>
const password = document.getElementById('password');
const confirmPassword = document.getElementById('confirmPassword');
const submitBtn = document.getElementById('submitBtn');
const strengthBar = document.getElementById('strengthBar');

// Password validation
password.addEventListener('input', function() {
  const value = this.value;
  let strength = 0;
  
  // Check length
  const hasLength = value.length >= 8;
  document.getElementById('req-length').classList.toggle('valid', hasLength);
  if (hasLength) strength++;
  
  // Check uppercase
  const hasUpper = /[A-Z]/.test(value);
  document.getElementById('req-upper').classList.toggle('valid', hasUpper);
  if (hasUpper) strength++;
  
  // Check lowercase
  const hasLower = /[a-z]/.test(value);
  document.getElementById('req-lower').classList.toggle('valid', hasLower);
  if (hasLower) strength++;
  
  // Check number
  const hasNumber = /[0-9]/.test(value);
  document.getElementById('req-number').classList.toggle('valid', hasNumber);
  if (hasNumber) strength++;
  
  // Check special character
  const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(value);
  document.getElementById('req-special').classList.toggle('valid', hasSpecial);
  if (hasSpecial) strength++;
  
  // Update strength bar
  strengthBar.className = 'strength-bar';
  if (strength === 0) strengthBar.className = 'strength-bar';
  else if (strength <= 2) strengthBar.className = 'strength-bar weak';
  else if (strength === 3) strengthBar.className = 'strength-bar fair';
  else if (strength === 4) strengthBar.className = 'strength-bar good';
  else strengthBar.className = 'strength-bar strong';
  
  checkPasswordMatch();
});

// Confirm password validation
confirmPassword.addEventListener('input', checkPasswordMatch);

function checkPasswordMatch() {
  const matchMsg = document.getElementById('matchMessage');
  if (confirmPassword.value === '') {
    matchMsg.innerHTML = '';
    return;
  }
  
  if (password.value === confirmPassword.value) {
    matchMsg.innerHTML = '<span style="color:var(--g600);"><i class="fas fa-check-circle"></i> รหัสผ่านตรงกัน</span>';
  } else {
    matchMsg.innerHTML = '<span style="color:#dc2626;"><i class="fas fa-times-circle"></i> รหัสผ่านไม่ตรงกัน</span>';
  }
}

// Form validation
document.getElementById('registerForm').addEventListener('submit', function(e) {
  const pwd = password.value;
  
  if (pwd.length < 8) {
    e.preventDefault();
    alert('รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร');
    return;
  }
  
  if (!/[A-Z]/.test(pwd)) {
    e.preventDefault();
    alert('รหัสผ่านต้องมีตัวพิมพ์ใหญ่อย่างน้อย 1 ตัว (A-Z)');
    return;
  }
  
  if (!/[a-z]/.test(pwd)) {
    e.preventDefault();
    alert('รหัสผ่านต้องมีตัวพิมพ์เล็กอย่างน้อย 1 ตัว (a-z)');
    return;
  }
  
  if (!/[0-9]/.test(pwd)) {
    e.preventDefault();
    alert('รหัสผ่านต้องมีตัวเลขอย่างน้อย 1 ตัว (0-9)');
    return;
  }
  
  if (!/[!@#$%^&*(),.?":{}|<>]/.test(pwd)) {
    e.preventDefault();
    alert('รหัสผ่านต้องมีอักขระพิเศษอย่างน้อย 1 ตัว (!@#$%^&*)');
    return;
  }
  
  if (pwd !== confirmPassword.value) {
    e.preventDefault();
    alert('รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน');
    return;
  }
});
</script>

</body>
</html>