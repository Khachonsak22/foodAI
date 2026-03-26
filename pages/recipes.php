<?php
session_start();

$db_path = '../config/connect.php';
if (file_exists($db_path)) {
    include $db_path;
} else {
    $conn = mysqli_connect("localhost", "root", "", "myfood");
}

if (!$conn) die("Database Connection Failed");

if (!isset($_SESSION['user_id'])) {
    $user_id = 3;
} else {
    $user_id = $_SESSION['user_id'];
}

/* ── User info ── */
$u_stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$u_data    = $u_stmt->get_result()->fetch_assoc();
$firstName = $u_data['first_name'] ?? 'User';
$lastName  = $u_data['last_name']  ?? '';
$initials  = mb_strtoupper(mb_substr($firstName,0,1)).mb_strtoupper(mb_substr($lastName,0,1));

/* ── 1. ดึงข้อมูล Tags ทั้งหมดจาก Database เพื่อสร้างตัวกรอง ── */
$tags_result = $conn->query("SELECT * FROM tags ORDER BY type ASC, id ASC")->fetch_all(MYSQLI_ASSOC);
$disease_tags = array_filter($tags_result, fn($t) => $t['type'] === 'health_condition');
$allergy_tags = array_filter($tags_result, fn($t) => $t['type'] === 'allergen');

/* ── รับค่าตัวกรองจาก URL (GET) แบบ Array ของ ID ── */
$search_text = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all';
$filter_diseases = isset($_GET['diseases']) ? (array)$_GET['diseases'] : [];
$filter_allergies = isset($_GET['allergies']) ? (array)$_GET['allergies'] : [];

/* ── 2. ดึงข้อมูลเมนูอาหารทั้งหมด และการกดหัวใจ ── */
$sql = "SELECT r.*, 
        (SELECT COUNT(*) FROM user_interactions ui 
         WHERE ui.recipe_id = r.id AND ui.user_id = ? AND ui.interaction_type = 'favorite') as is_fav,
        COALESCE(r.view_count, 0) as views,
        DATEDIFF(NOW(), r.created_at) as days_old
        FROM recipes r 
        ORDER BY r.id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* ── 3. ดึงความสัมพันธ์ Recipe <-> Tag มาจับคู่ใน PHP ให้แม่นยำ ── */
$rt_sql = "SELECT rt.recipe_id, t.* FROM recipe_tags rt JOIN tags t ON rt.tag_id = t.id";
$rt_res = $conn->query($rt_sql);
$all_recipe_tags = [];
while($row = $rt_res->fetch_assoc()) {
    $all_recipe_tags[$row['recipe_id']][] = $row;
}
foreach($recipes as &$r) {
    $r['tags'] = $all_recipe_tags[$r['id']] ?? [];
}
unset($r);

/* ── 4. ตรรกะการกรองข้อมูลแบบเสถียร 100% ── */
if ($search_text !== '' || !empty($filter_diseases) || !empty($filter_allergies) || $filter === 'favorites') {
    $recipes = array_filter($recipes, function($r) use ($search_text, $filter_diseases, $filter_allergies, $filter) {
        $recipe_tag_ids = array_column($r['tags'], 'id');
        
        // กรองรายการโปรด
        if ($filter === 'favorites' && $r['is_fav'] == 0) return false;

        // กฎที่ 1: อาหารที่แพ้ -> ถ้าเมนูนี้มี Tag ที่แพ้ ให้ "คัดทิ้งทันที"
        foreach ($filter_allergies as $allergy_id) {
            if (in_array($allergy_id, $recipe_tag_ids)) {
                return false; // ไม่ปลอดภัย
            }
        }

        // กฎที่ 2: โรคประจำตัว -> ถ้าเมนูนี้ "ไม่มี" Tag ว่าทานได้ ให้ "คัดทิ้ง"
        foreach ($filter_diseases as $disease_id) {
            if (!in_array($disease_id, $recipe_tag_ids)) {
                return false; // ไม่เหมาะกับโรค
            }
        }

        // กฎที่ 3: คำค้นหาทั่วไป (Text)
        if ($search_text !== '') {
            $text_pool = mb_strtolower($r['title'] . ' ' . $r['description'] . ' ' . implode(' ', array_column($r['tags'], 'name')), 'UTF-8');
            $terms = array_filter(explode(' ', mb_strtolower($search_text, 'UTF-8')));
            foreach ($terms as $term) {
                if (mb_stripos($text_pool, $term, 0, 'UTF-8') === false) {
                    return false;
                }
            }
        }

        return true; // ปลอดภัยและตรงเงื่อนไข
    });
}

