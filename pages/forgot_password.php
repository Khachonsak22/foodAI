<?php
session_start();
include '../config/connect.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$step = $_GET['step'] ?? 'request'; // request, verify, reset
$error_msg = '';
$success_msg = '';

/* ══════════════════════════════════════════════════════════════════
   STEP 1: REQUEST OTP
   ══════════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_otp') {
    
    $identifier = trim($_POST['identifier'] ?? ''); // email or phone
    
    if (empty($identifier)) {
        $error_msg = 'กรุณากรอกอีเมลหรือเบอร์โทรศัพท์';
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, email, username FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user) {
            // Generate 6-digit OTP
            $otp = sprintf("%06d", mt_rand(0, 999999));
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Store OTP in session (In production, store in database)
            $_SESSION['password_reset'] = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                // โค้ดเดิม: สร้าง OTP และเก็บลง Session
            $otp = sprintf("%06d", mt_rand(0, 999999)),
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes')),
            ];
            $_SESSION['password_reset'] = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'otp' => $otp,
                'expires_at' => $expires_at
            ];
            
            // ── โค้ดใหม่: การส่งอีเมลด้วยฟังก์ชัน mail() ของ PHP ──
            $to = $user['email'];
            $subject = "รหัส OTP สำหรับรีเซ็ทรหัสผ่านของคุณ (FoodAI)";
            
            // รูปแบบข้อความในอีเมล
            $message = "
            <html>
            <head>
              <title>รหัส OTP ของคุณ</title>
            </head>
            <body>
              <h2>คำขอรีเซ็ทรหัสผ่าน FoodAI</h2>
              <p>นี่คือรหัส OTP 6 หลักของคุณ (รหัสมีอายุการใช้งาน 10 นาที):</p>
              <h1 style='color: #22c55e; letter-spacing: 5px; font-size: 32px;'>{$otp}</h1>
              <p>หากคุณไม่ได้ทำรายการนี้ กรุณาเพิกเฉยต่ออีเมลฉบับนี้</p>
            </body>
            </html>
            ";
            
            // กำหนด Header เพื่อให้ส่งเป็น HTML และระบุผู้ส่ง
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: FoodAI Support <noreply@yourdomain.com>" . "\r\n"; 
            // หมายเหตุ: ควรเปลี่ยน yourdomain.com เป็นโดเมนจริงของคุณ
            
            // ทำการส่งอีเมล
            if(mail($to, $subject, $message, $headers)) {
                $success_msg = "ส่งรหัส OTP ไปยัง " . substr($user['email'], 0, 3) . "***@*** แล้ว";
            } else {
                // กรณีที่ Server ไม่รองรับการส่งอีเมล ให้แสดง OTP ไว้บนหน้าจอชั่วคราว (เพื่อใช้ทดสอบ)
                $success_msg = "[โหมดทดสอบ] ส่งอีเมลไม่สำเร็จ กรุณาใช้ OTP: $otp ไปก่อน";
            }
            // ───────────────────────────────────────────────

            header("Location: forgot_password.php?step=verify");
            exit();
    }
}

/* ══════════════════════════════════════════════════════════════════
   STEP 2: VERIFY OTP
   ══════════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    
    $otp_input = trim($_POST['otp'] ?? '');
    
    if (empty($otp_input)) {
        $error_msg = 'กรุณากรอกรหัส OTP';
    } elseif (!isset($_SESSION['password_reset'])) {
        $error_msg = 'เซสชันหมดอายุ กรุณาเริ่มใหม่';
        header("Location: forgot_password.php?step=request");
        exit();
    } else {
        $reset_data = $_SESSION['password_reset'];
        
        // Check expiration
        if (strtotime($reset_data['expires_at']) < time()) {
            $error_msg = 'รหัส OTP หมดอายุแล้ว กรุณาขอใหม่';
            unset($_SESSION['password_reset']);
        } elseif ($otp_input !== $reset_data['otp']) {
            $error_msg = 'รหัส OTP ไม่ถูกต้อง';
        } else {
            // OTP verified, proceed to reset
            $_SESSION['password_reset']['verified'] = true;
            header("Location: forgot_password.php?step=reset");
            exit();
        }
    }
}

/* ══════════════════════════════════════════════════════════════════
   STEP 3: RESET PASSWORD
   ══════════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error_msg = 'กรุณากรอกรหัสผ่านให้ครบถ้วน';
    } elseif ($new_password !== $confirm_password) {
        $error_msg = 'รหัสผ่านไม่ตรงกัน';
    } elseif (strlen($new_password) < 6) {
        $error_msg = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    } elseif (!isset($_SESSION['password_reset']['verified'])) {
        $error_msg = 'เซสชันไม่ถูกต้อง กรุณาเริ่มใหม่';
        header("Location: forgot_password.php?step=request");
        exit();
    } else {
        $user_id = $_SESSION['password_reset']['user_id'];
        $password_hash = hash('sha256', $new_password);
        
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $password_hash, $user_id);
        
        if ($stmt->execute()) {
            unset($_SESSION['password_reset']);
            $success_msg = 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว กรุณาเข้าสู่ระบบ';
            
            // Redirect to login after 2 seconds
            header("Refresh: 2; url=login.php");
        } else {
            $error_msg = 'เกิดข้อผิดพลาดในการบันทึก';
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
<title>ลืมรหัสผ่าน — FoodAI</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
  --g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;
  --g400:#4ade80;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;
  --t400:#2dd4bf;--t500:#14b8a6;--t600:#0d9488;
  --bg:#f5f8f5;--txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;position:relative;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,#c8e6c9 1px,transparent 1px);background-size:28px 28px;opacity:.35;}

.container{max-width:480px;width:100%;position:relative;z-index:1;}
.card{background:#fff;border:1px solid #e8f0e9;border-radius:24px;padding:2.5rem;box-shadow:0 12px 48px rgba(34,197,94,.08);}
.logo{width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,var(--g500),var(--t500));display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 1.5rem;box-shadow:0 6px 20px rgba(34,197,94,.3);}
.title{font-family:'Nunito',sans-serif;font-size:1.6rem;font-weight:800;color:var(--txt);text-align:center;margin-bottom:.5rem;}
.subtitle{font-size:.82rem;color:var(--muted);text-align:center;margin-bottom:2rem;line-height:1.6;}

.form-group{margin-bottom:1.2rem;}
.form-label{font-size:.78rem;font-weight:600;color:var(--sub);margin-bottom:7px;display:block;}
.form-input{width:100%;padding:12px 16px;background:var(--g50);border:1.5px solid #e8f0e9;border-radius:12px;font-family:'Kanit',sans-serif;font-size:.88rem;color:var(--txt);outline:none;transition:all .18s;}
.form-input:focus{border-color:var(--g400);box-shadow:0 0 0 3px rgba(74,222,128,.12);}

.otp-inputs{display:flex;gap:10px;justify-content:center;}
.otp-input{width:54px;height:54px;text-align:center;font-size:1.4rem;font-weight:700;font-family:'Nunito',sans-serif;background:var(--g50);border:2px solid #e8f0e9;border-radius:12px;outline:none;transition:all .18s;}
.otp-input:focus{border-color:var(--g500);background:#fff;box-shadow:0 0 0 3px rgba(74,222,128,.12);}

.btn-green{background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;font-size:.85rem;font-weight:700;padding:13px 28px;border-radius:13px;border:none;cursor:pointer;transition:all .2s;box-shadow:0 4px 16px rgba(34,197,94,.3);width:100%;font-family:'Kanit',sans-serif;}
.btn-green:hover{opacity:.88;box-shadow:0 6px 20px rgba(34,197,94,.4);}

.alert{padding:14px 18px;border-radius:13px;font-size:.82rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:12px;}
.alert.success{background:var(--g50);border:1.5px solid var(--g300);color:var(--g700);}
.alert.error{background:#fee2e2;border:1.5px solid #fecaca;color:#dc2626;}

.link{color:var(--g600);text-decoration:none;font-weight:600;transition:color .18s;}
.link:hover{color:var(--g700);}

.steps{display:flex;justify-content:center;gap:12px;margin-bottom:2rem;}
.step{width:32px;height:4px;background:#e8f0e9;border-radius:99px;transition:background .3s;}
.step.active{background:linear-gradient(90deg,var(--g500),var(--t500));}

@media (max-width: 640px){
  .card{padding:2rem 1.5rem;}
  .title{font-size:1.3rem;}
  .otp-input{width:48px;height:48px;font-size:1.2rem;}
}
</style>
</head>
<body>

<div class="container">
  <div class="card">
    <div class="logo">🔐</div>
    
    <?php if ($success_msg): ?>
    <div class="alert success">
      <i class="fas fa-check-circle" style="font-size:1.1rem;"></i>
      <span><?= htmlspecialchars($success_msg) ?></span>
    </div>
    <?php endif; ?>
    
    <?php if ($error_msg): ?>
    <div class="alert error">
      <i class="fas fa-exclamation-circle" style="font-size:1.1rem;"></i>
      <span><?= htmlspecialchars($error_msg) ?></span>
    </div>
    <?php endif; ?>
    
    <!-- STEP INDICATOR -->
    <div class="steps">
      <div class="step <?= $step==='request'?'active':'' ?>"></div>
      <div class="step <?= $step==='verify'?'active':'' ?>"></div>
      <div class="step <?= $step==='reset'?'active':'' ?>"></div>
    </div>
    
    <?php if ($step === 'request'): ?>
    <!-- STEP 1: REQUEST OTP -->
    <h1 class="title">ลืมรหัสผ่าน</h1>
    <p class="subtitle">กรอกอีเมลหรือชื่อผู้ใช้เพื่อรับรหัส OTP</p>
    
    <form method="POST">
      <input type="hidden" name="action" value="request_otp">
      
      <div class="form-group">
        <label class="form-label">อีเมลหรือชื่อผู้ใช้</label>
        <input type="text" name="identifier" class="form-input" placeholder="your@email.com" required autofocus>
      </div>
      
      <button type="submit" class="btn-green">
        <i class="fas fa-paper-plane" style="margin-right:8px;"></i> ส่งรหัส OTP
      </button>
    </form>
    
    <?php elseif ($step === 'verify'): ?>
    <!-- STEP 2: VERIFY OTP -->
    <h1 class="title">ยืนยันรหัส OTP</h1>
    <p class="subtitle">
      กรอกรหัส 6 หลักที่ส่งไปยัง<br>
      <strong><?= isset($_SESSION['password_reset']) ? substr($_SESSION['password_reset']['email'], 0, 3).'***@***' : '' ?></strong>
    </p>
    
    <form method="POST">
      <input type="hidden" name="action" value="verify_otp">
      
      <div class="form-group">
        <div class="otp-inputs" id="otpInputs">
          <input type="text" class="otp-input" maxlength="1" pattern="\d" required>
          <input type="text" class="otp-input" maxlength="1" pattern="\d" required>
          <input type="text" class="otp-input" maxlength="1" pattern="\d" required>
          <input type="text" class="otp-input" maxlength="1" pattern="\d" required>
          <input type="text" class="otp-input" maxlength="1" pattern="\d" required>
          <input type="text" class="otp-input" maxlength="1" pattern="\d" required>
        </div>
        <input type="hidden" name="otp" id="otpValue">
      </div>
      
      <button type="submit" class="btn-green">
        <i class="fas fa-check" style="margin-right:8px;"></i> ยืนยัน
      </button>
    </form>
    
    <?php elseif ($step === 'reset' && isset($_SESSION['password_reset']['verified'])): ?>
    <!-- STEP 3: RESET PASSWORD -->
    <h1 class="title">ตั้งรหัสผ่านใหม่</h1>
    <p class="subtitle">กรุณากรอกรหัสผ่านใหม่ของคุณ</p>
    
    <form method="POST">
      <input type="hidden" name="action" value="reset_password">
      
      <div class="form-group">
        <label class="form-label">รหัสผ่านใหม่</label>
        <input type="password" name="new_password" class="form-input" placeholder="อย่างน้อย 6 ตัวอักษร" required autofocus>
      </div>
      
      <div class="form-group">
        <label class="form-label">ยืนยันรหัสผ่าน</label>
        <input type="password" name="confirm_password" class="form-input" placeholder="พิมพ์รหัสผ่านอีกครั้ง" required>
      </div>
      
      <button type="submit" class="btn-green">
        <i class="fas fa-save" style="margin-right:8px;"></i> บันทึกรหัสผ่าน
      </button>
    </form>
    
    <?php else: ?>
    <!-- INVALID STEP -->
    <div class="alert error">
      <i class="fas fa-exclamation-triangle"></i>
      <span>เซสชันไม่ถูกต้อง กรุณาเริ่มใหม่</span>
    </div>
    <a href="forgot_password.php?step=request" class="btn-green" style="display:block;text-align:center;text-decoration:none;">
      เริ่มใหม่
    </a>
    <?php endif; ?>
    
    <div style="text-align:center;margin-top:1.5rem;">
      <a href="login.php" class="link">
        <i class="fas fa-arrow-left" style="margin-right:5px;"></i> กลับไปหน้าเข้าสู่ระบบ
      </a>
    </div>
  </div>
</div>

<script>
// OTP Input handling
const otpInputs = document.querySelectorAll('.otp-input');
if (otpInputs.length > 0) {
  otpInputs.forEach((input, index) => {
    input.addEventListener('input', (e) => {
      if (e.target.value.length === 1 && index < otpInputs.length - 1) {
        otpInputs[index + 1].focus();
      }
      updateOTPValue();
    });
    
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
        otpInputs[index - 1].focus();
      }
    });
    
    input.addEventListener('paste', (e) => {
      e.preventDefault();
      const paste = e.clipboardData.getData('text');
      const digits = paste.replace(/\D/g, '').split('');
      digits.forEach((digit, i) => {
        if (index + i < otpInputs.length) {
          otpInputs[index + i].value = digit;
        }
      });
      const lastFilledIndex = Math.min(index + digits.length, otpInputs.length - 1);
      otpInputs[lastFilledIndex].focus();
      updateOTPValue();
    });
  });
  
  function updateOTPValue() {
    const otp = Array.from(otpInputs).map(input => input.value).join('');
    document.getElementById('otpValue').value = otp;
  }
  
  otpInputs[0].focus();
}
</script>

</body>
</html>