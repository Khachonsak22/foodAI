<?php
session_start();
include '../config/connect.php';
date_default_timezone_set('Asia/Bangkok');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* ── User info ── */
$u_stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$u_data    = $u_stmt->get_result()->fetch_assoc();
$firstName = $u_data['first_name'] ?? 'User';
$lastName  = $u_data['last_name']  ?? '';
$initials  = mb_strtoupper(mb_substr($firstName,0,1)).mb_strtoupper(mb_substr($lastName,0,1));

/* ระบบคัดกรองข่าว (ดึงข่าวล่าสุด 1 ข่าวแยกออกมา)*/
$feat_sql = "SELECT * FROM news ORDER BY created_at DESC LIMIT 1";
$feat_res = $conn->query($feat_sql);
$featured = $feat_res->fetch_assoc();
$featured_id = $featured ? $featured['id'] : 0;

// เช็กว่าข่าวล่าสุดนี้ โพสต์หลังจาก 07:00 น. ของวันนี้หรือไม่
$today_7am = date('Y-m-d 07:00:00');
$is_new_today = ($featured && $featured['created_at'] >= $today_7am);

/* ── Fetch News List (ไม่รวมข่าว Featured ป้องกันการซ้ำ) ── */
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// นับจำนวนข่าวทั้งหมด (หักข่าว Featured ออก 1 ข่าว)
$count_sql = "SELECT COUNT(*) as total FROM news WHERE id != $featured_id";
$count_res = $conn->query($count_sql);
$total_news = $count_res->fetch_assoc()['total'];
$total_pages = ceil($total_news / $per_page);

// ดึงข่าวตามหน้า Page ปัจจุบัน
$news_sql = "SELECT * FROM news WHERE id != $featured_id ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
$news_list = $conn->query($news_sql)->fetch_all(MYSQLI_ASSOC);

// กำหนดหน้าเมนูที่กำลังใช้งาน
$current_page = 'news.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ข่าวสารสุขภาพ — FoodAI</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
  --g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;
  --g400:#4ade80;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;
  --t400:#2dd4bf;--t500:#14b8a6;--t600:#0d9488;
  --bg:#f8faf9;--card:#fff;--bdr:#e8f0e9;
  --txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;
  --sb-w:248px;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,#c8e6c9 1px,transparent 1px);background-size:28px 28px;opacity:.35;}

/* Sidebar Styles (โครงสร้างเดิม) */
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

/* Page & Layout */
.page-wrap{margin-left:var(--sb-w);flex:1;position:relative;z-index:1;min-width:0;}
.topbar{height:66px;background:rgba(255,255,255,.9);backdrop-filter:blur(12px);border-bottom:1px solid var(--bdr);display:flex;align-items:center;padding:0 2.5rem;gap:14px;position:sticky;top:0;z-index:50;}
.tb-back{width:38px;height:38px;border-radius:11px;background:#fff;border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;color:var(--sub);text-decoration:none;font-size:.8rem;transition:all .18s;}
.tb-back:hover{background:var(--g50);border-color:var(--g200);color:var(--g600);}

@keyframes slideUp{from{opacity:0;transform:translateY(18px);}to{opacity:1;transform:translateY(0);}}
.rv{opacity:0;animation:slideUp .6s cubic-bezier(.22,1,.36,1) forwards;}
.rv1{animation-delay:.05s;}.rv2{animation-delay:.15s;}.rv3{animation-delay:.25s;}

/* ── NEW DESIGN: Featured News (Editorial Style) ── */
.featured-card {
  background: #fff;
  border-radius: 24px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 4px 24px rgba(0,0,0,.03);
  border: 1px solid var(--bdr);
  transition: all .3s ease;
  cursor: pointer;
}
.featured-card:hover {
  box-shadow: 0 16px 40px rgba(34,197,94,.12);
  transform: translateY(-4px);
  border-color: var(--g300);
}
@media(min-width: 1024px) {
  .featured-card { flex-direction: row; height: 380px; }
}
.featured-img {
  width: 100%; height: 260px;
  background: var(--g50); position: relative; overflow: hidden;
}
@media(min-width: 1024px) {
  .featured-img { width: 55%; height: 100%; }
}
.featured-img img {
  width: 100%; height: 100%; object-fit: cover;
  transition: transform .6s ease;
}
.featured-card:hover .featured-img img {
  transform: scale(1.04);
}
.featured-body {
  width: 100%; padding: 32px;
  display: flex; flex-direction: column; justify-content: center;
}
@media(min-width: 1024px) {
  .featured-body { width: 45%; padding: 40px; }
}
.feat-badge {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 6px 12px; border-radius: 99px;
  font-size: .68rem; font-weight: 700; letter-spacing: .02em;
  margin-bottom: 16px; width: fit-content;
}
.feat-badge.new { background: linear-gradient(135deg,var(--g500),var(--t500)); color: #fff; box-shadow: 0 4px 12px rgba(34,197,94,.3); }
.feat-badge.fallback { background: var(--g50); border: 1px solid var(--g300); color: var(--g700); }

/* ── NEW DESIGN: News Grid ── */
.news-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 24px;
}
.news-card {
  background: #fff; border: 1px solid var(--bdr); border-radius: 20px;
  overflow: hidden; display: flex; flex-direction: column;
  transition: all .3s ease; cursor: pointer; height: 100%;
}
.news-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 32px rgba(34,197,94,.08);
  border-color: var(--g200);
}
.n-img {
  height: 180px; width: 100%; background: var(--g50); overflow: hidden; position: relative;
}
.n-img img {
  width: 100%; height: 100%; object-fit: cover; transition: transform .5s ease;
}
.news-card:hover .n-img img { transform: scale(1.05); }
.n-body {
  padding: 20px 24px; display: flex; flex-direction: column; flex: 1;
}

