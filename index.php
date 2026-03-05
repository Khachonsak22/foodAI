<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FoodAI - ผู้ช่วยวางแผนอาหารอัจฉริยะ</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
  --g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;
  --g400:#4ade80;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;
  --t400:#2dd4bf;--t500:#14b8a6;--t600:#0d9488;
  --txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;overflow-x:hidden;background:#f5f8f5;}

/* Animated Background */
.bg-animated{
  position:fixed;
  inset:0;
  z-index:0;
  background:linear-gradient(135deg,#f0fdf4 0%,#dcfce7 50%,#e0f2fe 100%);
}
.bg-animated::before{
  content:'';
  position:absolute;
  inset:0;
  background-image:
    radial-gradient(circle at 20% 50%,rgba(74,222,128,.15) 0%,transparent 50%),
    radial-gradient(circle at 80% 80%,rgba(45,212,191,.12) 0%,transparent 50%),
    radial-gradient(circle at 50% 20%,rgba(34,197,94,.1) 0%,transparent 50%);
  animation:bgPulse 15s ease-in-out infinite;
}
@keyframes bgPulse{0%,100%{opacity:1;}50%{opacity:.6;}}

.float-shape{
  position:fixed;
  border-radius:50%;
  opacity:.06;
  pointer-events:none;
  z-index:0;
}
.shape1{width:500px;height:500px;background:linear-gradient(135deg,var(--g400),var(--t400));top:-200px;right:-200px;animation:float1 25s ease-in-out infinite;}
.shape2{width:400px;height:400px;background:linear-gradient(135deg,var(--t500),var(--g500));bottom:-150px;left:-150px;animation:float2 20s ease-in-out infinite;}
.shape3{width:300px;height:300px;background:linear-gradient(135deg,var(--g300),var(--g400));top:40%;right:15%;animation:float3 18s ease-in-out infinite;}
@keyframes float1{0%,100%{transform:translate(0,0) scale(1);}50%{transform:translate(-50px,50px) scale(1.1);}}
@keyframes float2{0%,100%{transform:translate(0,0) scale(1);}50%{transform:translate(50px,-50px) scale(1.15);}}
@keyframes float3{0%,100%{transform:translate(0,0) scale(1);}50%{transform:translate(-40px,-40px) scale(.9);}}

/* Container */
.container{max-width:1400px;margin:0 auto;padding:0 2rem;position:relative;z-index:1;}

/* Navbar */
.navbar{
  background:rgba(255,255,255,.95);
  backdrop-filter:blur(20px);
  border-bottom:1px solid rgba(232,240,233,.8);
  position:sticky;
  top:0;
  z-index:100;
  box-shadow:0 4px 20px rgba(0,0,0,.05);
}
.nav-container{
  max-width:1400px;
  margin:0 auto;
  padding:1rem 2rem;
  display:flex;
  justify-content:space-between;
  align-items:center;
}
.logo{
  display:flex;
  align-items:center;
  gap:12px;
  text-decoration:none;
}
.logo-icon{
  width:50px;
  height:50px;
  border-radius:14px;
  background:linear-gradient(135deg,var(--g500),var(--t500));
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:1.8rem;
  box-shadow:0 6px 20px rgba(34,197,94,.4);
  animation:logoPulse 3s ease-in-out infinite;
}
@keyframes logoPulse{0%,100%{transform:scale(1);}50%{transform:scale(1.05);}}
.logo-text{
  font-family:'Nunito',sans-serif;
  font-size:1.6rem;
  font-weight:900;
  color:var(--g700);
  letter-spacing:-.02em;
}
.nav-links{
  display:flex;
  gap:2rem;
  align-items:center;
}
.nav-link{
  color:var(--sub);
  text-decoration:none;
  font-weight:500;
  font-size:.95rem;
  transition:color .2s;
}
.nav-link:hover{color:var(--g600);}
.btn-nav{
  padding:10px 24px;
  background:linear-gradient(135deg,var(--g500),var(--t500));
  color:#fff;
  border-radius:12px;
  text-decoration:none;
  font-weight:700;
  font-size:.9rem;
  box-shadow:0 6px 20px rgba(34,197,94,.35);
  transition:all .3s;
}
.btn-nav:hover{
  transform:translateY(-3px);
  box-shadow:0 10px 30px rgba(34,197,94,.5);
}

/* Hero Section */
.hero{
  min-height:90vh;
  display:flex;
  align-items:center;
  padding:4rem 0;
}
.hero-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:4rem;
  align-items:center;
}
.hero-content{
  animation:slideInLeft 1s ease;
}
@keyframes slideInLeft{from{opacity:0;transform:translateX(-50px);}to{opacity:1;transform:translateX(0);}}
.hero-badge{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:8px 20px;
  background:rgba(34,197,94,.1);
  border:2px solid rgba(34,197,94,.2);
  border-radius:99px;
  margin-bottom:1.5rem;
  font-size:.85rem;
  font-weight:600;
  color:var(--g700);
}
.hero-badge i{
  color:var(--g500);
  font-size:1rem;
}
.hero-title{
  font-family:'Nunito',sans-serif;
  font-size:3.5rem;
  font-weight:900;
  color:var(--txt);
  line-height:1.1;
  margin-bottom:1.5rem;
}
.hero-title .highlight{
  background:linear-gradient(135deg,var(--g500),var(--t500));
  -webkit-background-clip:text;
  -webkit-text-fill-color:transparent;
  background-clip:text;
}
.hero-subtitle{
  font-size:1.2rem;
  color:var(--sub);
  line-height:1.8;
  margin-bottom:2.5rem;
}
.hero-buttons{
  display:flex;
  gap:1rem;
}
.btn-primary{
  padding:16px 36px;
  background:linear-gradient(135deg,var(--g500),var(--t500));
  color:#fff;
  border-radius:14px;
  text-decoration:none;
  font-weight:700;
  font-size:1rem;
  box-shadow:0 8px 24px rgba(34,197,94,.4);
  transition:all .3s;
  display:inline-flex;
  align-items:center;
  gap:10px;
}
.btn-primary:hover{
  transform:translateY(-4px);
  box-shadow:0 12px 36px rgba(34,197,94,.5);
}
.btn-secondary{
  padding:16px 36px;
  background:#fff;
  color:var(--g600);
  border-radius:14px;
  text-decoration:none;
  font-weight:700;
  font-size:1rem;
  border:2px solid var(--g200);
  transition:all .3s;
  display:inline-flex;
  align-items:center;
  gap:10px;
}
.btn-secondary:hover{
  background:var(--g50);
  border-color:var(--g400);
}

