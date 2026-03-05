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

/* ── Get news ID ── */
$news_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if ($news_id <= 0) {
    header("Location: news.php");
    exit();
}

/* ── Fetch news article ── */
$stmt = $conn->prepare("SELECT * FROM news WHERE id = ?");
$stmt->bind_param("i", $news_id);
$stmt->execute();
$news = $stmt->get_result()->fetch_assoc();

if (!$news) {
    header("Location: news.php");
    exit();
}

/* ── Fetch related news (ไม่รวมข่าวที่กำลังอ่านอยู่) ── */
$related_stmt = $conn->prepare(
    "SELECT * FROM news 
     WHERE id != ? 
     ORDER BY ABS(DATEDIFF(created_at, ?)) ASC, created_at DESC 
     LIMIT 3"
);
$news_date = $news['created_at'];
$related_stmt->bind_param("is", $news_id, $news_date);
$related_stmt->execute();
$related_news = $related_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$current_page = 'news.php'; // เพื่อให้ Sidebar highlight ที่เมนูข่าวสาร
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($news['title']) ?> — FoodAI</title>
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

@keyframes slideUp{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}
.rv{opacity:0;animation:slideUp .6s cubic-bezier(.22,1,.36,1) forwards;}
.rv1{animation-delay:.05s;}.rv2{animation-delay:.15s;}.rv3{animation-delay:.25s;}

/* ── NEW DESIGN: Article Layout ── */
.article-container {
  max-width: 860px; /* ลดความกว้างลงให้อ่านหนังสือสบายตา */
  margin: 0 auto;
}

.article-header {
  text-align: center;
  margin-bottom: 2rem;
}

.article-meta {
  display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; align-items: center;
  margin-bottom: 18px; font-size: .75rem; color: var(--muted); font-weight: 500;
}

.article-title {
  font-family: 'Nunito', sans-serif;
  font-size: 2.2rem; font-weight: 800; color: var(--txt);
  line-height: 1.35; margin-bottom: 24px;
}

.article-hero-img {
  width: 100%; max-height: 480px;
  border-radius: 28px; overflow: hidden;
  box-shadow: 0 16px 40px rgba(0,0,0,.08);
  margin-bottom: 2.5rem; background: var(--g50);
}
.article-hero-img img {
  width: 100%; height: 100%; object-fit: cover;
}

.article-body {
  font-size: 1.05rem; /* ตัวหนังสือใหญ่ขึ้นอ่านง่าย */
  color: #334155;
  line-height: 1.9;
  letter-spacing: 0.01em;
  padding: 0 1rem;
}
.article-body p { margin-bottom: 1.5em; }
.article-body strong, .article-body b { color: var(--txt); font-weight: 700; }
.article-body br { display: block; content: ''; margin-top: .8em; }

.divider {
  height: 1px; background: linear-gradient(90deg, transparent, var(--bdr), transparent);
  margin: 4rem 0 3rem;
}

/* ── NEW DESIGN: Related News ── */
.related-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;
}
.related-card {
  background: #fff; border: 1px solid var(--bdr); border-radius: 20px;
  overflow: hidden; transition: all .3s ease; cursor: pointer; display: flex; flex-direction: column;
}
.related-card:hover {
  transform: translateY(-4px); box-shadow: 0 12px 28px rgba(34,197,94,.08); border-color: var(--g300);
}
.related-img { height: 150px; background: var(--g50); overflow: hidden; }
.related-img img { width: 100%; height: 100%; object-fit: cover; transition: transform .5s ease; }
.related-card:hover .related-img img { transform: scale(1.05); }
.related-body { padding: 18px; display: flex; flex-direction: column; flex: 1; }
.related-title {
  font-family: 'Nunito', sans-serif; font-size: .95rem; font-weight: 800; color: var(--txt);
  line-height: 1.45; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 10px;
}

::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-thumb{background:var(--g200);border-radius:99px;}
main { padding: 2rem 2.5rem 4rem; width: 100%; max-width: 1200px; margin: 0 auto; }

/* Responsive */
.menu-toggle { display: none; width: 38px; height: 38px; border-radius: 11px; background: white; border: 1px solid var(--bdr); align-items: center; justify-content: center; color: var(--sub); font-size: 0.9rem; cursor: pointer; }
@media (max-width: 1024px) {
  .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
  .sidebar.show { transform: translateX(0); }
  .page-wrap { margin-left: 0 !important; }
  .menu-toggle { display: flex; }
  main { padding: 1.5rem 1.5rem; }
  .article-title { font-size: 1.7rem; }
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
    <a href="news.php" class="tb-back"><i class="fas fa-arrow-left"></i></a>
    <div>
      <div style="font-family:'Nunito',sans-serif;font-size:.95rem;font-weight:800;color:var(--txt);">ย้อนกลับ</div>
    </div>
  </header>

  <main>
    <div class="article-container rv rv1">
      
      <div class="article-header">
        <div class="article-meta">
          <span style="background:var(--g50);border:1px solid var(--g200);color:var(--g700);padding:4px 12px;border-radius:99px;font-size:.7rem;font-weight:700;">
            <i class="fas fa-newspaper"></i> สาระสุขภาพ
          </span>
          <span><i class="fas fa-calendar-days" style="color:var(--g500);"></i> <?= date('j F Y', strtotime($news['created_at'])) ?></span>
          <span><i class="fas fa-clock" style="color:var(--g500);"></i> <?= date('H:i', strtotime($news['created_at'])) ?> น.</span>
        </div>
        
        <h1 class="article-title"><?= htmlspecialchars($news['title']) ?></h1>
      </div>

      <?php if (!empty($news['image_url'])): ?>
      <div class="article-hero-img">
        <img src="<?= htmlspecialchars($news['image_url']) ?>" alt="Cover" onerror="this.closest('.article-hero-img').style.display='none'">
      </div>
      <?php endif; ?>

      <div class="article-body">
        <?= nl2br($news['content']) ?>
      </div>

      <div class="divider"></div>

    </div>

    <?php if (count($related_news) > 0): ?>
    <div class="rv rv2" style="max-width:1000px;margin:0 auto;">
      <h2 style="font-family:'Nunito',sans-serif;font-size:1.25rem;font-weight:800;color:var(--txt);margin-bottom:24px;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-fire" style="color:var(--orange);"></i>
        บทความที่น่าสนใจ
      </h2>
      
      <div class="related-grid">
        <?php foreach ($related_news as $rel): ?>
        <div class="related-card" onclick="location.href='news_detail.php?id=<?= $rel['id'] ?>'">
          <div class="related-img">
            <?php if (!empty($rel['image_url'])): ?>
            <img src="<?= htmlspecialchars($rel['image_url']) ?>" alt="Img" onerror="this.closest('.related-img').innerHTML='<div style=\'width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:2rem;opacity:.2;\'>📰</div>'">
            <?php else: ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:2rem;opacity:.2;">📰</div>
            <?php endif; ?>
          </div>
          <div class="related-body">
            <h3 class="related-title"><?= htmlspecialchars($rel['title']) ?></h3>
            <div style="margin-top:auto;font-size:.68rem;color:var(--muted);display:flex;align-items:center;gap:5px;">
              <i class="far fa-clock"></i> <?= date('j M Y', strtotime($rel['created_at'])) ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>

</body>
</html>