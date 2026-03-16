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
    
    $identifier = trim($_POST['identifier'] ?? ''); // email or username
    
    if (empty($identifier)) {
        $error_msg = 'กรุณากรอกอีเมลหรือชื่อผู้ใช้';
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, email, username, first_name FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user) {
            // Generate 6-digit OTP
            $otp = sprintf("%06d", mt_rand(0, 999999));
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Store OTP in session
            $_SESSION['password_reset'] = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'otp' => $otp,
                'expires_at' => $expires_at
            ];
            
            // เตรียมข้อมูลสำหรับส่งอีเมล
            $to = $user['email'];
            $subject = "🔐 รหัส OTP สำหรับรีเซ็ตรหัสผ่าน - FoodAI";
            $user_name = $user['first_name'] ?: $user['username'];
            
            // รูปแบบอีเมลของคุณที่ออกแบบไว้
            $message = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f8f5; margin: 0; padding: 20px; }
                    .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(34,197,94,0.1); }
                    .header { background: linear-gradient(135deg, #22c55e, #14b8a6); padding: 40px 30px; text-align: center; }
                    .header h1 { color: #ffffff; margin: 0; font-size: 28px; }
                    .content { padding: 40px 30px; }
                    .otp-box { background: #f0fdf4; border: 2px solid #86efac; border-radius: 15px; padding: 30px; text-align: center; margin: 30px 0; }
                    .otp-code { font-size: 48px; font-weight: bold; color: #16a34a; letter-spacing: 8px; margin: 10px 0; font-family: 'Courier New', monospace; }
                    .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 8px; }
                    .footer { background: #f8faf9; padding: 20px 30px; text-align: center; font-size: 13px; color: #8da98f; }
                    .btn { display: inline-block; background: linear-gradient(135deg, #22c55e, #14b8a6); color: #ffffff !important; padding: 14px 32px; text-decoration: none; border-radius: 12px; font-weight: bold; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>FoodAI</h1>
                        <p style='color: #dcfce7; margin: 10px 0 0 0;'>Password Reset Request</p>
                    </div>
                    <div class='content'>
                        <h2 style='color: #1a2e1a; margin-bottom: 10px;'>สวัสดีคุณ {$user_name} 👋</h2>
                        <p style='color: #4b6b4e; line-height: 1.6;'>เราได้รับคำขอรีเซ็ตรหัสผ่านของคุณ กรุณาใช้รหัส OTP ด้านล่างเพื่อดำเนินการต่อ</p>
                        
                        <div class='otp-box'>
                            <p style='margin: 0; color: #4b6b4e; font-size: 14px;'>รหัส OTP ของคุณ</p>
                            <div class='otp-code'>{$otp}</div>
                            <p style='margin: 10px 0 0 0; color: #8da98f; font-size: 13px;'>⏱️ รหัสนี้จะหมดอายุใน 10 นาที</p>
                        </div>
                        
                        <div class='warning'>
                            <p style='margin: 0; color: #92400e; font-size: 14px;'><strong>⚠️ คำเตือน:</strong> หากคุณไม่ได้ทำรายการนี้ กรุณาเพิกเฉยต่ออีเมลนี้ และแจ้งทีมงานทันที</p>
                        </div>
                        
                        <p style='color: #8da98f; font-size: 13px; text-align: center; margin-top: 30px;'>
                            หากปุ่มไม่ทำงาน กรุณาคัดลอกรหัส OTP ด้านบนไปใส่ในหน้าเว็บ
                        </p>
                    </div>
                    <div class='footer'>
                        <p style='margin: 0;'>© 2026 FoodAI - AI-Powered Nutrition Planning</p>
                        <p style='margin: 5px 0 0 0;'>อีเมลนี้ส่งอัตโนมัติ กรุณาอย่าตอบกลับ</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            // ── โค้ดที่อัปเดต: การส่งอีเมลด้วย PHPMailer + Gmail SMTP ──
            require '../vendor/autoload.php'; 
            
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                
                // อย่าลืมแก้ไข 2 บรรทัดนี้ให้เป็นข้อมูลของคุณ
                $mail->Username   = 'khachonc22@gmail.com'; 
                $mail->Password   = 'gnvhqhmbtrlzlnzy'; 
                
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';

                // อย่าลืมแก้ไขอีเมลผู้ส่งตรงนี้ด้วยครับ
                $mail->setFrom('khachonc22@gmail.com', 'FoodAI Support');
                $mail->addAddress($to);

                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $message;

                $mail->send();
                $success_msg = "ส่งรหัส OTP ไปยัง " . substr($user['email'], 0, 3) . "***@*** แล้ว";
            } catch (Exception $e) {
                // โหมดทดสอบ: แสดง OTP บนหน้าจอ หากส่งเมลพลาด
                $success_msg = "[โหมดทดสอบ] OTP ของคุณคือ: <strong>$otp</strong> (ระบบส่งอีเมลยังไม่พร้อม)";
            }
            // ───────────────────────────────────────────────

            header("Location: forgot_password.php?step=verify");
            exit();
        } else {
            $error_msg = 'ไม่พบข้อมูลผู้ใช้งานนี้ในระบบ';
        }
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
            $error_msg = 'รหัส OTP ไม่ถูกต้อง กรุณาลองใหม่';
        } else {
            // OTP verified
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
    } elseif (strlen($new_password) < 8) {
        $error_msg = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error_msg = 'รหัสผ่านต้องมีตัวพิมพ์ใหญ่อย่างน้อย 1 ตัว';
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $error_msg = 'รหัสผ่านต้องมีตัวพิมพ์เล็กอย่างน้อย 1 ตัว';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error_msg = 'รหัสผ่านต้องมีตัวเลขอย่างน้อย 1 ตัว';
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
        $error_msg = 'รหัสผ่านต้องมีอักขระพิเศษอย่างน้อย 1 ตัว';
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
            $success_msg = '✅ เปลี่ยนรหัสผ่านเรียบร้อยแล้ว! กำลังพาคุณไปหน้าเข้าสู่ระบบ...';
            header("Refresh: 2; url=login.php");
        } else {
            $error_msg = 'เกิดข้อผิดพลาดในการบันทึก';
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
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;--g400:#4ade80;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--t400:#2dd4bf;--t500:#14b8a6;--bg:#f5f8f5;--txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;position:relative;overflow-x:hidden;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background:linear-gradient(135deg,#f0fdf4 0%,#dcfce7 50%,#e0f2fe 100%);opacity:.6;}
body::after{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle at 20% 50%,rgba(74,222,128,.15) 0%,transparent 50%),radial-gradient(circle at 80% 80%,rgba(45,212,191,.12) 0%,transparent 50%);animation:bgPulse 8s ease-in-out infinite;}
@keyframes bgPulse{0%,100%{opacity:1;}50%{opacity:.6;}}

.float-shape{position:fixed;border-radius:50%;opacity:.08;pointer-events:none;z-index:0;}
.float-shape:nth-child(1){width:400px;height:400px;background:linear-gradient(135deg,var(--g400),var(--t400));top:-150px;right:-150px;animation:float1 20s ease-in-out infinite;}
.float-shape:nth-child(2){width:300px;height:300px;background:linear-gradient(135deg,var(--t500),var(--g500));bottom:-100px;left:-100px;animation:float2 18s ease-in-out infinite;}
@keyframes float1{0%,100%{transform:translate(0,0);}50%{transform:translate(-40px,40px);}}
@keyframes float2{0%,100%{transform:translate(0,0);}50%{transform:translate(40px,-40px);}}

.container{max-width:500px;width:100%;position:relative;z-index:1;}
.card{background:rgba(255,255,255,.95);backdrop-filter:blur(20px);border:1px solid rgba(232,240,233,.8);border-radius:32px;padding:3rem 2.8rem;box-shadow:0 25px 70px rgba(34,197,94,.15),0 10px 30px rgba(0,0,0,.05);position:relative;overflow:hidden;}
.card::before{content:'';position:absolute;top:0;left:0;right:0;height:6px;background:linear-gradient(90deg,var(--g500),var(--t500),var(--g400));opacity:.8;}

.logo-wrapper{display:flex;justify-content:center;margin-bottom:1.5rem;}
.logo{width:80px;height:80px;border-radius:20px;background:linear-gradient(135deg,var(--g500),var(--t500));display:flex;align-items:center;justify-content:center;font-size:2.2rem;box-shadow:0 8px 24px rgba(34,197,94,.35),0 0 0 6px rgba(74,222,128,.1);animation:logoFloat 4s ease-in-out infinite;}
@keyframes logoFloat{0%,100%{transform:translateY(0);}50%{transform:translateY(-8px);}}

.title{font-family:'Nunito',sans-serif;font-size:1.8rem;font-weight:800;color:var(--txt);text-align:center;margin-bottom:.6rem;}
.subtitle{font-size:.88rem;color:var(--muted);text-align:center;margin-bottom:2rem;line-height:1.6;}

.steps{display:flex;justify-content:center;gap:10px;margin-bottom:2rem;}
.step-dot{width:40px;height:6px;background:#e8f0e9;border-radius:99px;transition:all .3s;}
.step-dot.active{background:linear-gradient(90deg,var(--g500),var(--t500));box-shadow:0 2px 8px rgba(34,197,94,.3);}

.form-group{margin-bottom:1.3rem;}
.form-label{font-size:.82rem;font-weight:600;color:var(--sub);margin-bottom:8px;display:flex;align-items:center;gap:6px;}
.form-label i{color:var(--g500);font-size:.75rem;}

/* Added wrapper and toggle classes for password eye icon */
.password-wrapper { position: relative; }
.form-input{width:100%;padding:13px 18px;background:#fff;border:2px solid #e8f0e9;border-radius:12px;font-family:'Kanit',sans-serif;font-size:.9rem;color:var(--txt);outline:none;transition:all .3s;}
.form-input:focus{border-color:var(--g400);box-shadow:0 0 0 4px rgba(74,222,128,.12);}
.form-input.pwd-input { padding-right: 45px; }
.toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--muted); cursor: pointer; padding: 0; outline: none; transition: color 0.2s; font-size: 1rem; }
.toggle-password:hover { color: var(--g600); }

.otp-inputs{display:flex;gap:10px;justify-content:center;margin:1.5rem 0;}
.otp-input{width:56px;height:56px;text-align:center;font-size:1.5rem;font-weight:700;font-family:'Nunito',sans-serif;background:#fff;border:2px solid #e8f0e9;border-radius:14px;outline:none;transition:all .2s;}
.otp-input:focus{border-color:var(--g500);background:#fff;box-shadow:0 0 0 4px rgba(74,222,128,.12);transform:scale(1.05);}

.password-requirements{margin-top:10px;padding:12px 14px;background:var(--g50);border:1px solid var(--g200);border-radius:10px;font-size:.75rem;}
.password-requirements h4{font-size:.8rem;font-weight:700;color:var(--g700);margin-bottom:8px;}
.req-item{display:flex;align-items:center;gap:6px;margin:4px 0;color:var(--sub); transition: all 0.3s;}
.req-item i{font-size:.7rem;color:#cbd5e1; transition: all 0.3s;}

/* CSS สำหรับตอนที่เงื่อนไขผ่านแล้ว (Real-time Validation) */
.req-item.valid { color: var(--g700); font-weight: 500; }
.req-item.valid i { color: var(--g500); }

.btn-primary{background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;font-size:.9rem;font-weight:700;padding:14px 28px;border-radius:13px;border:none;cursor:pointer;transition:all .3s;box-shadow:0 6px 20px rgba(34,197,94,.35);width:100%;font-family:'Kanit',sans-serif;position:relative;overflow:hidden;}
.btn-primary::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,transparent,rgba(255,255,255,.25),transparent);transform:translateX(-100%);transition:transform .7s;}
.btn-primary:hover::before{transform:translateX(100%);}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(34,197,94,.45);}

.alert{padding:14px 18px;border-radius:12px;font-size:.85rem;margin-bottom:1.8rem;display:flex;align-items:center;gap:12px;animation:slideDown .4s ease;border:2px solid;}
@keyframes slideDown{from{opacity:0;transform:translateY(-12px);}to{opacity:1;transform:translateY(0);}}
.alert-success{background:#dcfce7;border-color:#86efac;color:#15803d;}
.alert-error{background:#fee2e2;border-color:#fecaca;color:#dc2626;}
.alert i{font-size:1.1rem;flex-shrink:0;}

.back-link{text-align:center;margin-top:1.5rem;}
.back-link a{color:var(--g600);font-size:.85rem;font-weight:600;text-decoration:none;transition:all .2s;display:inline-flex;align-items:center;gap:6px;}
.back-link a:hover{color:var(--g700);}

@media (max-width:520px){
  .card{padding:2.5rem 2rem;}
  .title{font-size:1.5rem;}
  .logo{width:70px;height:70px;font-size:2rem;}
  .otp-input{width:48px;height:48px;font-size:1.3rem;}
}
</style>
</head>
<body>

<div class="float-shape"></div>
<div class="float-shape"></div>

<div class="container">
  <div class="card">
    <div class="logo-wrapper">
      <div class="logo">🔐</div>
    </div>
    
    <?php if ($success_msg): ?>
    <div class="alert alert-success">
      <i class="fas fa-check-circle"></i>
      <span><?= $success_msg ?></span>
    </div>
    <?php endif; ?>
    
    <?php if ($error_msg): ?>
    <div class="alert alert-error">
      <i class="fas fa-exclamation-circle"></i>
      <span><?= htmlspecialchars($error_msg) ?></span>
    </div>
    <?php endif; ?>
    
    <div class="steps">
      <div class="step-dot <?= $step==='request'?'active':'' ?>"></div>
      <div class="step-dot <?= $step==='verify'?'active':'' ?>"></div>
      <div class="step-dot <?= $step==='reset'?'active':'' ?>"></div>
    </div>
    
    <?php if ($step === 'request'): ?>
    <h1 class="title">ลืมรหัสผ่าน?</h1>
    <p class="subtitle">กรอกอีเมลหรือชื่อผู้ใช้เพื่อรับรหัส OTP สำหรับรีเซ็ตรหัสผ่าน</p>
    
    <form method="POST">
      <input type="hidden" name="action" value="request_otp">
      
      <div class="form-group">
        <label class="form-label">
          <i class="fas fa-envelope"></i> อีเมลหรือชื่อผู้ใช้
        </label>
        <input type="text" name="identifier" class="form-input" placeholder="your@email.com หรือ username" required autofocus>
      </div>
      
      <button type="submit" class="btn-primary">
        <i class="fas fa-paper-plane" style="margin-right:8px;"></i> ส่งรหัส OTP
      </button>
    </form>
    
    <?php elseif ($step === 'verify'): ?>
    <h1 class="title">ยืนยันรหัส OTP</h1>
    <p class="subtitle">
      กรอกรหัส 6 หลักที่เราส่งไปยัง<br>
      <strong style="color:var(--g600);"><?= isset($_SESSION['password_reset']) ? substr($_SESSION['password_reset']['email'], 0, 3).'***@***' : '' ?></strong>
    </p>
    
    <form method="POST" id="otpForm">
      <input type="hidden" name="action" value="verify_otp">
      
      <div class="form-group">
        <div class="otp-inputs" id="otpInputs">
          <input type="text" class="otp-input" maxlength="1" pattern="\d" required autofocus>
          <input type="text" class="otp-input" maxlength="1" pattern="\d" required>
          <input type="text" class="otp-input" maxlength="1" pattern="\d" required>
          <input type="text" class="otp-input" maxlength="1" pattern="\d" required>
          <input type="text" class="otp-input" maxlength="1" pattern="\d" required>
          <input type="text" class="otp-input" maxlength="1" pattern="\d" required>
        </div>
        <input type="hidden" name="otp" id="otpValue">
      </div>
      
      <button type="submit" class="btn-primary">
        <i class="fas fa-check-circle" style="margin-right:8px;"></i> ยืนยันรหัส OTP
      </button>
    </form>
    
    <div style="text-align:center;margin-top:1rem;font-size:.8rem;color:var(--muted);">
      <p>ไม่ได้รับรหัส? <a href="forgot_password.php?step=request" style="color:var(--g600);font-weight:600;">ขอรหัสใหม่</a></p>
    </div>
    
    <?php elseif ($step === 'reset' && isset($_SESSION['password_reset']['verified'])): ?>
    <h1 class="title">ตั้งรหัสผ่านใหม่</h1>
    <p class="subtitle">กรุณากรอกรหัสผ่านใหม่ของคุณ</p>
    
    <form method="POST" id="resetForm">
      <input type="hidden" name="action" value="reset_password">
      
      <div class="form-group">
        <label class="form-label">
          <i class="fas fa-lock"></i> รหัสผ่านใหม่
        </label>
        <div class="password-wrapper">
          <input type="password" name="new_password" id="newPassword" class="form-input pwd-input" placeholder="อย่างน้อย 8 ตัวอักษร" required autofocus>
          <button type="button" class="toggle-password" onclick="toggleVisibility('newPassword', 'iconNewPwd')">
            <i class="fas fa-eye" id="iconNewPwd"></i>
          </button>
        </div>
        
        <div class="password-requirements">
          <h4><i class="fas fa-shield-alt"></i> ความแข็งแกร่งของรหัสผ่าน</h4>
          <div class="req-item" id="req-length"><i class="fas fa-circle"></i> <span>อย่างน้อย 8 ตัวอักษร</span></div>
          <div class="req-item" id="req-upper"><i class="fas fa-circle"></i> <span>ตัวพิมพ์ใหญ่ (A-Z)</span></div>
          <div class="req-item" id="req-lower"><i class="fas fa-circle"></i> <span>ตัวพิมพ์เล็ก (a-z)</span></div>
          <div class="req-item" id="req-number"><i class="fas fa-circle"></i> <span>ตัวเลข (0-9)</span></div>
          <div class="req-item" id="req-special"><i class="fas fa-circle"></i> <span>อักขระพิเศษ (!@#$%^&*)</span></div>
        </div>
      </div>
      
      <div class="form-group">
        <label class="form-label">
          <i class="fas fa-lock"></i> ยืนยันรหัสผ่าน
        </label>
        <div class="password-wrapper">
          <input type="password" name="confirm_password" id="confirmPassword" class="form-input pwd-input" placeholder="พิมพ์รหัสผ่านอีกครั้ง" required>
          <button type="button" class="toggle-password" onclick="toggleVisibility('confirmPassword', 'iconConfirmPwd')">
            <i class="fas fa-eye" id="iconConfirmPwd"></i>
          </button>
        </div>
      </div>
      
      <button type="submit" class="btn-primary" id="submitBtn">
        <i class="fas fa-save" style="margin-right:8px;"></i> บันทึกรหัสผ่านใหม่
      </button>
    </form>
    
    <?php else: ?>
    <div class="alert alert-error">
      <i class="fas fa-exclamation-triangle"></i>
      <span>เซสชันไม่ถูกต้อง กรุณาเริ่มต้นใหม่</span>
    </div>
    <a href="forgot_password.php?step=request" class="btn-primary" style="display:block;text-align:center;text-decoration:none;">
      <i class="fas fa-redo"></i> เริ่มต้นใหม่
    </a>
    <?php endif; ?>
    
    <div class="back-link">
      <a href="login.php">
        <i class="fas fa-arrow-left"></i> กลับไปหน้าเข้าสู่ระบบ
      </a>
    </div>
  </div>
</div>

<script>
// Toggle Password Visibility
function toggleVisibility(inputId, iconId) {
  const input = document.getElementById(inputId);
  const icon = document.getElementById(iconId);
  
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.remove('fa-eye');
    icon.classList.add('fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  }
}

// Real-time Password Validation
const newPasswordInput = document.getElementById('newPassword');
if (newPasswordInput) {
  newPasswordInput.addEventListener('input', function() {
    const val = this.value;
    
    // ตั้งค่าเงื่อนไข (Regex)
    const rules = [
      { id: 'req-length', regex: /.{8,}/ }, // 8 ตัวขึ้นไป
      { id: 'req-upper', regex: /[A-Z]/ },   // พิมพ์ใหญ่
      { id: 'req-lower', regex: /[a-z]/ },   // พิมพ์เล็ก
      { id: 'req-number', regex: /[0-9]/ },  // ตัวเลข
      { id: 'req-special', regex: /[!@#$%^&*(),.?":{}|<>]/ } // อักขระพิเศษ
    ];
    
    // ตรวจสอบแต่ละเงื่อนไข
    rules.forEach(rule => {
      const el = document.getElementById(rule.id);
      const icon = el.querySelector('i');
      
      if (rule.regex.test(val)) {
        // หากผ่านเงื่อนไข ให้เปลี่ยนคลาสเป็น valid และเปลี่ยนไอคอน
        el.classList.add('valid');
        icon.classList.remove('fa-circle');
        icon.classList.add('fa-check-circle');
      } else {
        // หากไม่ผ่านเงื่อนไข ให้ลบคลาส valid ออก และกลับไปใช้ไอคอนวงกลม
        el.classList.remove('valid');
        icon.classList.remove('fa-check-circle');
        icon.classList.add('fa-circle');
      }
    });
  });
}

// OTP Input handling
const otpInputs = document.querySelectorAll('.otp-input');
if (otpInputs.length > 0) {
  otpInputs.forEach((input, index) => {
    input.addEventListener('input', (e) => {
      // Only allow digits
      e.target.value = e.target.value.replace(/\D/g, '');
      
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
}
</script>

</body>
</html>