.hero-image{
  position:relative;
  animation:slideInRight 1s ease;
}
@keyframes slideInRight{from{opacity:0;transform:translateX(50px);}to{opacity:1;transform:translateX(0);}}
.hero-mockup{
  position:relative;
  background:#fff;
  border-radius:32px;
  padding:2rem;
  box-shadow:0 20px 60px rgba(0,0,0,.15);
  border:1px solid #e8f0e9;
}
.mockup-header{
  display:flex;
  gap:8px;
  margin-bottom:1.5rem;
}
.dot{width:12px;height:12px;border-radius:50%;}
.dot.red{background:#ef4444;}
.dot.yellow{background:#f59e0b;}
.dot.green{background:#22c55e;}
.mockup-content{
  background:linear-gradient(135deg,var(--g50),rgba(220,252,231,.5));
  border-radius:20px;
  padding:2rem;
  text-align:center;
}
.mockup-icon{
  font-size:5rem;
  margin-bottom:1rem;
}
.mockup-text{
  font-family:'Nunito',sans-serif;
  font-size:1.4rem;
  font-weight:700;
  color:var(--g700);
  margin-bottom:.5rem;
}
.mockup-subtext{
  color:var(--muted);
  font-size:.95rem;
}

/* Features */
.features{
  padding:6rem 0;
  background:#fff;
}
.section-header{
  text-align:center;
  margin-bottom:4rem;
}
.section-badge{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:8px 20px;
  background:var(--g50);
  border:2px solid var(--g200);
  border-radius:99px;
  margin-bottom:1rem;
  font-size:.85rem;
  font-weight:700;
  color:var(--g600);
}
.section-title{
  font-family:'Nunito',sans-serif;
  font-size:2.5rem;
  font-weight:900;
  color:var(--txt);
  margin-bottom:1rem;
}
.section-subtitle{
  font-size:1.1rem;
  color:var(--sub);
  max-width:600px;
  margin:0 auto;
}
.features-grid{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:2rem;
}
.feature-card{
  background:linear-gradient(135deg,#fff,var(--g50));
  border:2px solid var(--g100);
  border-radius:24px;
  padding:2.5rem;
  text-align:center;
  transition:all .4s;
  position:relative;
  overflow:hidden;
}
.feature-card::before{
  content:'';
  position:absolute;
  top:-50%;
  right:-50%;
  width:200%;
  height:200%;
  background:radial-gradient(circle,rgba(34,197,94,.1),transparent 70%);
  opacity:0;
  transition:opacity .4s;
}
.feature-card:hover::before{opacity:1;}
.feature-card:hover{
  transform:translateY(-8px);
  box-shadow:0 20px 40px rgba(34,197,94,.2);
  border-color:var(--g400);
}
.feature-icon{
  width:80px;
  height:80px;
  border-radius:20px;
  background:linear-gradient(135deg,var(--g500),var(--t500));
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:2.2rem;
  margin:0 auto 1.5rem;
  box-shadow:0 8px 24px rgba(34,197,94,.3);
  position:relative;
}
.feature-title{
  font-family:'Nunito',sans-serif;
  font-size:1.3rem;
  font-weight:800;
  color:var(--txt);
  margin-bottom:1rem;
}
.feature-desc{
  color:var(--sub);
  line-height:1.7;
  font-size:.95rem;
}

/* Stats */
.stats{
  background:linear-gradient(135deg,var(--g500),var(--t500));
  padding:4rem 0;
  color:#fff;
}
.stats-grid{
  display:grid;
  grid-template-columns:repeat(4,1fr);
  gap:3rem;
  text-align:center;
}
.stat-number{
  font-family:'Nunito',sans-serif;
  font-size:3rem;
  font-weight:900;
  margin-bottom:.5rem;
}
.stat-label{
  font-size:1.1rem;
  opacity:.9;
}

/* CTA */
.cta{
  padding:6rem 0;
  text-align:center;
}
.cta-content{
  max-width:700px;
  margin:0 auto;
}
.cta-title{
  font-family:'Nunito',sans-serif;
  font-size:2.8rem;
  font-weight:900;
  color:var(--txt);
  margin-bottom:1.5rem;
}
.cta-subtitle{
  font-size:1.2rem;
  color:var(--sub);
  margin-bottom:2.5rem;
}

/* Responsive */
@media (max-width:1024px){
  .hero-grid{grid-template-columns:1fr;gap:3rem;}
  .features-grid{grid-template-columns:repeat(2,1fr);}
  .stats-grid{grid-template-columns:repeat(2,1fr);}
}
@media (max-width:768px){
  .nav-links{display:none;}
  .hero-title{font-size:2.5rem;}
  .features-grid{grid-template-columns:1fr;}
  .stats-grid{grid-template-columns:1fr;}
}
</style>
</head>
<body>

<div class="bg-animated"></div>
<div class="float-shape shape1"></div>
<div class="float-shape shape2"></div>
<div class="float-shape shape3"></div>

<!-- Navbar -->
<nav class="navbar">
  <div class="nav-container">
    <a href="#" class="logo">
      <div class="logo-icon">🥗</div>
      <span class="logo-text">FoodAI</span>
    </a>
    <div class="nav-links">
      <a href="#features" class="nav-link">ฟีเจอร์</a>
      <a href="#about" class="nav-link">เกี่ยวกับเรา</a>
      <a href="#contact" class="nav-link">ติดต่อ</a>
      <a href="pages/login.php" class="btn-nav">
        <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
      </a>
    </div>
  </div>
</nav>

<!-- Hero Section -->
<section class="hero">
  <div class="container">
    <div class="hero-grid">
      <div class="hero-content">
        <div class="hero-badge">
          <i class="fas fa-sparkles"></i>
          <span>Powered by AI Technology</span>
        </div>
        <h1 class="hero-title">
          ผู้ช่วยวางแผน<br>
          <span class="highlight">อาหารอัจฉริยะ</span><br>
          เพื่อสุขภาพที่ดี
        </h1>
        <p class="hero-subtitle">
          วางแผนอาหารที่เหมาะกับคุณด้วย AI ติดตามแคลอรี่ รับคำแนะนำเมนู
          และดูแลสุขภาพแบบ Personalized ทุกมื้อ
        </p>
        <div class="hero-buttons">
          <a href="pages/register.php" class="btn-primary">
            <i class="fas fa-rocket"></i> เริ่มต้นใช้งาน
          </a>
          <a href="#features" class="btn-secondary">
            <i class="fas fa-play-circle"></i> เรียนรู้เพิ่มเติม
          </a>
        </div>
      </div>
      <div class="hero-image">
        <div class="hero-mockup">
          <div class="mockup-header">
            <div class="dot red"></div>
            <div class="dot yellow"></div>
            <div class="dot green"></div>
          </div>
          <div class="mockup-content">
            <div class="mockup-icon">🍽️</div>
            <div class="mockup-text">วางแผนอาหารอัจฉริยะ</div>
            <div class="mockup-subtext">ด้วยเทคโนโลยี AI ที่ดูแลสุขภาพคุณ</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Features -->
<section class="features" id="features">
  <div class="container">
    <div class="section-header">
      <div class="section-badge">
        <i class="fas fa-star"></i> ฟีเจอร์เด่น
      </div>
      <h2 class="section-title">ทำไมต้องเลือก FoodAI?</h2>
      <p class="section-subtitle">
        ระบบครบครันที่ช่วยดูแลสุขภาพของคุณอย่างมืออาชีพ
      </p>
    </div>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon">🤖</div>
        <h3 class="feature-title">AI แนะนำเมนู</h3>
        <p class="feature-desc">
          ปัญญาประดิษฐ์วิเคราะห์และแนะนำเมนูที่เหมาะกับโรคประจำตัว 
          อาหารที่แพ้ และเป้าหมายสุขภาพของคุณ
        </p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📊</div>
        <h3 class="feature-title">ติดตามแคลอรี่</h3>
        <p class="feature-desc">
          บันทึกและติดตามแคลอรี่ทุกมื้อ พร้อมกราฟและสถิติ
          ที่ช่วยให้คุณเห็นความก้าวหน้า
        </p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🏥</div>
        <h3 class="feature-title">กรองตามโรค</h3>
        <p class="feature-desc">
          ค้นหาเมนูที่ปลอดภัยสำหรับโรคประจำตัว 20+ โรค
          และอาหารที่แพ้ 20+ ชนิด
        </p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📖</div>
        <h3 class="feature-title">สูตรอาหาร 100+ เมนู</h3>
        <p class="feature-desc">
          คลังสูตรอาหารเพื่อสุขภาพพร้อมคำแนะนำโภชนาการ
          และส่วนผสมละเอียด
        </p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">❤️</div>
        <h3 class="feature-title">เมนูโปรด</h3>
        <p class="feature-desc">
          บันทึกเมนูที่ชอบไว้ดูง่าย เข้าถึงได้รวดเร็ว
          และวางแผนมื้ออาหารล่วงหน้า
        </p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📱</div>
        <h3 class="feature-title">ใช้งานง่าย</h3>
        <p class="feature-desc">
          Interface สวยงาม ใช้งานสะดวก รองรับทุกอุปกรณ์
          ทั้ง Desktop, Tablet และ Mobile
        </p>
      </div>
    </div>
  </div>
</section>

<!-- Stats -->
<section class="stats">
  <div class="container">
    <div class="stats-grid">
      <div>
        <div class="stat-number">100+</div>
        <div class="stat-label">สูตรอาหาร</div>
      </div>
      <div>
        <div class="stat-number">40+</div>
        <div class="stat-label">Tags (โรค+อาหารแพ้)</div>
      </div>
      <div>
        <div class="stat-number">1,000+</div>
        <div class="stat-label">ผู้ใช้งาน</div>
      </div>
      <div>
        <div class="stat-number">24/7</div>
        <div class="stat-label">AI แนะนำ</div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta">
  <div class="container">
    <div class="cta-content">
      <h2 class="cta-title">
        พร้อมเริ่มดูแลสุขภาพกับเราแล้วหรือยัง?
      </h2>
      <p class="cta-subtitle">
        สมัครสมาชิกวันนี้ ฟรี! และเริ่มต้นใช้งานทันที
      </p>
      <div class="hero-buttons" style="justify-content:center;">
        <a href="pages/register.php" class="btn-primary">
          <i class="fas fa-user-plus"></i> สมัครสมาชิก
        </a>
        <a href="pages/login.php" class="btn-secondary">
          <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
        </a>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>

</body>
</html>