// ── สุ่มลำดับเมนู ──
shuffle($recipes);
$total_recipes = count($recipes);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>สูตรอาหาร — FoodAI</title>
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

main { padding: 2rem 2.5rem 3.5rem; width: 100%; max-width: 100%; margin: 0 auto; }

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
.rv1{animation-delay:.04s;}.rv2{animation-delay:.10s;}.rv3{animation-delay:.17s;}

.stitle{font-family:'Nunito',sans-serif;font-size:1rem;font-weight:800;color:var(--txt);display:flex;align-items:center;gap:8px;}
.gline{height:3px;background:linear-gradient(90deg,var(--g500),transparent);border-radius:99px;}

/* Recipe Grid */
.recipe-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px;}

/* Recipe Card */
.r-card{background:#fff;border:1px solid var(--bdr);border-radius:18px;overflow:hidden;transition:all .22s;cursor:pointer;display:flex;flex-direction:column;position:relative;}
.r-card:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(34,197,94,.12);border-color:var(--g300);}

/* NEW Badge */
.new-badge{
  position:absolute;
  top:12px;
  left:12px;
  z-index:15;
  background:linear-gradient(135deg, #fbbf24, #f59e0b);
  color:#fff;
  padding:5px 12px;
  border-radius:20px;
  font-size:.65rem;
  font-weight:800;
  font-family:'Nunito',sans-serif;
  letter-spacing:.03em;
  box-shadow:0 4px 12px rgba(251,191,36,.4);
  display:flex;
  align-items:center;
  gap:4px;
  animation:newBadgePulse 2s ease-in-out infinite;
}
.new-badge-text{
  text-shadow:0 1px 2px rgba(0,0,0,.15);
}

@keyframes newBadgePulse{
  0%, 100%{
    transform:scale(1);
    box-shadow:0 4px 12px rgba(251,191,36,.4);
  }
  50%{
    transform:scale(1.05);
    box-shadow:0 6px 16px rgba(251,191,36,.6);
  }
}

.r-img{height:180px;background:linear-gradient(135deg,#e2e8f0,#cbd5e1);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;}
.r-img img{width:100%;height:100%;object-fit:cover;}
.r-img-placeholder{font-size:2.5rem;color:rgba(255,255,255,.4);}

.fav-btn{position:absolute;top:12px;right:12px;width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.95);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.3);display:flex;align-items:center;justify-content:center;color:#cbd5e1;font-size:.85rem;cursor:pointer;transition:all .2s;z-index:10;box-shadow:0 4px 12px rgba(0,0,0,.1);}
.fav-btn:hover{transform:scale(1.1);background:#fff;}
.fav-btn.active{color:#f43f5e;background:#fff;}

.r-body{padding:16px;display:flex;flex-direction:column;flex:1;}
.r-title{font-family:'Nunito',sans-serif;font-size:.95rem;font-weight:700;color:var(--txt);line-height:1.4;margin-bottom:6px;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;}

/* แสดง Tag ในการ์ด */
.r-tags-wrap { display:flex; flex-wrap:wrap; gap:5px; margin-bottom:8px; }
.r-tag { font-size:.6rem; font-weight:600; padding:2px 8px; border-radius:6px; white-space:nowrap; display:inline-flex; align-items:center; gap:3px;}

.r-desc{font-size:.75rem;color:var(--muted);line-height:1.6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:12px;}

.r-meta{display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap;}
.r-badge{font-size:.68rem;font-weight:700;padding:4px 10px;border-radius:8px;display:inline-flex;align-items:center;gap:4px;}
.badge-cal{background:var(--g50);color:var(--g700);border:1px solid var(--g200);}
.badge-view{background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd;}

.r-btn{width:100%;padding:9px;border-radius:11px;background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;font-size:.78rem;font-weight:700;text-align:center;text-decoration:none;display:block;transition:opacity .2s,box-shadow .2s;margin-top:auto;font-family:'Kanit',sans-serif;}
.r-btn:hover{opacity:.88;box-shadow:0 4px 16px rgba(34,197,94,.35);color:#fff;}

/* Search & Filter */
.search-wrap{background:#fff;border:1.5px solid var(--bdr);border-radius:13px;display:flex;align-items:center;gap:10px;padding:0 14px;height:44px;transition:border-color .18s,box-shadow .18s;flex:1;max-width:380px;}
.search-wrap:focus-within{border-color:var(--g400);box-shadow:0 0 0 3px rgba(74,222,128,.12);}
.search-wrap input{border:none;outline:none;background:transparent;font-family:'Kanit',sans-serif;font-size:.85rem;color:var(--txt);width:100%;flex:1;}
.search-wrap input::placeholder{color:var(--muted);}

.filter-tabs{display:flex;gap:6px;background:var(--g50);border-radius:12px;padding:4px;}
.filter-tab{padding:7px 18px;border-radius:9px;font-size:.75rem;font-weight:600;color:var(--sub);cursor:pointer;transition:all .18s;text-decoration:none;display:flex;align-items:center;gap:6px;}
.filter-tab:hover{background:rgba(255,255,255,.6);color:var(--g700);}
.filter-tab.active{background:#fff;color:var(--g600);box-shadow:0 2px 8px rgba(34,197,94,.12);}

/* Empty state */
.empty{border:2px dashed var(--g200);border-radius:16px;text-align:center;padding:3rem 1.5rem;color:var(--muted);background:#fff;}

::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-thumb{background:var(--g200);border-radius:99px;}

/* ── 🌟 Responsive CSS (รองรับมือถือและทุกหน้าจอ) ── */
.menu-toggle { display: none; width: 38px; height: 38px; border-radius: 11px; background: white; border: 1px solid var(--bdr); align-items: center; justify-content: center; color: var(--sub); font-size: 0.9rem; cursor: pointer; flex-shrink: 0; }

@media (max-width: 1024px) {
  .sidebar { transform: translateX(-100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
  .sidebar.show { transform: translateX(0); }
  .page-wrap { margin-left: 0 !important; }
  .menu-toggle { display: flex; }
}

@media (max-width: 768px) {
  .topbar { flex-wrap: wrap; height: auto; padding: 12px 1.5rem; gap: 10px; }
  .search-wrap-container { max-width: 100% !important; margin-top: 5px; width: 100%; justify-content: flex-start !important; }
  .search-wrap { max-width: 100% !important; }
  main { padding: 1.5rem 1.2rem 3rem !important; }
  .rv1 { flex-direction: column; align-items: flex-start !important; gap: 12px; }
}

@media (max-width: 480px) {
  .topbar { padding: 12px 1rem; }
  .recipe-grid { grid-template-columns: 1fr; }
  /* ปรับ Dropdown เมนูกรองให้กลายเป็น Popup ตรงกลางจอบนมือถือ */
  #filterDropdown { position: fixed !important; top: 10% !important; left: 5% !important; right: 5% !important; max-height: 80vh !important; }
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
      <div style="font-family:'Nunito',sans-serif;font-size:.95rem;font-weight:800;color:var(--txt);">สูตรอาหาร</div>
      <div style="font-size:.7rem;color:var(--muted);">เลือกเมนูเพื่อสุขภาพของคุณ</div>
    </div>
    
    <div class="search-wrap-container" style="margin-left:auto; display:flex; align-items:center; gap:12px; flex:1; max-width:520px; position:relative; justify-content:flex-end;">
      
      <form method="GET" id="searchForm" class="search-wrap" style="margin:0; width:100%; display:flex; align-items:center;">
        <i class="fas fa-magnifying-glass" style="color:var(--g500);font-size:.75rem;flex-shrink:0;"></i>
        <input type="text" name="search" value="<?= htmlspecialchars($search_text) ?>" placeholder="ค้นหาเมนูอาหาร..." style="flex:1;">
        <?php if ($filter !== 'all'): ?>
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <?php endif; ?>
        <button type="button" onclick="toggleFilters()" style="padding:4px 10px;background:var(--g50);border:1px solid var(--bdr);border-radius:6px;font-size:.7rem;color:var(--sub);cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:4px;">
          <i class="fas fa-filter"></i> กรอง
        </button>

        <div id="filterDropdown" style="display:none;position:absolute;top:calc(100% + 8px);left:0;right:0;background:#fff;border:1.5px solid var(--bdr);border-radius:12px;box-shadow:0 8px 24px rgba(34,197,94,.12);padding:18px;z-index:200;max-height:450px;overflow-y:auto;text-align:left;">
            
            <p style="font-size:.75rem;font-weight:700;color:var(--g700);margin-bottom:10px;"><i class="fas fa-stethoscopes"></i> เลือกโรคประจำตัว (เมนูที่ปลอดภัยและทานได้):</p>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;border-bottom:1px solid var(--bdr);padding-bottom:15px;">
            <?php foreach($disease_tags as $t): ?>
                <label style="display:inline-flex;align-items:center;gap:5px;padding:5px 10px;background:var(--g50);border:1px solid var(--bdr);border-radius:8px;cursor:pointer;font-size:.7rem;">
                    <input type="checkbox" name="diseases[]" value="<?= $t['id'] ?>" <?= in_array($t['id'], $filter_diseases) ? 'checked' : '' ?> style="accent-color:var(--g500);">
                    <span><?= $t['icon'] ?> <?= htmlspecialchars($t['name']) ?></span>
                </label>
            <?php endforeach; ?>
            </div>

            <p style="font-size:.75rem;font-weight:700;color:#dc2626;margin-bottom:10px;"><i class="fas fa-ban"></i> อาหารที่แพ้ (ห้ามมีส่วนผสมของ):</p>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:15px;">
            <?php foreach($allergy_tags as $t): ?>
                <label style="display:inline-flex;align-items:center;gap:5px;padding:5px 10px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;cursor:pointer;font-size:.7rem;">
                    <input type="checkbox" name="allergies[]" value="<?= $t['id'] ?>" <?= in_array($t['id'], $filter_allergies) ? 'checked' : '' ?> style="accent-color:#dc2626;">
                    <span style="color:#991b1b;"><?= $t['icon'] ?> <?= htmlspecialchars($t['name']) ?></span>
                </label>
            <?php endforeach; ?>
            </div>

            <div style="margin-top:15px;display:flex;justify-content:flex-end;gap:10px;">
                <a href="recipes.php" style="padding:6px 16px;background:var(--g50);color:var(--sub);border:1px solid var(--bdr);border-radius:8px;font-size:.75rem;font-weight:600;text-decoration:none;">ล้างตัวกรอง</a>
                <button type="submit" style="padding:6px 16px;background:var(--g500);color:white;border:none;border-radius:8px;font-size:.75rem;font-weight:600;cursor:pointer;box-shadow:0 4px 10px rgba(34,197,94,0.2);">ค้นหา</button>
            </div>
        </div>
      </form>
    </div>
  </header>

  <main style="padding:2rem 2.5rem 3.5rem;max-width:1320px;width:100%;">

    <div class="rv rv1" style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:1.8rem;gap:16px;flex-wrap:wrap;">
      <div>
        <p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">คลังเมนู</p>
        <h1 style="font-family:'Nunito',sans-serif;font-size:1.6rem;font-weight:800;color:var(--txt);line-height:1.1;">
          สูตรอาหารทั้งหมด
          <span style="font-size:1rem;color:var(--muted);font-weight:600;">(<?= $total_recipes ?> เมนู)</span>
        </h1>
        <div class="gline" style="width:50px;margin-top:10px;"></div>
      </div>

      <div class="filter-tabs">
        <a href="?<?= $search_text ? 'search='.urlencode($search_text).'&' : '' ?>filter=all" 
           class="filter-tab <?= $filter==='all'?'active':'' ?>">
          <i class="fas fa-grid-2" style="font-size:.7rem;"></i> ทั้งหมด
        </a>
        <a href="?<?= $search_text ? 'search='.urlencode($search_text).'&' : '' ?>filter=favorites" 
           class="filter-tab <?= $filter==='favorites'?'active':'' ?>">
          <i class="fas fa-heart" style="font-size:.7rem;"></i> รายการโปรด
        </a>
      </div>
    </div>

    <div class="rv rv2">
      <?php if (count($recipes) > 0): ?>
      <div class="recipe-grid">
        <?php foreach ($recipes as $r): ?>
        <div class="r-card" onclick="location.href='recipe_detail.php?id=<?= $r['id'] ?>'">
          <!-- NEW Badge -->
          <?php if (isset($r['days_old']) && $r['days_old'] <= 3): ?>
          <div class="new-badge">
            <span class="new-badge-text">✨ NEW</span>
          </div>
          <?php endif; ?>
          
          <div class="r-img">
            <?php
            $img = '../public/uploads/'.$r['image'];
            if (!empty($r['image']) && file_exists($img)):
            ?>
            <img src="<?= $img ?>" alt="<?= htmlspecialchars($r['title']) ?>">
            <?php else: ?>
            <i class="fas fa-utensils r-img-placeholder"></i>
            <?php endif; ?>
            
            <button class="fav-btn <?= $r['is_fav']>0?'active':'' ?>" 
                    onclick="event.stopPropagation(); toggleFav(<?= $r['id'] ?>, this)"
                    id="favbtn-<?= $r['id'] ?>"
                    title="<?= $r['is_fav']>0?'ลบออกจากรายการโปรด':'เพิ่มในรายการโปรด' ?>">
              <i class="<?= $r['is_fav']>0?'fa-solid':'fa-regular' ?> fa-heart"></i>
            </button>
          </div>

          <div class="r-body">
            <h3 class="r-title"><?= htmlspecialchars($r['title']) ?></h3>
            
            <div class="r-tags-wrap">
                <?php foreach($r['tags'] as $t): 
                    $is_allergy = ($t['type'] === 'allergen');
                ?>
                <span class="r-tag" style="background:<?= $is_allergy ? '#fef2f2' : '#ecfdf5' ?>; color:<?= $is_allergy ? '#dc2626' : '#059669' ?>; border:1px solid <?= $is_allergy ? '#fecaca' : '#a7f3d0' ?>;">
                    <?= htmlspecialchars($t['name']) ?>
                </span>
                <?php endforeach; ?>
            </div>

            <p class="r-desc"><?= htmlspecialchars($r['description'] ?: 'เมนูอาหารเพื่อสุขภาพ') ?></p>
            
            <div class="r-meta">
              <span class="r-badge badge-cal">
                <i class="bi bi-fire" style="font-size:.65rem; color: #ff5722;"></i> <?= $r['calories'] ?: '—' ?> kcal
              </span>
              <?php if ($r['views'] > 0): ?>
              <span class="r-badge badge-view">
                <i class="fas fa-eye" style="font-size:.65rem;"></i> <?= number_format($r['views']) ?>
              </span>
              <?php endif; ?>
            </div>

            <a href="recipe_detail.php?id=<?= $r['id'] ?>" class="r-btn">
              <i class="fas fa-book-open" style="font-size:.7rem;margin-right:5px;"></i> ดูวิธีทำและโภชนาการ
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php else: ?>
      <div class="empty">
        <div style="font-size:2.5rem;opacity:.2;margin-bottom:12px;">🔍</div>
        <p style="font-size:.9rem;font-weight:600;margin-bottom:5px;">
          <?= ($search_text || !empty($filter_diseases) || !empty($filter_allergies)) ? 'ไม่พบเมนูที่ปลอดภัยหรือตรงเงื่อนไข' : ($filter==='favorites' ? 'ยังไม่มีรายการโปรด' : 'ยังไม่มีสูตรอาหาร') ?>
        </p>
        <p style="font-size:.75rem;color:var(--muted);margin-bottom:18px;">
          <?= ($search_text || !empty($filter_diseases) || !empty($filter_allergies)) ? 'ลองเปลี่ยนเงื่อนไขการค้นหา หรือล้างตัวกรอง' : 'เริ่มสำรวจสูตรอาหารจากเชฟ AI' ?>
        </p>
        <?php if ($filter === 'favorites' || $search_text || !empty($filter_diseases) || !empty($filter_allergies)): ?>
        <a href="recipes.php" style="display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;text-decoration:none;padding:9px 20px;border-radius:11px;font-size:.78rem;font-weight:700;">
          <i class="fas fa-sync" style="font-size:.7rem;"></i> ล้างการค้นหาทั้งหมด
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>

<script>
function toggleFilters() {
  const dropdown = document.getElementById('filterDropdown');
  dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

// ปิด Dropdown เมื่อคลิกนอกพื้นที่
document.addEventListener('click', function(e) {
  const container = document.querySelector('.search-wrap-container');
  const dropdown = document.getElementById('filterDropdown');
  if (container && !container.contains(e.target) && dropdown.style.display === 'block') {
    dropdown.style.display = 'none';
  }
});

const userId = <?= $user_id ?>;

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
      const icon = btn.querySelector('i');
      if (btn.classList.contains('active')) {
        icon.classList.remove('fa-regular'); icon.classList.add('fa-solid');
        btn.title = 'ลบออกจากรายการโปรด';
      } else {
        icon.classList.remove('fa-solid'); icon.classList.add('fa-regular');
        btn.title = 'เพิ่มในรายการโปรด';
      }
      showToast(data.action === 'added' ? '❤️ เพิ่มในรายการโปรดแล้ว' : '💔 ลบออกจากรายการโปรดแล้ว');
    } else {
      showToast('⚠️ ' + (data.message || 'เกิดข้อผิดพลาด'), true);
    }
  } catch(e) {
    showToast('⚠️ ไม่สามารถเชื่อมต่อได้', true);
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