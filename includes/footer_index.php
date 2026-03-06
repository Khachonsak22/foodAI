<!-- Footer -->
<footer class="footer">
  <div class="footer-wave">
    <svg viewBox="0 0 1200 100" preserveAspectRatio="none">
      <path d="M0,50 Q300,0 600,50 T1200,50 L1200,100 L0,100 Z" fill="url(#footerGradient)"></path>
      <defs>
        <linearGradient id="footerGradient" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" style="stop-color:#22c55e;stop-opacity:1" />
          <stop offset="100%" style="stop-color:#14b8a6;stop-opacity:1" />
        </linearGradient>
      </defs>
    </svg>
  </div>

  <div class="footer-main">
    <div class="footer-container">
      
      <!-- Column 1: About -->
      <div class="footer-col">
        <div class="footer-logo">
          <div class="footer-logo-icon"><i class="fas fa-utensils" style="color: #ffffff;"></i></div>
          <div>
            <div class="footer-brand">FoodAI</div>
            <div class="footer-tagline">Smart Food Assistant</div>
          </div>
        </div>
        <p class="footer-desc">
          ระบบผู้ช่วยวางแผนอาหารอัจฉริยะที่ช่วยดูแลสุขภาพของคุณด้วย AI 
          พร้อมคำแนะนำที่ตรงกับความต้องการส่วนบุคคล
        </p>
        <div class="footer-social">
          <a href="#" class="social-btn"><i class="fab fa-facebook-f"></i></a>
          <a href="#" class="social-btn"><i class="fab fa-twitter"></i></a>
          <a href="#" class="social-btn"><i class="fab fa-instagram"></i></a>
          <a href="#" class="social-btn"><i class="fab fa-line"></i></a>
        </div>
      </div>

      <!-- Column 2: Quick Links -->
      <div class="footer-col">
        <h3 class="footer-title">เมนูหลัก</h3>
        <ul class="footer-links">
          <li><a href="pages/login.php"><i class="fas fa-home"></i> หน้าแรก</a></li>
          <li><a href="pages/login.php"><i class="fas fa-utensils"></i> สูตรอาหาร</a></li>
          <li><a href="pages/login.php"><i class="fas fa-book"></i> บันทึกมื้ออาหาร</a></li>
          <li><a href="pages/login.php"><i class="fas fa-fire"></i> เมนูยอดนิยม</a></li>
          <li><a href="pages/login.php"><i class="fas fa-robot"></i> AI แนะนำเมนู</a></li>
        </ul>
      </div>

      <!-- Column 3: Features -->
      <div class="footer-col">
        <h3 class="footer-title">ฟีเจอร์</h3>
        <ul class="footer-links">
          <li><a href="pages/login.php"><i class="fas fa-user-cog"></i> ตั้งค่าโปรไฟล์</a></li>
          <li><a href="#"><i class="fas fa-chart-line"></i> ติดตามแคลอรี่</a></li>
          <li><a href="#"><i class="fas fa-heart"></i> เมนูโปรด</a></li>
          <li><a href="#"><i class="fas fa-filter"></i> กรองตามโรค</a></li>
          <li><a href="#"><i class="fas fa-bell"></i> การแจ้งเตือน</a></li>
        </ul>
      </div>

      <!-- Column 4: Contact & Support -->
      <div class="footer-col">
        <h3 class="footer-title">ติดต่อเรา</h3>
        <ul class="footer-contact">
          <li>
            <i class="fas fa-envelope"></i>
            <div>
              <strong>Email</strong>
              <a href="mailto:support@foodai.com">support@foodai.com</a>
            </div>
          </li>
          <li>
            <i class="fas fa-phone"></i>
            <div>
              <strong>โทรศัพท์</strong>
              <span>02-XXX-XXXX</span>
            </div>
          </li>
          <li>
            <i class="fas fa-map-marker-alt"></i>
            <div>
              <strong>ที่อยู่</strong>
              <span>Mahasarakham, Thailand</span>
            </div>
          </li>
        </ul>
      </div>

    </div>
  </div>

  <!-- Footer Bottom -->
  <div class="footer-bottom">
    <div class="footer-container">
      <div class="footer-bottom-content">
        <p class="copyright">
          © <?= date('Y') ?> <strong>FoodAI</strong>. All rights reserved. 
          <span class="divider">|</span> 
          Powered by AI Technology
        </p>
        <div class="footer-bottom-links">
          <a href="#" class="footer-link">นโยบายความเป็นส่วนตัว</a>
          <span class="divider">|</span>
          <a href="#" class="footer-link">เงื่อนไขการใช้งาน</a>
          <span class="divider">|</span>
          <a href="#" class="footer-link">FAQ</a>
        </div>
      </div>
    </div>
  </div>
</footer>

