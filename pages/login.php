<?php

ini_set('display_errors',1);
ini_set('display_statu_errors',1);
error_reporting(E_ALL);

session_start();
include '../config/connect.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = hash('sha256', $_POST['password']); 

    $stmt = $conn->prepare("SELECT id, role, first_name FROM users WHERE email = ? AND password_hash = ?");
    $stmt->bind_param("ss", $email, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['first_name'] = $user['first_name']; 

        if (str_ends_with($email, '@admin.com')) {
            header("Location: ../admin/admin_dashboard.php");
            exit();
        } else {
            $stmt_h = $conn->prepare("SELECT user_id FROM health_profiles WHERE user_id = ?");
            $stmt_h->bind_param("i", $user['id']);
            $stmt_h->execute();
            $result_h = $stmt_h->get_result();
            
            if ($result_h->num_rows > 0) {
                header("Location: dashboard.php"); 
            } else {
                header("Location: setup_profile.php"); 
            }
            exit();
        }
    } else {
        $error = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เข้าสู่ระบบ — FoodAI</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;--g400:#4ade80;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--t400:#2dd4bf;--t500:#14b8a6;--bg:#f5f8f5;--txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;}
*{box-sizing:border-box;margin:0;padding:0;}

/* แก้ไขบรรทัดนี้: เปลี่ยน overflow:hidden เป็น overflow-x:hidden; overflow-y:auto; */
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;position:relative;overflow-x:hidden; overflow-y:auto;}

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

.container{max-width:480px;width:100%;position:relative;z-index:1;}
.card{background:rgba(255,255,255,.95);backdrop-filter:blur(20px);border:1px solid rgba(232,240,233,.8);border-radius:32px;padding:3.5rem 3rem;box-shadow:0 25px 70px rgba(34,197,94,.15),0 10px 30px rgba(0,0,0,.05);position:relative;overflow:hidden;}
.card::before{content:'';position:absolute;top:0;left:0;right:0;height:6px;background:linear-gradient(90deg,var(--g500),var(--t500),var(--g400));opacity:.8;}

.logo-wrapper{display:flex;justify-content:center;margin-bottom:2rem;}
.logo{width:90px;height:90px;border-radius:24px;background:linear-gradient(135deg,var(--g500),var(--t500));display:flex;align-items:center;justify-content:center;font-size:2.5rem;box-shadow:0 10px 30px rgba(34,197,94,.4),0 0 0 8px rgba(74,222,128,.1);animation:logoFloat 4s ease-in-out infinite;position:relative;}
.logo::before{content:'';position:absolute;inset:-8px;border-radius:28px;background:linear-gradient(135deg,rgba(74,222,128,.2),rgba(45,212,191,.2));z-index:-1;animation:logoPulse 2s ease-in-out infinite;}
@keyframes logoFloat{0%,100%{transform:translateY(0) rotate(0deg);}25%{transform:translateY(-8px) rotate(-2deg);}75%{transform:translateY(-8px) rotate(2deg);}}
@keyframes logoPulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.6;transform:scale(1.05);}}

.title{font-family:'Nunito',sans-serif;font-size:2.1rem;font-weight:800;color:var(--txt);text-align:center;margin-bottom:.6rem;line-height:1.2;}
.subtitle{font-size:.92rem;color:var(--muted);text-align:center;margin-bottom:2.5rem;line-height:1.6;}

.form-group{margin-bottom:1.5rem;}
.form-label{font-size:.82rem;font-weight:600;color:var(--sub);margin-bottom:10px;display:flex;align-items:center;gap:7px;}
.form-label i{color:var(--g500);font-size:.75rem;}
.input-wrapper{position:relative;}
.input-icon{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.9rem;transition:color .2s;}
.form-input{width:100%;padding:14px 18px 14px 46px;background:#fff;border:2px solid #e8f0e9;border-radius:14px;font-family:'Kanit',sans-serif;font-size:.9rem;color:var(--txt);outline:none;transition:all .3s;}
.form-input:focus{border-color:var(--g400);box-shadow:0 0 0 4px rgba(74,222,128,.15);background:#fff;}
.form-input:focus + .input-icon{color:var(--g500);}

.forgot-link{text-align:right;margin-bottom:1.5rem;}
.forgot-link a{color:var(--g600);font-size:.8rem;font-weight:600;text-decoration:none;transition:all .2s;}
.forgot-link a:hover{color:var(--g700);text-decoration:underline;}

.btn-login{background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;font-size:.92rem;font-weight:700;padding:16px 32px;border-radius:14px;border:none;cursor:pointer;transition:all .3s;box-shadow:0 8px 24px rgba(34,197,94,.4);width:100%;font-family:'Kanit',sans-serif;position:relative;overflow:hidden;}
.btn-login::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,transparent,rgba(255,255,255,.25),transparent);transform:translateX(-100%);transition:transform .7s;}
.btn-login::after{content:'';position:absolute;inset:0;background:radial-gradient(circle,rgba(255,255,255,.3) 0%,transparent 70%);opacity:0;transition:opacity .3s;}
.btn-login:hover::before{transform:translateX(100%);}
.btn-login:hover::after{opacity:1;}
.btn-login:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(34,197,94,.5);}
.btn-login:active{transform:translateY(-1px);}

