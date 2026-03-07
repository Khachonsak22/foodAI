<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FoodAI - ผู้ช่วยวางแผนอาหารอัจฉริยะ</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
:root{
  --g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;
  --g400:#4ade80;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;
  --t400:#2dd4bf;--t500:#14b8a6;--txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;
}
*{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:'Kanit',sans-serif;overflow-x:hidden;background:#fff;}

/* Navbar */
.navbar{
  background:rgba(255,255,255,.98);
  backdrop-filter:blur(20px);
  border-bottom:1px solid rgba(232,240,233,.6);
  position:fixed;
  top:0;
  left:0;
  right:0;
  z-index:1000;
  box-shadow:0 2px 20px rgba(0,0,0,.05);
  transition:all .3s;
}
.navbar.scrolled{
  box-shadow:0 4px 30px rgba(0,0,0,.1);
}
.nav-container{
  max-width:1400px;
  margin:0 auto;
  padding:1.2rem 2rem;
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
  width:45px;
  height:45px;
  border-radius:12px;
  background:linear-gradient(135deg,var(--g500),var(--t500));
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:1.6rem;
  box-shadow:0 4px 16px rgba(34,197,94,.35);
}
.logo-text{
  font-family:'Nunito',sans-serif;
  font-size:1.5rem;
  font-weight:900;
  background:linear-gradient(135deg,var(--g600),var(--t500));
  -webkit-background-clip:text;
  -webkit-text-fill-color:transparent;
  background-clip:text;
}
.nav-links{
  display:flex;
  gap:2.5rem;
  align-items:center;
}
.nav-link{
  color:var(--sub);
  text-decoration:none;
  font-weight:500;
  font-size:.95rem;
  transition:color .2s;
  position:relative;
}
.nav-link::after{
  content:'';
  position:absolute;
  bottom:-5px;
  left:0;
  width:0;
  height:2px;
  background:var(--g500);
  transition:width .3s;
}
.nav-link:hover::after{width:100%;}
.nav-link:hover{color:var(--g600);}
.btn-nav{
  padding:12px 28px;
  background:linear-gradient(135deg,var(--g500),var(--t500));
  color:#fff;
  border-radius:12px;
  text-decoration:none;
  font-weight:700;
  font-size:.9rem;
  box-shadow:0 4px 16px rgba(34,197,94,.3);
  transition:all .3s;
}
.btn-nav:hover{
  transform:translateY(-2px);
  box-shadow:0 8px 24px rgba(34,197,94,.4);
}

/* Hero */
.hero{
  min-height:100vh;
  display:flex;
  align-items:center;
  padding:8rem 0 4rem;
  background:linear-gradient(135deg,#f0fdf4 0%,#ffffff 100%);
  position:relative;
  overflow:hidden;
}
.hero::before{
  content:'';
  position:absolute;
  top:-50%;
  right:-20%;
  width:800px;
  height:800px;
  background:radial-gradient(circle,rgba(74,222,128,.15),transparent 70%);
  border-radius:50%;
  animation:float 20s ease-in-out infinite;
}
@keyframes float{0%,100%{transform:translate(0,0);}50%{transform:translate(-50px,50px);}}

.container{max-width:1400px;margin:0 auto;padding:0 2rem;position:relative;z-index:1;}
.hero-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:4rem;
  align-items:center;
}
.hero-content{opacity:0;animation:fadeInUp 1s ease forwards;}
@keyframes fadeInUp{to{opacity:1;transform:translateY(0);}from{opacity:0;transform:translateY(30px);}}

.hero-badge{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:10px 24px;
  background:rgba(34,197,94,.1);
  border:2px solid rgba(34,197,94,.2);
  border-radius:50px;
  margin-bottom:2rem;
  font-size:.88rem;
  font-weight:600;
  color:var(--g700);
}
.hero-title{
  font-family:'Nunito',sans-serif;
  font-size:4rem;
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
  font-size:1.25rem;
  color:var(--sub);
  line-height:1.8;
  margin-bottom:3rem;
  max-width:500px;
}
.hero-buttons{display:flex;gap:1rem;}
.btn-primary{
  padding:18px 40px;
  background:linear-gradient(135deg,var(--g500),var(--t500));
  color:#fff;
  border-radius:14px;
  text-decoration:none;
  font-weight:700;
  font-size:1.05rem;
  box-shadow:0 8px 24px rgba(34,197,94,.35);
  transition:all .3s;
  display:inline-flex;
  align-items:center;
  gap:10px;
}
.btn-primary:hover{
  transform:translateY(-4px);
  box-shadow:0 12px 32px rgba(34,197,94,.45);
}
.btn-secondary{
  padding:18px 40px;
  background:#fff;
  color:var(--g600);
  border-radius:14px;
  text-decoration:none;
  font-weight:700;
  font-size:1.05rem;
  border:2px solid var(--g200);
  transition:all .3s;
  display:inline-flex;
  align-items:center;
  gap:10px;
}
.btn-secondary:hover{
  background:var(--g50);
  border-color:var(--g400);
  transform:translateY(-4px);
}

/* Hero Image Grid */
.hero-images{
  display:grid;
  grid-template-columns:repeat(2,1fr);
  gap:1.5rem;
  opacity:0;
  animation:fadeInUp 1s ease .3s forwards;
}
.food-card{
  border-radius:24px;
  overflow:hidden;
  box-shadow:0 10px 40px rgba(0,0,0,.1);
  transition:all .4s;
  position:relative;
}
.food-card:hover{
  transform:translateY(-8px);
  box-shadow:0 20px 50px rgba(0,0,0,.15);
}
.food-card img{
  width:100%;
  height:250px;
  object-fit:cover;
  display:block;
}
.food-card:nth-child(1){margin-top:2rem;}
.food-card:nth-child(2){margin-top:0;}
.food-card:nth-child(3){margin-top:0;}
.food-card:nth-child(4){margin-top:2rem;}

/* Scroll Reveal */
.reveal{
  opacity:0;
  transform:translateY(50px);
  transition:all .8s ease;
}
.reveal.active{
  opacity:1;
  transform:translateY(0);
}

/* Features */
.features{
  padding:8rem 0;
  background:#fff;
}
.section-header{
  text-align:center;
  margin-bottom:5rem;
}
.section-badge{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:10px 24px;
  background:var(--g50);
  border:2px solid var(--g200);
  border-radius:50px;
  margin-bottom:1.5rem;
  font-size:.9rem;
  font-weight:700;
  color:var(--g600);
}
.section-title{
  font-family:'Nunito',sans-serif;
  font-size:3rem;
  font-weight:900;
  color:var(--txt);
  margin-bottom:1rem;
}
.section-subtitle{
  font-size:1.2rem;
  color:var(--sub);
  max-width:600px;
  margin:0 auto;
}
.features-grid{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:2.5rem;
}
.feature-card{
  background:#fff;
  border:2px solid var(--g100);
  border-radius:28px;
  padding:3rem 2.5rem;
  text-align:center;
  transition:all .4s;
  position:relative;
}
.feature-card::before{
  content:'';
  position:absolute;
  inset:0;
  border-radius:28px;
  background:linear-gradient(135deg,var(--g50),transparent);
  opacity:0;
  transition:opacity .4s;
}
.feature-card:hover::before{opacity:1;}
.feature-card:hover{
  transform:translateY(-10px);
  box-shadow:0 20px 50px rgba(34,197,94,.15);
  border-color:var(--g400);
}
.feature-icon{
  width:90px;
  height:90px;
  border-radius:24px;
  background:linear-gradient(135deg,var(--g500),var(--t500));
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:2.5rem;
  margin:0 auto 2rem;
  box-shadow:0 10px 30px rgba(34,197,94,.3);
  position:relative;
}
.feature-title{
  font-family:'Nunito',sans-serif;
  font-size:1.4rem;
  font-weight:800;
  color:var(--txt);
  margin-bottom:1rem;
}
.feature-desc{
  color:var(--sub);
  line-height:1.8;
  font-size:1rem;
}

/* Gallery */
.gallery{
  padding:8rem 0;
  background:var(--g50);
}
.gallery-grid{
  display:grid;
  grid-template-columns:repeat(4,1fr);
  gap:2rem;
  margin-top:4rem;
}
.gallery-item{
  border-radius:24px;
  overflow:hidden;
  box-shadow:0 10px 30px rgba(0,0,0,.1);
  transition:all .4s;
}
.gallery-item:hover{
  transform:scale(1.05);
  box-shadow:0 20px 40px rgba(0,0,0,.15);
}
.gallery-item img{
  width:100%;
  height:300px;
  object-fit:cover;
  display:block;
}

/* Stats */
.stats{
  background:linear-gradient(135deg,var(--g500),var(--t500));
  padding:5rem 0;
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
  font-size:3.5rem;
  font-weight:900;
  margin-bottom:.8rem;
}
.stat-label{
  font-size:1.2rem;
  opacity:.95;
}

/* CTA */
.cta{
  padding:8rem 0;
  text-align:center;
  background:#fff;
}
.cta-content{
  max-width:800px;
  margin:0 auto;
}
.cta-title{
  font-family:'Nunito',sans-serif;
  font-size:3.2rem;
  font-weight:900;
  color:var(--txt);
  margin-bottom:1.5rem;
}
.cta-subtitle{
  font-size:1.3rem;
  color:var(--sub);
  margin-bottom:3rem;
}

/* Responsive */
@media (max-width:1024px){
  .hero-grid{grid-template-columns:1fr;gap:3rem;}
  .hero-images{margin-top:3rem;}
  .features-grid{grid-template-columns:repeat(2,1fr);}
  .gallery-grid{grid-template-columns:repeat(2,1fr);}
  .stats-grid{grid-template-columns:repeat(2,1fr);}
}
@media (max-width:768px){
  .nav-links{display:none;}
  .hero-title{font-size:2.8rem;}
  .hero-images{grid-template-columns:1fr;}
  .features-grid{grid-template-columns:1fr;}
  .gallery-grid{grid-template-columns:1fr;}
  .stats-grid{grid-template-columns:1fr;gap:2rem;}
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar" id="navbar">
  <div class="nav-container">
    <a href="#" class="logo">
      <div class="logo-icon"><i class="fas fa-utensils" style="color: #ffffff;"></i></div>
      <span class="logo-text">FoodAI</span>
    </a>
    <div class="nav-links">
      <a href="#features" class="nav-link">ฟีเจอร์</a>
      <a href="#gallery" class="nav-link">แกลเลอรี่</a>
      <a href="#about" class="nav-link">เกี่ยวกับ</a>
      <a href="pages/login.php" class="btn-nav">
        <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
      </a>
    </div>
  </div>
</nav>

<!-- Hero -->
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
          และดูแลสุขภาพแบบ Personalized
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
      <div class="hero-images">
        <div class="food-card">
          <img src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=400&h=300&fit=crop" alt="Food 1">
        </div>
        <div class="food-card">
          <img src="https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=400&h=300&fit=crop" alt="Food 2">
        </div>
        <div class="food-card">
          <img src="https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?w=400&h=300&fit=crop" alt="Food 3">
        </div>
        <div class="food-card">
          <img src="https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=400&h=300&fit=crop" alt="Food 4">
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Features -->
<section class="features" id="features">
  <div class="container">
    <div class="section-header reveal">
      <div class="section-badge">
        <i class="fas fa-star"></i> ฟีเจอร์เด่น
      </div>
      <h2 class="section-title">ทำไมต้องเลือก FoodAI?</h2>
      <p class="section-subtitle">
        ระบบครบครันที่ช่วยดูแลสุขภาพของคุณอย่างมืออาชีพ
      </p>
    </div>
    <div class="features-grid">
      <div class="feature-card reveal">
        <div class="feature-icon"><i class="fas fa-robot" style="color: #62d4da;"></i></div>
        <h3 class="feature-title">AI แนะนำเมนู</h3>
        <p class="feature-desc">
          ปัญญาประดิษฐ์วิเคราะห์และแนะนำเมนูที่เหมาะกับโรคประจำตัว 
          อาหารที่แพ้ และเป้าหมายสุขภาพ
        </p>
      </div>
      <div class="feature-card reveal">
        <div class="feature-icon"><i class="bi bi-bar-chart-line-fill" style="color: #2d56f7;"></i></div>
        <h3 class="feature-title">ติดตามแคลอรี่</h3>
        <p class="feature-desc">
          บันทึกและติดตามแคลอรี่ทุกมื้อ พร้อมกราฟและสถิติ
          ที่ช่วยให้เห็นความก้าวหน้า
        </p>
      </div>
      <div class="feature-card reveal">
        <div class="feature-icon"><i class="fa-solid fa-hospital" style="color: #ffffff;"></i></div>
        <h3 class="feature-title">กรองตามโรค</h3>
        <p class="feature-desc">
          ค้นหาเมนูที่ปลอดภัยสำหรับโรคประจำตัว 20+ โรค
          และอาหารที่แพ้ 20+ ชนิด
        </p>
      </div>
      <div class="feature-card reveal">
        <div class="feature-icon"><i class="fas fa-book-open" style="color: #ffffff;"></i></div>
        <h3 class="feature-title">สูตรอาหาร 100+</h3>
        <p class="feature-desc">
          คลังสูตรอาหารเพื่อสุขภาพพร้อมคำแนะนำโภชนาการ
          และส่วนผสมละเอียด
        </p>
      </div>
      <div class="feature-card reveal">
        <div class="feature-icon"><i class="bi bi-suit-heart-fill" style="color: #dc2626;"></i></div>
        <h3 class="feature-title">เมนูโปรด</h3>
        <p class="feature-desc">
          บันทึกเมนูที่ชอบไว้ดูง่าย เข้าถึงได้รวดเร็ว
          และวางแผนมื้ออาหารล่วงหน้า
        </p>
      </div>
      <div class="feature-card reveal">
        <div class="feature-icon"><i class="bi bi-phone" style="color: #fff23b;"></i></div>
        <h3 class="feature-title">ใช้งานง่าย</h3>
        <p class="feature-desc">
          Interface สวยงาม ใช้งานสะดวก รองรับทุกอุปกรณ์
          ทั้ง Desktop Tablet และ Mobile
        </p>
      </div>
    </div>
  </div>
</section>

<!-- Gallery -->
<section class="gallery" id="gallery">
  <div class="container">
    <div class="section-header reveal">
      <div class="section-badge">
        <i class="fas fa-images"></i> แกลเลอรี่
      </div>
      <h2 class="section-title">เมนูอาหารเพื่อสุขภาพ</h2>
      <p class="section-subtitle">
        รวมเมนูอาหารคุณภาพจากนักโภชนาการมืออาชีพ
      </p>
    </div>
    <div class="gallery-grid">
      <div class="gallery-item reveal">
        <img src="https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=400&h=400&fit=crop" alt="Healthy Bowl">
      </div>
      <div class="gallery-item reveal">
        <img src="https://images.unsplash.com/photo-1490645935967-10de6ba17061?w=400&h=400&fit=crop" alt="Fresh Salad">
      </div>
      <div class="gallery-item reveal">
        <img src="https://images.unsplash.com/photo-1498837167922-ddd27525d352?w=400&h=400&fit=crop" alt="Smoothie">
      </div>
      <div class="gallery-item reveal">
        <img src="https://images.unsplash.com/photo-1547592180-85f173990554?w=400&h=400&fit=crop" alt="Breakfast">
      </div>
    </div>
  </div>
</section>

<!-- Stats -->
<section class="stats reveal">
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
<section class="cta reveal">
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

<?php include 'includes/footer_index.php'; ?>

<script>
// Navbar scroll effect
window.addEventListener('scroll', () => {
  const navbar = document.getElementById('navbar');
  if (window.scrollY > 50) {
    navbar.classList.add('scrolled');
  } else {
    navbar.classList.remove('scrolled');
  }
});

// Scroll reveal animation
const reveals = document.querySelectorAll('.reveal');
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry, index) => {
    if (entry.isIntersecting) {
      setTimeout(() => {
        entry.target.classList.add('active');
      }, index * 100);
    }
  });
}, {
  threshold: 0.1,
  rootMargin: '0px 0px -50px 0px'
});

reveals.forEach(reveal => {
  revealObserver.observe(reveal);
});
</script>

</body>
</html>