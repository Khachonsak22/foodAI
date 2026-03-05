<?php
session_start();
$db_path = '../config/connect.php';
if (file_exists($db_path)) { include $db_path; }
else { $conn = mysqli_connect("localhost","root","","myfood"); }
if (!$conn) die("Database Connection Failed");

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 3;

$u_stmt = $conn->prepare("SELECT username, first_name, last_name FROM users WHERE id = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$userData  = $u_stmt->get_result()->fetch_assoc();
$firstName = $userData['first_name'] ?? ($userData['username'] ?? 'User');
$lastName  = $userData['last_name']  ?? '';
$initials  = mb_strtoupper(mb_substr($firstName,0,1)).mb_strtoupper(mb_substr($lastName,0,1));

$recipe_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($recipe_id <= 0) { header("Location: dashboard.php"); exit(); }

$success_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);
    
    if ($rating >= 1 && $rating <= 5) {
        $stmt = $conn->prepare("INSERT INTO recipe_reviews (recipe_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiis", $recipe_id, $user_id, $rating, $comment);
        if ($stmt->execute()) {
            $success_msg = "ส่งรีวิวเรียบร้อย";
        }
    }
}

// ── ดึงข้อมูลเมนู ──
$stmt = $conn->prepare("SELECT * FROM recipes WHERE id = ?");
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$recipe = $stmt->get_result()->fetch_assoc();
if (!$recipe) die("ไม่พบสูตรอาหารที่ต้องการ");

// ── ดึงข้อมูล Tags เฉพาะของเมนูนี้ เพื่อแสดงสีและไอคอน ──
$stmt_tags = $conn->prepare("SELECT t.* FROM recipe_tags rt JOIN tags t ON rt.tag_id = t.id WHERE rt.recipe_id = ?");
$stmt_tags->bind_param("i", $recipe_id);
$stmt_tags->execute();
$recipe_tags = $stmt_tags->get_result()->fetch_all(MYSQLI_ASSOC);

// ── ดึงวัตถุดิบ ──
$stmt_ing = $conn->prepare("SELECT i.name, ri.amount, ri.unit FROM recipe_ingredients ri JOIN ingredients i ON ri.ingredient_id = i.id WHERE ri.recipe_id = ?");
$stmt_ing->bind_param("i", $recipe_id);
$stmt_ing->execute();
$ingredients = $stmt_ing->get_result();

$reviews_sql = "SELECT rr.*, u.first_name, u.last_name FROM recipe_reviews rr JOIN users u ON rr.user_id = u.id WHERE rr.recipe_id = ? ORDER BY rr.created_at DESC";
$reviews_stmt = $conn->prepare($reviews_sql);
$reviews_stmt->bind_param("i", $recipe_id);
$reviews_stmt->execute();
$reviews = $reviews_stmt->get_result();

$avg_sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM recipe_reviews WHERE recipe_id = ?";
$avg_stmt = $conn->prepare($avg_sql);
$avg_stmt->bind_param("i", $recipe_id);
$avg_stmt->execute();
$rating_data = $avg_stmt->get_result()->fetch_assoc();
$avg_rating = round($rating_data['avg_rating'] ?? 0, 1);
$total_reviews = $rating_data['total_reviews'] ?? 0;

