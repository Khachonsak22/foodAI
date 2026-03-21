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

/* ── Fetch Most Viewed Recipes (by view_count) ── */
$most_viewed_sql = "
    SELECT 
        r.id,
        r.title,
        r.description,
        r.calories,
        r.image,
        r.servings,
        COALESCE(r.view_count, 0) as view_count,
        (SELECT COUNT(*) FROM user_interactions WHERE recipe_id = r.id AND interaction_type = 'favorite') as fav_count,
        (SELECT COUNT(*) FROM user_interactions WHERE recipe_id = r.id AND user_id = ? AND interaction_type = 'favorite') as is_fav
    FROM recipes r
    WHERE r.view_count > 0
    ORDER BY r.view_count DESC
    LIMIT 8
";

$stmt_viewed = $conn->prepare($most_viewed_sql);
$stmt_viewed->bind_param("i", $user_id);
$stmt_viewed->execute();
$most_viewed = $stmt_viewed->get_result()->fetch_all(MYSQLI_ASSOC);

/* ── Fetch Most Favorited Recipes ── */
$most_favorited_sql = "
    SELECT 
        r.id,
        r.title,
        r.description,
        r.calories,
        r.image,
        r.servings,
        COALESCE(r.view_count, 0) as view_count,
        COUNT(ui.id) as fav_count,
        (SELECT COUNT(*) FROM user_interactions WHERE recipe_id = r.id AND user_id = ? AND interaction_type = 'favorite') as is_fav
    FROM recipes r
    INNER JOIN user_interactions ui ON r.id = ui.recipe_id AND ui.interaction_type = 'favorite'
    GROUP BY r.id
    ORDER BY fav_count DESC
    LIMIT 8
";

$stmt_fav = $conn->prepare($most_favorited_sql);
$stmt_fav->bind_param("i", $user_id);
$stmt_fav->execute();
$most_favorited = $stmt_fav->get_result()->fetch_all(MYSQLI_ASSOC);

/* ── Calculate max values for percentage ── */
$max_views = !empty($most_viewed) ? $most_viewed[0]['view_count'] : 1;
$max_favs = !empty($most_favorited) ? $most_favorited[0]['fav_count'] : 1;

/* ── Top 3 for each category ── */
$top_3_viewed = array_slice($most_viewed, 0, 3);
$rest_viewed  = array_slice($most_viewed, 3);

$top_3_favorited = array_slice($most_favorited, 0, 3);
$rest_favorited  = array_slice($most_favorited, 3);