.alert{padding:16px 20px;border-radius:14px;font-size:.85rem;margin-bottom:1.8rem;display:flex;align-items:center;gap:12px;animation:slideDown .5s ease;border:2px solid;}
@keyframes slideDown{from{opacity:0;transform:translateY(-15px);}to{opacity:1;transform:translateY(0);}}
.alert.error{background:#fee2e2;border-color:#fecaca;color:#dc2626;}
.alert i{font-size:1.2rem;flex-shrink:0;}

.divider{display:flex;align-items:center;margin:2rem 0;color:var(--muted);font-size:.78rem;font-weight:500;}
.divider::before,.divider::after{content:'';flex:1;height:2px;background:linear-gradient(90deg,transparent,#e8f0e9,transparent);}
.divider::before{margin-right:1.2rem;}
.divider::after{margin-left:1.2rem;}

.register-link{text-align:center;padding:18px;background:var(--g50);border-radius:14px;border:2px dashed var(--g200);}
.register-link p{font-size:.88rem;color:var(--sub);}
.register-link a{color:var(--g600);font-weight:700;text-decoration:none;transition:all .2s;}
.register-link a:hover{color:var(--g700);text-decoration:underline;}

.footer{text-align:center;margin-top:2rem;padding:1rem;}
.footer p{font-size:.75rem;color:var(--muted);display:flex;align-items:center;justify-content:center;gap:6px;}
.footer i{color:var(--g500);}

/* SCROLLBAR */
::-webkit-scrollbar {
    width: 6px; /* ความกว้างของ scrollbar แนวตั้ง */
    height: 6px; /* ความสูงของ scrollbar แนวนอน (ถ้ามี) */
}
::-webkit-scrollbar-track {
    background: transparent; /* สีพื้นหลังแทร็ก */
}
::-webkit-scrollbar-thumb {
    background: #cbd5e1; /* สีของแถบเลื่อนปกติ */
    border-radius: 10px; /* ความโค้งมนของแถบเลื่อน */
}
::-webkit-scrollbar-thumb:hover {
    background: var(--g500, #22c55e); /* เปลี่ยนเป็นสีเขียวของเว็บเวลาเอาเมาส์ชี้ */
}
/* รองรับเว็บบราวเซอร์ Firefox */
html {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}

@media (max-width:520px){
  .card{padding:2.5rem 2rem;}
  .title{font-size:1.8rem;}
  .logo{width:75px;height:75px;font-size:2rem;}
  body{padding:1rem;}
}
</style>
</head>
<body>

<div class="float-shape"></div>
<div class="float-shape"></div>
<div class="float-shape"></div>

<div class="container">
  <div class="card">
    <div class="logo-wrapper">
      <div class="logo"><i class="fas fa-utensils" style="color: #ffffff;"></i></div>
    </div>
    
    <h1 class="title">ยินดีต้อนรับกลับ!</h1>
    <p class="subtitle">เข้าสู่ระบบ FoodAI เพื่อเริ่มต้นการวางแผนอาหารที่ดีต่อสุขภาพ</p>

    <?php if(!empty($error)): ?>
    <div class="alert error">
      <i class="fas fa-exclamation-circle"></i>
      <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <form action="" method="POST">
      <div class="form-group">
        <label class="form-label">
          <i class="fas fa-envelope"></i> อีเมล
        </label>
        <div class="input-wrapper">
          <input type="email" name="email" class="form-input" placeholder="your@email.com" required autofocus>
          <i class="fas fa-at input-icon"></i>
        </div>
      </div>
      
      <div class="form-group">
        <label class="form-label">
          <i class="fas fa-lock"></i> รหัสผ่าน
        </label>
        <div class="input-wrapper">
          <input type="password" name="password" class="form-input" placeholder="••••••••" required>
          <i class="fas fa-key input-icon"></i>
        </div>
      </div>
      
      <div class="forgot-link">
        <a href="forgot_password.php">
          <i class="fas fa-question-circle"></i> ลืมรหัสผ่าน?
        </a>
      </div>
      
      <button type="submit" class="btn-login">
        <i class="fas fa-sign-in-alt" style="margin-right:8px;"></i> เข้าสู่ระบบ
      </button>
    </form>
    
    <div class="divider">หรือ</div>
    
    <div class="register-link">
      <p>
        ยังไม่มีบัญชี? <a href="register.php">
          <i class="fas fa-user-plus"></i> สมัครสมาชิก
        </a>
      </p>
    </div>
  </div>
  
  <div class="footer">
    <p>
      <i class="fas fa-sparkles"></i>
      © 2026 FoodAI • Powered by AI Technology
    </p>
  </div>
</div>

</body>
</html>