$conn->query("UPDATE recipes SET view_count = COALESCE(view_count,0)+1 WHERE id=$recipe_id");
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($recipe['title']) ?> — FoodAI</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--g50:#f0fdf4;--g400:#4ade80;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--t400:#2dd4bf;--t500:#14b8a6;--bg:#f5f8f5;--card:#fff;--bdr:#e8f0e9;--txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;--sb-w:248px;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);display:flex;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,#c8e6c9 1px,transparent 1px);background-size:28px 28px;opacity:.35;}
.page-wrap{margin-left:var(--sb-w);flex:1;position:relative;z-index:1;}
main{padding:2rem 2.5rem 3.5rem;width:100%;max-width:1400px;margin:0 auto;}
.hero-img{width:100%;height:380px;border-radius:20px;object-fit:cover;box-shadow:0 12px 40px rgba(34,197,94,.15);}
.card{background:var(--card);border:1px solid var(--bdr);border-radius:20px;padding:28px;}
.badge{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:10px;font-size:.75rem;font-weight:600;}
.badge-green{background:var(--g50);color:var(--g700);border:1px solid var(--g200);}
.badge-orange{background:#fff7ed;color:#ea580c;border:1px solid:#fed7aa;}

/* ── Style สำหรับ Tags ── */
.r-tags-wrap { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:18px; }
.r-tag { font-size:.75rem; font-weight:600; padding:4px 12px; border-radius:8px; display:inline-flex; align-items:center; gap:6px; }

.section-title{font-family:'Nunito',sans-serif;font-size:1.15rem;font-weight:800;color:var(--txt);margin-bottom:16px;display:flex;align-items:center;gap:10px;}
.section-title i{color:var(--g500);font-size:1.1rem;}
.ingredient-item{display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--g50);border-radius:10px;font-size:.82rem;}
.ingredient-item i{color:var(--g500);font-size:.8rem;}
.step-item{display:flex;gap:16px;padding:16px;background:var(--g50);border-radius:14px;border-left:4px solid var(--g500);margin-bottom:10px;}
.step-number{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;}
.btn{padding:12px 24px;border-radius:12px;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:8px;border:none;text-decoration:none;}
.btn-green{background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;box-shadow:0 4px 14px rgba(34,197,94,.25);}
.btn-green:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(34,197,94,.35);}
.star-rating{display:flex;gap:4px;font-size:1.1rem;}
.star-rating i{color:#fbbf24;cursor:pointer;transition:.2s;}
.star-rating i.far{color:#d1d5db;}
.star-rating i:hover{transform:scale(1.15);}
.review-card{background:var(--g50);border:1px solid var(--g200);border-radius:14px;padding:18px;margin-bottom:14px;}
.review-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;}
.review-user{display:flex;align-items:center;gap:10px;}
.review-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--t400));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;}
.review-stars{display:flex;gap:2px;font-size:.9rem;}
.review-stars i{color:#fbbf24;}
.review-comment{font-size:.82rem;color:var(--sub);line-height:1.6;}
.review-date{font-size:.7rem;color:var(--muted);margin-top:8px;}
</style>
</head>
<body>

<?php include '../includes/sidebar.php' ?>

<div class="page-wrap">
  <main>
    <a href="recipes.php" style="display:inline-flex;align-items:center;gap:8px;color:var(--g600);font-size:.82rem;font-weight:600;text-decoration:none;margin-bottom:20px;">
      <i class="fas fa-arrow-left"></i> กลับไปหน้าสูตรอาหาร
    </a>

    <?php if ($success_msg): ?>
    <div style="background:#f0fdf4;border:1.5px solid var(--g300);color:var(--g700);padding:12px 16px;border-radius:12px;margin-bottom:20px;font-size:.82rem;">✅ <?= $success_msg ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:24px;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:center;">
        <div>
          <?php $img_path = '../public/uploads/' . $recipe['image']; if (!empty($recipe['image']) && file_exists($img_path)): ?>
            <img src="<?= $img_path ?>" class="hero-img">
          <?php else: ?>
            <div class="hero-img" style="background:var(--g50);display:flex;align-items:center;justify-content:center;font-size:4rem;opacity:.3;"><i class="fas fa-utensils"></i></div>
          <?php endif; ?>
        </div>

        <div>
          <h1 style="font-family:'Nunito',sans-serif;font-size:2rem;font-weight:800;color:var(--txt);line-height:1.2;margin-bottom:12px;">
            <?= htmlspecialchars($recipe['title']) ?>
          </h1>

          <?php if (!empty($recipe_tags)): ?>
          <div class="r-tags-wrap">
              <?php foreach($recipe_tags as $t): 
                  $is_allergy = ($t['type'] === 'allergen');
              ?>
              <span class="r-tag" style="background:<?= $is_allergy ? '#fef2f2' : '#ecfdf5' ?>; color:<?= $is_allergy ? '#dc2626' : '#059669' ?>; border:1px solid <?= $is_allergy ? '#fecaca' : '#a7f3d0' ?>;">
                  <?= htmlspecialchars($t['name']) ?>
              </span>
              <?php endforeach; ?>
          </div>
          <?php endif; ?>
          
          <p style="font-size:.88rem;color:var(--sub);line-height:1.7;margin-bottom:18px;">
            <?= htmlspecialchars($recipe['description'] ?: 'เมนูอาหารเพื่อสุขภาพ') ?>
          </p>

          <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;">
            <div class="star-rating">
              <?php for($i=1; $i<=5; $i++): ?>
                <i class="<?= $i <= $avg_rating ? 'fas' : 'far' ?> fa-star"></i>
              <?php endfor; ?>
            </div>
            <span style="font-weight:600;color:var(--txt);"><?= $avg_rating ?></span>
            <span style="color:var(--muted);font-size:.8rem;">(<?= $total_reviews ?> รีวิว)</span>
          </div>

          <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
            <span class="badge badge-green"><i class="fas fa-fire"></i> <?= $recipe['calories'] ?> kcal</span>
            <?php if ($recipe['servings']): ?>
            <span class="badge badge-orange"><i class="fas fa-utensils"></i> <?= $recipe['servings'] ?> คน</span>
            <?php endif; ?>
            <span class="badge" style="background:#fff;border:1px solid var(--bdr);color:var(--sub);"><i class="fas fa-eye"></i> <?= number_format($recipe['view_count'] ?? 0) ?> ครั้ง</span>
          </div>

          <div style="display:flex;gap:10px;">
            <button class="btn btn-green"><i class="fas fa-bookmark"></i> บันทึกเมนู</button>
            <button class="btn" style="background:var(--g50);color:var(--g600);border:1px solid var(--g200);">
              <i class="fas fa-share-nodes"></i> แชร์
            </button>
          </div>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
      <div class="card">
        <h2 class="section-title"><i class="fas fa-carrot" style="color:var(--g500);"></i> วัตถุดิบ</h2>
        <div style="display:flex;flex-direction:column;gap:10px;">
          <?php if ($ingredients->num_rows > 0): ?>
            <?php while($ing = $ingredients->fetch_assoc()): ?>
            <div class="ingredient-item">
              <i class="fas fa-check-circle"></i>
              <span style="flex:1;font-weight:500;"><?= htmlspecialchars($ing['name']) ?></span>
              <span style="color:var(--g600);font-weight:600;"><?= htmlspecialchars($ing['amount']) ?> <?= htmlspecialchars($ing['unit']) ?></span>
            </div>
            <?php endwhile; ?>
          <?php else: ?>
            <p style="color:var(--muted);font-size:.82rem;">ไม่มีข้อมูลวัตถุดิบ</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <h2 class="section-title"><i class="fas fa-chart-pie"></i> โภชนาการ</h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
          <div style="padding:16px;background:var(--g50);border-radius:12px;text-align:center;">
            <div style="font-size:1.5rem;font-weight:800;color:var(--g600);font-family:'Nunito',sans-serif;"><?= $recipe['calories'] ?></div>
            <div style="font-size:.75rem;color:var(--muted);margin-top:4px;">แคลอรี่ (kcal)</div>
          </div>
          <div style="padding:16px;background:var(--g50);border-radius:12px;text-align:center;">
            <div style="font-size:1.5rem;font-weight:800;color:var(--g600);font-family:'Nunito',sans-serif;"><?= $recipe['servings'] ?: 1 ?></div>
            <div style="font-size:.75rem;color:var(--muted);margin-top:4px;">จำนวนที่เสิร์ฟ</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
      <h2 class="section-title"><i class="fas fa-list-ol" style="color:var(--g500);"></i> วิธีทำ</h2>
      <div>
        <?php
        $steps = explode("\n", $recipe['instructions']);
        $step_num = 1;
        foreach($steps as $step):
          $step = trim(preg_replace('/^\d+[\.\)]\s*/', '', $step));
          if(empty($step)) continue;
        ?>
        <div class="step-item">
          <div class="step-number"><?= $step_num++ ?></div>
          <div style="flex:1;font-size:.85rem;line-height:1.7;color:var(--sub);"><?= htmlspecialchars($step) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <h2 class="section-title" style="margin-bottom:24px;"><i class="fas fa-comments"></i> รีวิวและคะแนน (<?= $total_reviews ?>)</h2>

      <div style="background:var(--g50);border:1.5px dashed var(--g300);border-radius:14px;padding:24px;margin-bottom:28px;">
        <h3 style="font-size:.95rem;font-weight:700;color:var(--txt);margin-bottom:16px;">เขียนรีวิวของคุณ</h3>
        <form method="POST">
          <input type="hidden" name="submit_review" value="1">
          
          <div style="margin-bottom:16px;">
            <label style="display:block;font-size:.8rem;font-weight:600;color:var(--sub);margin-bottom:8px;">ให้คะแนน</label>
            <div class="star-rating" id="ratingInput">
              <i class="far fa-star" data-rating="1"></i>
              <i class="far fa-star" data-rating="2"></i>
              <i class="far fa-star" data-rating="3"></i>
              <i class="far fa-star" data-rating="4"></i>
              <i class="far fa-star" data-rating="5"></i>
            </div>
            <input type="hidden" name="rating" id="ratingValue" value="5">
          </div>

          <div style="margin-bottom:18px;">
            <label style="display:block;font-size:.8rem;font-weight:600;color:var(--sub);margin-bottom:8px;">ความคิดเห็น</label>
            <textarea name="comment" rows="4" placeholder="แชร์ประสบการณ์การทำเมนูนี้..." style="width:100%;padding:12px 16px;border:1.5px solid var(--bdr);border-radius:10px;font-family:'Kanit',sans-serif;font-size:.82rem;"></textarea>
          </div>

          <button type="submit" class="btn btn-green"><i class="fas fa-paper-plane"></i> ส่งรีวิว</button>
        </form>
      </div>

      <?php if ($reviews->num_rows > 0): ?>
        <?php while($review = $reviews->fetch_assoc()): ?>
        <div class="review-card">
          <div class="review-header">
            <div class="review-user">
              <div class="review-avatar"><?= mb_strtoupper(mb_substr($review['first_name'], 0, 1)) ?></div>
              <div>
                <div style="font-weight:600;font-size:.85rem;color:var(--txt);">
                  <?= htmlspecialchars($review['first_name'] . ' ' . $review['last_name']) ?>
                </div>
                <div class="review-stars">
                  <?php for($i=1; $i<=5; $i++): ?>
                    <i class="<?= $i <= $review['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                  <?php endfor; ?>
                </div>
              </div>
            </div>
          </div>
          <p class="review-comment"><?= htmlspecialchars($review['comment']) ?></p>
          <p class="review-date"><i class="far fa-clock"></i> <?= date('j M Y, H:i', strtotime($review['created_at'])) ?> น.</p>
        </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div style="text-align:center;padding:40px;color:var(--muted);">
          <i class="fas fa-comment-slash" style="font-size:2.5rem;opacity:.3;margin-bottom:12px;display:block;"></i>
          <p style="font-size:.85rem;">ยังไม่มีรีวิว เป็นคนแรกที่รีวิวเมนูนี้!</p>
        </div>
      <?php endif; ?>
    </div>

  </main>
</div>

<script>
const stars = document.querySelectorAll('#ratingInput i');
const ratingValue = document.getElementById('ratingValue');

stars.forEach(star => {
  star.addEventListener('click', function() {
    const rating = this.getAttribute('data-rating');
    ratingValue.value = rating;
    
    stars.forEach((s, index) => {
      if (index < rating) {
        s.classList.remove('far');
        s.classList.add('fas');
      } else {
        s.classList.remove('fas');
        s.classList.add('far');
      }
    });
  });
  
  star.addEventListener('mouseenter', function() {
    const rating = this.getAttribute('data-rating');
    stars.forEach((s, index) => {
      if (index < rating) {
        s.style.color = '#fbbf24';
      }
    });
  });
});

document.getElementById('ratingInput').addEventListener('mouseleave', function() {
  const currentRating = ratingValue.value;
  stars.forEach((s, index) => {
    s.style.color = index < currentRating ? '#fbbf24' : '#d1d5db';
  });
});

stars.forEach(s => {
  s.classList.remove('far');
  s.classList.add('fas');
});
</script>

</body>
</html>