$medal_colors = [
    1 => ['bg' => '#fef3c7', 'border' => '#fbbf24', 'text' => '#92400e'],
    2 => ['bg' => '#f1f5f9', 'border' => '#94a3b8', 'text' => '#475569'],
    3 => ['bg' => '#fed7aa', 'border' => '#fb923c', 'text' => '#9a3412']
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เมนูยอดนิยม — FoodAI</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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

@keyframes slideUp{from{opacity:0;transform:translateY(18px);}to{opacity:1;transform:translateY(0);}}
.rv{opacity:0;animation:slideUp .5s cubic-bezier(.22,1,.36,1) forwards;}
.rv1{animation-delay:.04s;}.rv2{animation-delay:.10s;}.rv3{animation-delay:.17s;}.rv4{animation-delay:.24s;}

@keyframes flameDance{0%,100%{transform:rotate(-3deg) scale(1);}50%{transform:rotate(4deg) scale(1.12);}}
.flame{display:inline-block;animation:flameDance 1.9s ease-in-out infinite;}

.stitle{font-family:'Nunito',sans-serif;font-size:1rem;font-weight:800;color:var(--txt);display:flex;align-items:center;gap:8px;}
.gline{height:3px;background:linear-gradient(90deg,var(--g500),transparent);border-radius:99px;}

main { 
    padding: 2rem 2.5rem 3.5rem; 
    width: 100%; 
    max-width: 100%;
    margin: 0 auto; 
}

/* Top 3 Cards */
.top-card{background:#fff;border:2px solid;border-radius:20px;padding:24px;transition:all .22s;cursor:pointer;position:relative;overflow:hidden;}
.top-card::before{content:'';position:absolute;top:-50%;right:-50%;width:200%;height:200%;background:radial-gradient(circle,rgba(255,255,255,.4),transparent 70%);opacity:0;transition:opacity .4s;}
.top-card:hover::before{opacity:1;}
.top-card:hover{transform:translateY(-4px);box-shadow:0 16px 40px rgba(0,0,0,.12);}

.medal{font-size:2.5rem;margin-bottom:12px;}
.rank-badge{font-size:.6rem;font-weight:800;letter-spacing:.1em;padding:4px 12px;border-radius:8px;display:inline-block;margin-bottom:14px;}

/* Recipe Grid */
.recipe-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px;}
.r-card{background:#fff;border:1px solid var(--bdr);border-radius:18px;overflow:hidden;transition:all .22s;cursor:pointer;display:flex;flex-direction:column;position:relative;}
.r-card:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(34,197,94,.12);border-color:var(--g300);}
.r-img{height:180px;background:linear-gradient(135deg,#e2e8f0,#cbd5e1);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;}
.r-img img{width:100%;height:100%;object-fit:cover;}
.rank-num{position:absolute;top:10px;left:10px;width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.95);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.8rem;color:var(--g600);box-shadow:0 3px 10px rgba(0,0,0,.15);z-index:2;}
.fav-btn{position:absolute;top:10px;right:10px;width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.9);backdrop-filter:blur(6px);border:none;display:flex;align-items:center;justify-content:center;color:#cbd5e0;font-size:.8rem;cursor:pointer;z-index:2;transition:all .2s;}
.fav-btn:hover{transform:scale(1.1);box-shadow:0 4px 12px rgba(220,38,38,.3);}
.fav-btn.active{color:#dc2626;background:#fff;}
.r-body{padding:18px;display:flex;flex-direction:column;flex:1;}
.r-title{font-family:'Nunito',sans-serif;font-size:.95rem;font-weight:800;color:var(--txt);line-height:1.3;margin-bottom:8px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.r-desc{font-size:.72rem;color:var(--muted);line-height:1.5;margin-bottom:12px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.r-stats{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;}
.r-badge{font-size:.65rem;font-weight:700;padding:4px 10px;border-radius:8px;display:inline-flex;align-items:center;gap:4px;}
.badge-cal{background:var(--g50);color:var(--g700);border:1px solid var(--g200);}
.badge-fire{background:#fff7ed;color:#ea580c;border:1px solid #fed7aa;}
.badge-heart{background:#fee2e2;color:#dc2626;border:1px solid #fecaca;}
.badge-view{background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;}
.popularity-bar{height:6px;background:var(--g100);border-radius:99px;overflow:hidden;margin-bottom:8px;}
.popularity-fill{height:100%;border-radius:99px;transition:width .6s cubic-bezier(.22,1,.36,1);}
.r-btn{display:block;width:100%;text-align:center;padding:8px 16px;background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;border-radius:10px;text-decoration:none;font-size:.75rem;font-weight:700;transition:all .18s;}
.r-btn:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(34,197,94,.25);}

.empty{text-align:center;padding:80px 20px;background:rgba(255,255,255,.6);border-radius:20px;border:2px dashed var(--g200);}

@media(max-width:1024px){
  .sidebar{transform:translateX(-100%);}
  .sidebar.show{transform:translateX(0);}
  .page-wrap{margin-left:0;}
  .recipe-grid{grid-template-columns:repeat(auto-fill,minmax(220px,1fr));}
}
</style>
</head>
<body>

<?php include '../includes/sidebar.php' ?>

<!-- MAIN -->
<div class="page-wrap">

  <header class="topbar">
    <a href="dashboard.php" class="tb-back"><i class="fas fa-arrow-left"></i></a>
    <div>
      <div style="font-family:'Nunito',sans-serif;font-size:.95rem;font-weight:800;color:var(--txt);">เมนูยอดนิยม</div>
      <div style="font-size:.7rem;color:var(--muted);">อันดับเมนูที่ผู้ใช้ชื่นชอบมากที่สุด</div>
    </div>
  </header>

  <main>

    <!-- HEADER -->
    <div class="rv rv1" style="margin-bottom:1.8rem;">
      <p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">อันดับความนิยม</p>
      <h1 style="font-family:'Nunito',sans-serif;font-size:1.6rem;font-weight:800;color:var(--txt);line-height:1.1;">
        <span class="flame"><i class="bi bi-fire" style="color: #ff5722;"></i></span> เมนูยอดนิยมจากผู้ใช้งาน
      </h1>
      <div class="gline" style="width:50px;margin-top:10px;"></div>
    </div>

    <!-- MOST VIEWED SECTION -->
    <?php if (count($top_3_viewed) > 0): ?>
    <div class="rv rv2" style="margin-bottom:2.5rem;">
      <h2 class="stitle" style="margin-bottom:18px;">Top 3 เมนูที่มีผู้เข้าดูมากที่สุด</h2>
      
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;">
        <?php foreach ($top_3_viewed as $idx => $recipe):
          $rank = $idx + 1;
          $color = $medal_colors[$rank];
          $medals = [
            '<i class="fas fa-medal" style="color: #FFD700;" title="อันดับ 1"></i>', // สีทอง
            '<i class="fas fa-medal" style="color: #C0C0C0;" title="อันดับ 2"></i>', // สีเงิน
            '<i class="fas fa-medal" style="color: #CD7F32;" title="อันดับ 3"></i>'  // สีทองแดง
        ];
          $popularity = $max_views > 0 ? round($recipe['view_count'] / $max_views * 100) : 0;
        ?>
        <div class="top-card" 
             style="border-color:<?= $color['border'] ?>;background:linear-gradient(to bottom,<?= $color['bg'] ?>,#fff);"
             onclick="location.href='recipe_detail.php?id=<?= $recipe['id'] ?>'">
          <div style="position:relative;z-index:1;">
            <div class="medal"><?= $medals[$idx] ?></div>
            <div class="rank-badge" style="background:<?= $color['border'] ?>;color:<?= $color['text'] ?>;">
              อันดับ <?= $rank ?>
            </div>
            
            <h3 style="font-family:'Nunito',sans-serif;font-size:1.05rem;font-weight:800;color:var(--txt);line-height:1.4;margin-bottom:10px;">
              <?= htmlspecialchars($recipe['title']) ?>
            </h3>
            
            <p style="font-size:.78rem;color:var(--muted);line-height:1.6;margin-bottom:14px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
              <?= htmlspecialchars($recipe['description'] ?: 'เมนูยอดนิยมจากผู้ใช้งาน') ?>
            </p>

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
              <span class="r-badge badge-view">
                <i class="fas fa-eye" style="font-size:.65rem;"></i> <?= number_format($recipe['view_count']) ?> ครั้ง
              </span>
              <span class="r-badge badge-cal">
                <i class="fas fa-fire" style="font-size:.65rem;"></i> <?= $recipe['calories'] ?: '—' ?> kcal
              </span>
              <?php if ($recipe['fav_count'] > 0): ?>
              <span class="r-badge badge-heart">
                <i class="fas fa-heart" style="font-size:.65rem;"></i> <?= $recipe['fav_count'] ?>
              </span>
              <?php endif; ?>
            </div>

            <div style="margin-bottom:8px;">
              <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                <span style="font-size:.65rem;color:<?= $color['text'] ?>;font-weight:700;">การเข้าชม</span>
                <span style="font-size:.65rem;color:<?= $color['text'] ?>;font-weight:800;"><?= $popularity ?>%</span>
              </div>
              <div class="popularity-bar">
                <div class="popularity-fill" style="width:<?= $popularity ?>%;background:<?= $color['border'] ?>;"></div>
              </div>
            </div>

            <a href="recipe_detail.php?id=<?= $recipe['id'] ?>" class="r-btn" style="background:<?= $color['border'] ?>;">
              <i class="fas fa-book-open" style="font-size:.7rem;margin-right:5px;"></i> ดูสูตร
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- REST OF VIEWED -->
    <?php if (count($rest_viewed) > 0): ?>
    <div class="rv rv3" style="margin-bottom:3rem;">
      <h2 class="stitle" style="margin-bottom:18px;">เมนูที่มีผู้เข้าดูอื่นๆ</h2>
      
      <div class="recipe-grid">
        <?php foreach ($rest_viewed as $idx => $r):
          $rank = $idx + 4;
          $popularity = $max_views > 0 ? round($r['view_count'] / $max_views * 100) : 0;
        ?>
        <div class="r-card" onclick="location.href='recipe_detail.php?id=<?= $r['id'] ?>'">
          <div class="r-img">
            <div class="rank-num">#<?= $rank ?></div>
            
            <?php
            $img = '../public/uploads/'.$r['image'];
            if (!empty($r['image']) && file_exists($img)):
            ?>
            <img src="<?= $img ?>" alt="<?= htmlspecialchars($r['title']) ?>">
            <?php else: ?>
            <i class="fas fa-utensils" style="font-size:2.5rem;color:rgba(255,255,255,.4);"></i>
            <?php endif; ?>
            
            <button class="fav-btn <?= $r['is_fav']>0?'active':'' ?>" 
                    onclick="event.stopPropagation(); toggleFav(<?= $r['id'] ?>, this)"
                    id="favbtn-<?= $r['id'] ?>">
              <i class="fas fa-heart"></i>
            </button>
          </div>

          <div class="r-body">
            <h3 class="r-title"><?= htmlspecialchars($r['title']) ?></h3>
            <p class="r-desc"><?= htmlspecialchars($r['description'] ?: 'เมนูยอดนิยม') ?></p>
            
            <div class="r-stats">
              <span class="r-badge badge-view">
                <i class="fas fa-eye" style="font-size:.65rem;"></i> <?= number_format($r['view_count']) ?>
              </span>
              <span class="r-badge badge-cal">
                <i class="fas fa-fire" style="font-size:.65rem;"></i> <?= $r['calories'] ?: '—' ?> kcal
              </span>
            </div>

            <div style="margin-bottom:8px;">
              <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                <span style="font-size:.65rem;color:var(--muted);">การเข้าชม</span>
                <span style="font-size:.65rem;color:#2563eb;font-weight:700;"><?= $popularity ?>%</span>
              </div>
              <div class="popularity-bar">
                <div class="popularity-fill" style="width:<?= $popularity ?>%;background:#3b82f6;"></div>
              </div>
            </div>

            <a href="recipe_detail.php?id=<?= $r['id'] ?>" class="r-btn">
              <i class="fas fa-book-open" style="font-size:.7rem;margin-right:5px;"></i> ดูสูตร
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- MOST FAVORITED SECTION -->
    <?php if (count($top_3_favorited) > 0): ?>
    <div class="rv rv4" style="margin-bottom:2.5rem;">
      <h2 class="stitle" style="margin-bottom:18px;">Top 3 เมนูที่ผู้ใช้กดถูกใจมากที่สุด</h2>
      
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;">
        <?php foreach ($top_3_favorited as $idx => $recipe):
          $rank = $idx + 1;
          $color = $medal_colors[$rank];
          $medals = [
              '<i class="fas fa-medal" style="color: #FFD700;" title="อันดับ 1"></i>', // สีทอง
              '<i class="fas fa-medal" style="color: #C0C0C0;" title="อันดับ 2"></i>', // สีเงิน
              '<i class="fas fa-medal" style="color: #CD7F32;" title="อันดับ 3"></i>'  // สีทองแดง
          ];
          $popularity = $max_favs > 0 ? round($recipe['fav_count'] / $max_favs * 100) : 0;
        ?>
        <div class="top-card" 
             style="border-color:<?= $color['border'] ?>;background:linear-gradient(to bottom,<?= $color['bg'] ?>,#fff);"
             onclick="location.href='recipe_detail.php?id=<?= $recipe['id'] ?>'">
          <div style="position:relative;z-index:1;">
            <div class="medal"><?= $medals[$idx] ?></div>
            <div class="rank-badge" style="background:<?= $color['border'] ?>;color:<?= $color['text'] ?>;">
              อันดับ <?= $rank ?>
            </div>
            
            <h3 style="font-family:'Nunito',sans-serif;font-size:1.05rem;font-weight:800;color:var(--txt);line-height:1.4;margin-bottom:10px;">
              <?= htmlspecialchars($recipe['title']) ?>
            </h3>
            
            <p style="font-size:.78rem;color:var(--muted);line-height:1.6;margin-bottom:14px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
              <?= htmlspecialchars($recipe['description'] ?: 'เมนูยอดนิยมจากผู้ใช้งาน') ?>
            </p>

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
              <span class="r-badge badge-heart">
                <i class="fas fa-heart" style="font-size:.65rem;"></i> <?= $recipe['fav_count'] ?> คน
              </span>
              <span class="r-badge badge-view">
                <i class="fas fa-eye" style="font-size:.65rem;"></i> <?= number_format($recipe['view_count']) ?>
              </span>
              <span class="r-badge badge-cal">
                <i class="fas fa-fire" style="font-size:.65rem;"></i> <?= $recipe['calories'] ?: '—' ?> kcal
              </span>
            </div>

            <div style="margin-bottom:8px;">
              <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                <span style="font-size:.65rem;color:<?= $color['text'] ?>;font-weight:700;">ความนิยม</span>
                <span style="font-size:.65rem;color:<?= $color['text'] ?>;font-weight:800;"><?= $popularity ?>%</span>
              </div>
              <div class="popularity-bar">
                <div class="popularity-fill" style="width:<?= $popularity ?>%;background:<?= $color['border'] ?>;"></div>
              </div>
            </div>

            <a href="recipe_detail.php?id=<?= $recipe['id'] ?>" class="r-btn" style="background:<?= $color['border'] ?>;">
              <i class="fas fa-book-open" style="font-size:.7rem;margin-right:5px;"></i> ดูสูตร
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- REST OF FAVORITED -->
    <?php if (count($rest_favorited) > 0): ?>
    <div class="rv rv4">
      <h2 class="stitle" style="margin-bottom:18px;">เมนูที่ผู้ใช้กดถูกใจอื่นๆ</h2>
      
      <div class="recipe-grid">
        <?php foreach ($rest_favorited as $idx => $r):
          $rank = $idx + 4;
          $popularity = $max_favs > 0 ? round($r['fav_count'] / $max_favs * 100) : 0;
        ?>
        <div class="r-card" onclick="location.href='recipe_detail.php?id=<?= $r['id'] ?>'">
          <div class="r-img">
            <div class="rank-num">#<?= $rank ?></div>
            
            <?php
            $img = '../public/uploads/'.$r['image'];
            if (!empty($r['image']) && file_exists($img)):
            ?>
            <img src="<?= $img ?>" alt="<?= htmlspecialchars($r['title']) ?>">
            <?php else: ?>
            <i class="fas fa-utensils" style="font-size:2.5rem;color:rgba(255,255,255,.4);"></i>
            <?php endif; ?>
            
            <button class="fav-btn <?= $r['is_fav']>0?'active':'' ?>" 
                    onclick="event.stopPropagation(); toggleFav(<?= $r['id'] ?>, this)"
                    id="favbtn-<?= $r['id'] ?>">
              <i class="fas fa-heart"></i>
            </button>
          </div>

          <div class="r-body">
            <h3 class="r-title"><?= htmlspecialchars($r['title']) ?></h3>
            <p class="r-desc"><?= htmlspecialchars($r['description'] ?: 'เมนูยอดนิยม') ?></p>
            
            <div class="r-stats">
              <span class="r-badge badge-heart">
                <i class="fas fa-heart" style="font-size:.65rem;"></i> <?= $r['fav_count'] ?>
              </span>
              <span class="r-badge badge-view">
                <i class="fas fa-eye" style="font-size:.65rem;"></i> <?= number_format($r['view_count']) ?>
              </span>
            </div>

            <div style="margin-bottom:8px;">
              <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                <span style="font-size:.65rem;color:var(--muted);">ความนิยม</span>
                <span style="font-size:.65rem;color:#dc2626;font-weight:700;"><?= $popularity ?>%</span>
              </div>
              <div class="popularity-bar">
                <div class="popularity-fill" style="width:<?= $popularity ?>%;background:#dc2626;"></div>
              </div>
            </div>

            <a href="recipe_detail.php?id=<?= $r['id'] ?>" class="r-btn">
              <i class="fas fa-book-open" style="font-size:.7rem;margin-right:5px;"></i> ดูสูตร
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (count($most_viewed) === 0 && count($most_favorited) === 0): ?>
    <div class="rv rv3 empty">
      <div style="font-size:2.5rem;opacity:.2;margin-bottom:12px;">📊</div>
      <p style="font-size:.9rem;font-weight:600;margin-bottom:5px;">ยังไม่มีข้อมูลเมนูยอดนิยม</p>
      <p style="font-size:.75rem;color:var(--muted);margin-bottom:18px;">เริ่มบันทึกเมนูอาหารเพื่อให้เห็นสถิติ</p>
      <a href="recipes.php" style="display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;text-decoration:none;padding:9px 20px;border-radius:11px;font-size:.78rem;font-weight:700;">
        <i class="fas fa-book-open" style="font-size:.7rem;"></i> สำรวจสูตรอาหาร
      </a>
    </div>
    <?php endif; ?>

  </main>
</div>

<script>
// ✅ Use toggle_favorite.php endpoint
async function toggleFav(recipeId, btn) {
  try {
    const res = await fetch('../api/toggle_favorite.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ recipe_id: recipeId })
    });
    
    const data = await res.json();
    
    if (data.success) {
      btn.classList.toggle('active');
      
      // Show appropriate message
      if (data.action === 'added') {
        showToast('❤️ เพิ่มในรายการโปรดแล้ว');
      } else {
        showToast('💔 ลบออกจากรายการโปรดแล้ว');
      }
      
      // Reload after 1 second to update counts
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast('⚠️ ' + (data.message || 'เกิดข้อผิดพลาด'), true);
    }
  } catch(e) {
    console.error('Favorite error:', e);
    showToast('⚠️ เกิดข้อผิดพลาด กรุณาลองใหม่', true);
  }
}

function showToast(msg, isError = false) {
  const toast = document.createElement('div');
  toast.textContent = msg;
  toast.style.cssText = `
    position:fixed;bottom:24px;right:24px;z-index:9999;
    background:${isError?'#fee2e2':'#f0fdf4'};
    color:${isError?'#dc2626':'#15803d'};
    border:1px solid ${isError?'#fecaca':'#bbf7d0'};
    padding:12px 20px;border-radius:12px;font-size:.85rem;font-weight:600;
    box-shadow:0 8px 24px rgba(0,0,0,.12);
    animation:slideInUp .3s ease;
  `;
  document.body.appendChild(toast);
  setTimeout(() => {
    toast.style.animation = 'slideOutDown .3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 2500);
}

const style = document.createElement('style');
style.textContent = `
  @keyframes slideInUp { from{opacity:0;transform:translateY(20px);} to{opacity:1;transform:translateY(0);} }
  @keyframes slideOutDown { from{opacity:1;transform:translateY(0);} to{opacity:0;transform:translateY(20px);} }
`;
document.head.appendChild(style);
</script>

</body>
</html>