<style>
/* ===================================
   FOOTER STYLES
=================================== */
.footer{
  background:linear-gradient(180deg,#f5f8f5 0%,#e8f0e9 100%);
  margin-top:auto;
  position:relative;
  z-index:10;
}

.footer-wave{
  position:relative;
  width:100%;
  height:80px;
  margin-bottom:-1px;
}

.footer-wave svg{
  width:100%;
  height:100%;
  display:block;
}

.footer-main{
  background:#fff;
  padding:60px 0 40px;
  border-top:1px solid #e8f0e9;
}

.footer-container{
  max-width:1400px;
  margin:0 auto;
  padding:0 2rem;
  display:grid;
  grid-template-columns:2fr 1fr 1fr 1.5fr;
  gap:40px;
}

/* Logo & Brand */
.footer-logo{
  display:flex;
  align-items:center;
  gap:12px;
  margin-bottom:16px;
}

.footer-logo-icon{
  width:50px;
  height:50px;
  border-radius:14px;
  background:linear-gradient(135deg,#22c55e,#14b8a6);
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:1.6rem;
  box-shadow:0 4px 16px rgba(34,197,94,.3);
}

.footer-brand{
  font-family:'Nunito',sans-serif;
  font-size:1.4rem;
  font-weight:800;
  color:#15803d;
  line-height:1;
}

.footer-tagline{
  font-size:.7rem;
  color:#8da98f;
  font-weight:600;
  letter-spacing:.05em;
  text-transform:uppercase;
  margin-top:2px;
}

.footer-desc{
  color:#4b6b4e;
  font-size:.85rem;
  line-height:1.7;
  margin-bottom:20px;
  max-width:350px;
}

/* Social Buttons */
.footer-social{
  display:flex;
  gap:10px;
}

.social-btn{
  width:40px;
  height:40px;
  border-radius:10px;
  background:#f0fdf4;
  border:2px solid #dcfce7;
  display:flex;
  align-items:center;
  justify-content:center;
  color:#16a34a;
  font-size:.9rem;
  text-decoration:none;
  transition:all .3s;
}

.social-btn:hover{
  background:linear-gradient(135deg,#22c55e,#14b8a6);
  border-color:#22c55e;
  color:#fff;
  transform:translateY(-3px);
  box-shadow:0 6px 16px rgba(34,197,94,.3);
}

/* Footer Columns */
.footer-col{
  display:flex;
  flex-direction:column;
}

.footer-title{
  font-family:'Nunito',sans-serif;
  font-size:1rem;
  font-weight:800;
  color:#15803d;
  margin-bottom:16px;
  display:flex;
  align-items:center;
  gap:8px;
}

.footer-title::before{
  content:'';
  width:4px;
  height:20px;
  background:linear-gradient(180deg,#22c55e,#14b8a6);
  border-radius:99px;
}

/* Links */
.footer-links{
  list-style:none;
  padding:0;
  margin:0;
}

.footer-links li{
  margin-bottom:10px;
}

.footer-links a{
  color:#4b6b4e;
  text-decoration:none;
  font-size:.85rem;
  display:flex;
  align-items:center;
  gap:10px;
  transition:all .2s;
  padding:6px 0;
}

.footer-links a i{
  color:#22c55e;
  font-size:.8rem;
  width:18px;
}

.footer-links a:hover{
  color:#22c55e;
  padding-left:8px;
}

/* Contact */
.footer-contact{
  list-style:none;
  padding:0;
  margin:0;
}

.footer-contact li{
  display:flex;
  gap:12px;
  margin-bottom:16px;
  align-items:flex-start;
}

.footer-contact i{
  width:36px;
  height:36px;
  border-radius:10px;
  background:#f0fdf4;
  border:2px solid #dcfce7;
  display:flex;
  align-items:center;
  justify-content:center;
  color:#22c55e;
  font-size:.85rem;
  flex-shrink:0;
}

.footer-contact strong{
  font-size:.75rem;
  color:#8da98f;
  font-weight:600;
  display:block;
  margin-bottom:2px;
  text-transform:uppercase;
  letter-spacing:.05em;
}

.footer-contact span,
.footer-contact a{
  font-size:.85rem;
  color:#4b6b4e;
  text-decoration:none;
  display:block;
}

.footer-contact a:hover{
  color:#22c55e;
}

/* Footer Bottom */
.footer-bottom{
  background:#fff;
  border-top:2px solid #e8f0e9;
  padding:24px 0;
}

.footer-bottom-content{
  display:flex;
  justify-content:space-between;
  align-items:center;
  flex-wrap:nowrap;
  gap:16px;
}

.copyright{
  color:#8da98f;
  font-size:.8rem;
  margin:0;
  white-space: nowrap;
}

.copyright strong{
  color:#22c55e;
  font-weight:700;
}

.divider{
  color:#dcfce7;
  margin:0 8px;
}

.footer-bottom-links{
  display:flex;
  align-items:center;
  gap:8px;
  white-space: nowrap;
}

.footer-link{
  color:#4b6b4e;
  text-decoration:none;
  font-size:.8rem;
  font-weight:500;
  transition:color .2s;
}

.footer-link:hover{
  color:#22c55e;
}

/* Responsive */
@media (max-width:1024px){
  .footer-container{
    grid-template-columns:1fr 1fr;
    gap:32px;
  }
}

@media (max-width:640px){
  .footer-container{
    grid-template-columns:1fr;
    gap:32px;
  }
  
  .footer-main{
    padding:40px 0 30px;
  }
  
  .footer-bottom-content{
    flex-direction:column;
    text-align:center;
  }
  
  .footer-wave{
    height:50px;
  }
}
</style>