/* Pagination & Utilities */
.stitle{font-family:'Nunito',sans-serif;font-size:1.15rem;font-weight:800;color:var(--txt);display:flex;align-items:center;gap:8px;}
.gline{height:4px;background:linear-gradient(90deg,var(--g500),transparent);border-radius:99px;}

.pagination{display:flex;gap:6px;justify-content:center;align-items:center;}
.page-btn{width:38px;height:38px;border-radius:12px;border:1px solid var(--bdr);background:#fff;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:600;color:var(--sub);cursor:pointer;transition:all .2s;text-decoration:none;}
.page-btn:hover{background:var(--g50);border-color:var(--g300);color:var(--g700);transform:translateY(-2px);}
.page-btn.active{background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;border-color:transparent;box-shadow:0 4px 12px rgba(34,197,94,.3);}
.page-btn:disabled{opacity:.4;cursor:not-allowed;pointer-events:none;}

.empty{border:2px dashed var(--g200);border-radius:24px;text-align:center;padding:4rem 2rem;color:var(--muted);background:#fff;}

::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-thumb{background:var(--g200);border-radius:99px;}
main { padding: 2rem 2.5rem 4rem; width: 100%; max-width: 1200px; margin: 0 auto; }

/* Responsive Sidebar */
.menu-toggle { display: none; width: 38px; height: 38px; border-radius: 11px; background: white; border: 1px solid var(--bdr); align-items: center; justify-content: center; color: var(--sub); font-size: 0.9rem; cursor: pointer; }
@media (max-width: 1024px) {
  .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
  .sidebar.show { transform: translateX(0); }
  .page-wrap { margin-left: 0 !important; }
  .menu-toggle { display: flex; }
  main { padding: 1.5rem 1rem; }
}
</style>
</head>
<body>

<?php include '../includes/sidebar.php' ?>

<div class="page-wrap">

  <header class="topbar">
    <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('show')">
      <i class="fas fa-bars"></i>
    </button>
    <a href="dashboard.php" class="tb-back"><i class="fas fa-arrow-left"></i></a>
    <div>
      <div style="font-family:'Nunito',sans-serif;font-size:.95rem;font-weight:800;color:var(--txt);">ข่าวสารสุขภาพ</div>
      <div style="font-size:.7rem;color:var(--muted);">อัปเดตล่าสุดโดยระบบอัตโนมัติ</div>
    </div>
    <div style="margin-left:auto;">
      <div style="background:var(--g50);border:1px solid var(--g300);color:var(--g700);font-size:.62rem;font-weight:700;padding:5px 12px;border-radius:99px;display:inline-flex;align-items:center;gap:6px;">
        <i class="fas fa-sync-alt" style="font-size:.55rem;color:var(--g500);"></i> รีเซ็ตทุก 07:00 น.
      </div>
    </div>
  </header>

  <main>

    <div class="rv rv1" style="margin-bottom:2rem;">
      <h1 style="font-family:'Nunito',sans-serif;font-size:1.8rem;font-weight:800;color:var(--txt);line-height:1.2;">
        บทความและข่าวสารสุขภาพ
      </h1>
      <p style="font-size:.85rem;color:var(--muted);margin-top:4px;">แหล่งรวมสาระน่ารู้ด้านโภชนาการและการดูแลตัวเอง (ทั้งหมด <?= $total_news + ($featured ? 1 : 0) ?> บทความ)</p>
      <div class="gline" style="width:60px;margin-top:12px;"></div>
    </div>

    <?php if ($featured && $page === 1): ?>
    <div class="rv rv2" style="margin-bottom:3rem;">
      <div class="featured-card" onclick="location.href='news_detail.php?id=<?= $featured['id'] ?>'">
        
        <div class="featured-img">
          <?php if (!empty($featured['image_url'])): ?>
            <img src="<?= htmlspecialchars($featured['image_url']) ?>" alt="Cover">
          <?php else: ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:4rem;opacity:.2;">📰</div>
          <?php endif; ?>
        </div>
        
        <div class="featured-body">
          <?php if($is_new_today): ?>
            <div class="feat-badge new">
              <i class="fas fa-sparkles"></i> ข่าวใหม่ประจำวัน
            </div>
          <?php else: ?>
            <div class="feat-badge fallback">
              <i class="fas fa-clock"></i> ข่าวล่าสุด
            </div>
          <?php endif; ?>

          <h2 style="font-family:'Nunito',sans-serif;font-size:1.4rem;font-weight:800;color:var(--txt);line-height:1.4;margin-bottom:12px;">
            <?= htmlspecialchars($featured['title']) ?>
          </h2>
          
          <p style="font-size:.85rem;color:var(--sub);line-height:1.7;margin-bottom:20px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">
            <?= strip_tags($featured['content']) ?>
          </p>
          
          <div style="margin-top:auto;display:flex;align-items:center;justify-content:space-between;">
            <div style="font-size:.7rem;color:var(--muted);display:flex;align-items:center;gap:6px;">
              <i class="fas fa-calendar-days"></i>
              <?= date('j M Y, H:i', strtotime($featured['created_at'])) ?> น.
            </div>
            <span style="font-size:.75rem;font-weight:700;color:var(--g600);">
              อ่านต่อ <i class="fas fa-arrow-right" style="font-size:.65rem;margin-left:2px;"></i>
            </span>
          </div>
        </div>

      </div>
    </div>
    <?php endif; ?>

    <?php if (count($news_list) > 0): ?>
    <div class="rv rv3">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
        <h2 class="stitle">บทความอื่นๆ</h2>
        <span style="font-size:.7rem;color:var(--muted);background:var(--bdr);padding:3px 8px;border-radius:6px;"><?= $total_news ?> รายการ</span>
      </div>
      
      <div class="news-grid">
        <?php foreach ($news_list as $news): ?>
        <div class="news-card" onclick="location.href='news_detail.php?id=<?= $news['id'] ?>'">
          
          <div class="n-img">
            <?php if (!empty($news['image_url'])): ?>
              <img src="<?= htmlspecialchars($news['image_url']) ?>" alt="Img">
            <?php else: ?>
              <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:3rem;opacity:.2;">📰</div>
            <?php endif; ?>
          </div>
          
          <div class="n-body">
            <div style="font-size:.68rem;color:var(--g600);font-weight:600;margin-bottom:8px;display:flex;align-items:center;gap:5px;">
              <i class="far fa-clock"></i>
              <?= date('j M Y', strtotime($news['created_at'])) ?>
            </div>
            
            <h3 style="font-family:'Nunito',sans-serif;font-size:.95rem;font-weight:800;color:var(--txt);line-height:1.45;margin-bottom:10px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
              <?= htmlspecialchars($news['title']) ?>
            </h3>
            
            <p style="font-size:.78rem;color:var(--muted);line-height:1.6;margin-bottom:16px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
              <?= strip_tags($news['content']) ?>
            </p>
            
            <div style="margin-top:auto;border-top:1px solid var(--bdr);padding-top:14px;">
              <span style="font-size:.72rem;font-weight:700;color:var(--sub);transition:color .2s;">
                อ่านบทความนี้ <i class="fas fa-chevron-right" style="font-size:.6rem;margin-left:4px;"></i>
              </span>
            </div>
          </div>
          
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ($total_pages > 1): ?>
      <div style="margin-top:3.5rem;">
        <div class="pagination">
          <?php if ($page > 1): ?>
          <a href="?page=<?= $page - 1 ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
          <?php else: ?>
          <button class="page-btn" disabled><i class="fas fa-chevron-left"></i></button>
          <?php endif; ?>

          <?php
          $start = max(1, $page - 2);
          $end   = min($total_pages, $page + 2);
          if ($start > 1): ?>
            <a href="?page=1" class="page-btn">1</a>
            <?php if ($start > 2): ?><span style="color:var(--muted);font-size:.8rem;">...</span><?php endif; ?>
          <?php endif; ?>

          <?php for ($i = $start; $i <= $end; $i++): ?>
          <a href="?page=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>

          <?php if ($end < $total_pages): ?>
            <?php if ($end < $total_pages - 1): ?><span style="color:var(--muted);font-size:.8rem;">...</span><?php endif; ?>
            <a href="?page=<?= $total_pages ?>" class="page-btn"><?= $total_pages ?></a>
          <?php endif; ?>

          <?php if ($page < $total_pages): ?>
          <a href="?page=<?= $page + 1 ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
          <?php else: ?>
          <button class="page-btn" disabled><i class="fas fa-chevron-right"></i></button>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
      
    </div>

    <?php elseif (!$featured): ?>
    <div class="rv rv3 empty">
      <div style="font-size:3rem;opacity:.2;margin-bottom:12px;">📭</div>
      <p style="font-size:1.1rem;font-weight:700;color:var(--txt);margin-bottom:6px;">ยังไม่มีข่าวสารในระบบ</p>
      <p style="font-size:.85rem;color:var(--muted);margin-bottom:20px;">ระบบอัตโนมัติจะดึงข่าวสารใหม่ทุกวันเวลา 07:00 น.</p>
    </div>
    <?php endif; ?>

  </main>
</div>

</body>
</html>