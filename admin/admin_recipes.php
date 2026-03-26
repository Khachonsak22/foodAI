<?php
session_start();
include '../config/connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$admin_stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$admin_stmt->bind_param("i", $user_id);
$admin_stmt->execute();
$admin_data = $admin_stmt->get_result()->fetch_assoc();

if ($admin_data['role'] != 1) {
    header("Location: ../pages/dashboard.php");
    exit();
}

// ─── ดึงข้อมูลวัตถุดิบทั้งหมดสำหรับแสดงใน Dropdown ───
$ing_sql = "SELECT id, name FROM ingredients ORDER BY name ASC";
$ingredients_list = $conn->query($ing_sql)->fetch_all(MYSQLI_ASSOC);

// ─── ดึงข้อมูล Tag ทั้งหมด ───
$tag_sql = "SELECT id, name FROM tags ORDER BY id ASC";
$tags_list = $conn->query($tag_sql)->fetch_all(MYSQLI_ASSOC);

/* ══════════════════════════════════════════════════════════════════
   HANDLE ACTIONS
   ══════════════════════════════════════════════════════════════════ */
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ลบสูตรอาหาร (วัตถุดิบที่ผูกไว้จะถูกลบอัตโนมัติจาก ON DELETE CASCADE)
    if ($action === 'delete_recipe') {
        $recipe_id = (int)$_POST['recipe_id'];
        $stmt = $conn->prepare("DELETE FROM recipes WHERE id = ?");
        $stmt->bind_param("i", $recipe_id);
        
        if ($stmt->execute()) {
            $success_msg = "ลบสูตรอาหารเรียบร้อย";
        } else {
            $error_msg = "เกิดข้อผิดพลาดในการลบ";
        }
    }
    
    // แก้ไขสูตรอาหาร
    if ($action === 'edit_recipe') {
        $id = (int)$_POST['id'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $instructions = trim($_POST['instructions']);
        $calories = (int)$_POST['calories'];
        
        // จัดการอัปโหลดรูปภาพใหม่ (ถ้ามี)
        if (!empty($_FILES['image']['name'])) {
            $image = time() . '_' . $_FILES['image']['name'];
            $target = '../public/uploads/' . $image;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                $stmt = $conn->prepare("UPDATE recipes SET title=?, description=?, instructions=?, calories=?, image=? WHERE id=?");
                $stmt->bind_param("sssisi", $title, $description, $instructions, $calories, $image, $id);
            }
        } else {
            $stmt = $conn->prepare("UPDATE recipes SET title=?, description=?, instructions=?, calories=? WHERE id=?");
            $stmt->bind_param("sssii", $title, $description, $instructions, $calories, $id);
        }
        
        if (isset($stmt) && $stmt->execute()) {
            // ─── อัปเดตข้อมูลวัตถุดิบ ───
            $del_ing_stmt = $conn->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?");
            $del_ing_stmt->bind_param("i", $id);
            $del_ing_stmt->execute();

            if (isset($_POST['ingredient_id']) && is_array($_POST['ingredient_id'])) {
                $ing_stmt = $conn->prepare("INSERT INTO recipe_ingredients (recipe_id, ingredient_id, amount, unit) VALUES (?, ?, ?, ?)");
                for ($i = 0; $i < count($_POST['ingredient_id']); $i++) {
                    $ing_id = (int)$_POST['ingredient_id'][$i];
                    $amount = (float)$_POST['amount'][$i];
                    $unit = trim($_POST['unit'][$i]);

                    if ($ing_id > 0 && $amount > 0) {
                        $ing_stmt->bind_param("iids", $id, $ing_id, $amount, $unit);
                        $ing_stmt->execute();
                    }
                }
            }
            
            // ─── อัปเดตข้อมูล Tag ───
            $del_tag_stmt = $conn->prepare("DELETE FROM recipe_tags WHERE recipe_id = ?");
            $del_tag_stmt->bind_param("i", $id);
            $del_tag_stmt->execute();
            
            if (isset($_POST['tags']) && is_array($_POST['tags'])) {
                $tag_stmt = $conn->prepare("INSERT INTO recipe_tags (recipe_id, tag_id) VALUES (?, ?)");
                foreach ($_POST['tags'] as $t_id) {
                    $t_id = (int)$t_id;
                    $tag_stmt->bind_param("ii", $id, $t_id);
                    $tag_stmt->execute();
                }
            }
            
            $success_msg = "แก้ไขสูตรอาหารและวัตถุดิบเรียบร้อย";
        } else {
            $error_msg = "เกิดข้อผิดพลาดในการแก้ไขข้อมูล";
        }
    }
}

// Fetch all recipes
$recipes_sql = "
    SELECT r.id, r.title, r.description, r.instructions, r.calories, r.image, r.created_at,
           (SELECT COUNT(*) FROM meal_logs WHERE recipe_id = r.id) as log_count,
           DATEDIFF(NOW(), r.created_at) as days_old
    FROM recipes r
    ORDER BY r.created_at DESC
";
$recipes = $conn->query($recipes_sql)->fetch_all(MYSQLI_ASSOC);

// ─── ดึงข้อมูลวัตถุดิบของแต่ละเมนู ───
$ri_sql = "SELECT recipe_id, ingredient_id, amount, unit FROM recipe_ingredients";
$ri_result = $conn->query($ri_sql);
$recipe_ingredients_map = [];
while ($row = $ri_result->fetch_assoc()) {
    $recipe_ingredients_map[$row['recipe_id']][] = $row;
}

// ─── ดึงข้อมูล Tag ของแต่ละเมนู ───
$rt_sql = "SELECT recipe_id, tag_id FROM recipe_tags";
$rt_result = $conn->query($rt_sql);
$recipe_tags_map = [];
while ($row = $rt_result->fetch_assoc()) {
    $recipe_tags_map[$row['recipe_id']][] = $row['tag_id'];
}

foreach ($recipes as &$r) {
    // แนบข้อมูลวัตถุดิบและ Tag เข้าไปใน array ของสูตรอาหาร
    $r['ingredients'] = $recipe_ingredients_map[$r['id']] ?? [];
    $r['tags'] = $recipe_tags_map[$r['id']] ?? [];
}
unset($r);

$total = count($recipes);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการสูตรอาหาร — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<style>
/* CSS แบบเดิมของคุณทั้งหมด */
:root{--g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;--g400:#4ade80;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--bg:#f5f8f5;--card:#fff;--bdr:#e8f0e9;--txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;--sb-w:260px;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,#c8e6c9 1px,transparent 1px);background-size:28px 28px;opacity:.35;}
.page-wrap{margin-left:var(--sb-w);flex:1;position:relative;z-index:1;min-width:0;}
.topbar{height:66px;background:rgba(255,255,255,.95);backdrop-filter:blur(12px);border-bottom:1px solid #e5ede6;display:flex;align-items:center;padding:0 2.5rem;gap:14px;position:sticky;top:0;z-index:50;}
.card{background:#fff;border:1px solid var(--bdr);border-radius:18px;padding:22px;}
.table{width:100%;font-size:.8rem;}
.table th{text-align:left;padding:12px 10px;background:var(--g50);color:var(--sub);font-weight:600;border-bottom:2px solid var(--g200);font-size:.75rem;}
.table td{padding:12px 10px;border-bottom:1px solid var(--bdr);}
.btn{padding:8px 16px;border-radius:10px;font-size:.78rem;font-weight:600;cursor:pointer;transition:all .18s;display:inline-flex;align-items:center;gap:6px;border:none;text-decoration:none;}
.btn-sm{padding:6px 12px;font-size:.72rem;}
.btn-green{background:linear-gradient(135deg,var(--g500),#14b8a6);color:#fff;box-shadow:0 4px 12px rgba(34,197,94,.2);}
.btn-green:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(34,197,94,.3);color:#fff;}
.badge{font-size:.68rem;font-weight:700;padding:4px 10px;border-radius:8px;display:inline-block;}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;opacity:0;transition:opacity .2s;}
.modal.open{display:flex;opacity:1;}
.modal-content{background:#fff;border-radius:20px;padding:28px;max-width:700px;width:90%;transform:translateY(20px);transition:transform .3s;max-height:90vh;overflow-y:auto;}
.modal.open .modal-content{transform:translateY(0);}
.form-group{margin-bottom:16px;}
.form-label{display:block;font-size:.78rem;font-weight:600;color:var(--sub);margin-bottom:6px;}
.form-input{width:100%;padding:10px 14px;border:1.5px solid var(--bdr);border-radius:10px;font-family:'Kanit',sans-serif;font-size:.82rem;outline:none;transition:border-color .2s;}
.form-input:focus{border-color:var(--g400);}
.dataTables_wrapper{font-family:'Kanit',sans-serif!important;}
.dataTables_wrapper .dataTables_filter input{border:1.5px solid var(--bdr);border-radius:8px;padding:6px 12px;font-size:.8rem;}
.dataTables_wrapper .dataTables_info{font-size:.75rem;color:var(--muted);}
.dataTables_wrapper .dataTables_paginate .paginate_button{border:1px solid var(--bdr);border-radius:8px;padding:4px 12px;font-size:.75rem;}
.dataTables_wrapper .dataTables_paginate .paginate_button.current{background:var(--g500)!important;color:#fff!important;}
.dt-buttons{margin-bottom:12px;}
.dt-button{background:var(--g500)!important;color:#fff!important;border:none!important;padding:8px 16px!important;border-radius:8px!important;}

/* Tags CSS */
.tag-checkbox-group { display: flex; flex-wrap: wrap; gap: 6px; }
.tag-label { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; cursor: pointer; border: 1px solid var(--bdr); transition: all 0.2s; }
.tag-label:hover { background: var(--g50); }
.tag-label.disease { background: #ecfdf5; border-color: #a7f3d0; color: #059669; }
.tag-label.allergy { background: #eff6ff; border-color: #bae6fd; color: #0284c7; }
::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-thumb{background:var(--g200);border-radius:99px;}

/*CSS สำหรับปุ่ม Hamburger และ Responsive บนมือถือ*/
.hamburger {
  display: none;
  width: 40px; height: 40px; border-radius: 10px;
  background: #fff; border: 1px solid var(--bdr);
  align-items: center; justify-content: center;
  cursor: pointer; color: var(--txt); font-size: 1.1rem;
  transition: all .2s; margin-right: 14px; flex-shrink: 0;
}
.hamburger:hover { background: var(--g50); color: var(--g600); border-color: var(--g300); }

@media (max-width: 1024px) {
  .hamburger { display: flex; }
  .page-wrap { margin-left: 0; }
  .topbar { padding: 0 1.25rem; }
  main { padding: 1.5rem 1rem !important; }
}

/* ==========================================
   ตกแต่งไลบรารี Select2 ให้เข้ากับธีมเว็บ 
   ========================================== */
.select2-container--default .select2-selection--single {
  height: 42px; /* ให้สูงเท่ากับ input ปกติในหน้านี้ */
  padding: 6px 14px;
  border: 1.5px solid var(--bdr);
  border-radius: 10px;
  font-family: 'Kanit', sans-serif;
  font-size: .82rem;
  background: #fff;
  outline: none;
  display: flex;
  align-items: center;
  transition: all 0.2s;
}
.select2-container--default.select2-container--open .select2-selection--single {
  border-color: var(--g400);
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
  padding-left: 0;
  color: var(--txt);
  line-height: normal;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
  height: 100%;
  right: 12px;
}
.select2-dropdown {
  border: 1.5px solid var(--g400);
  border-radius: 10px;
  box-shadow: 0 10px 25px rgba(34,197,94,0.15);
  font-family: 'Kanit', sans-serif;
  font-size: .82rem;
  overflow: hidden;
  z-index: 99999; /* ให้ลอยอยู่เหนือ Modal */
}
.select2-search--dropdown { padding: 8px; }
.select2-container--default .select2-search--dropdown .select2-search__field {
  border: 1.5px solid var(--bdr);
  border-radius: 8px;
  padding: 6px 10px;
  outline: none;
  font-family: 'Kanit', sans-serif;
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
  background-color: var(--g500);
  color: white;
}
.select2-results__option { padding: 6px 12px; }
</style>
</head>
<body>

<?php include '../includes/sidebar_admin.php' ?>

<div class="page-wrap">
  <header class="topbar">
    <button class="hamburger"><i class="fas fa-bars"></i></button>
    <div style="flex:1;">
      <div style="font-family:'Nunito',sans-serif;font-size:1.1rem;font-weight:800;color:var(--txt);">จัดการสูตรอาหาร</div>
      <div style="font-size:.72rem;color:var(--muted);">ทั้งหมด <?= number_format($total) ?> รายการ</div>
    </div>
    <a href="admin_add_recipes.php" class="btn btn-green">
      <i class="fas fa-plus"></i> เพิ่มเมนูใหม่
    </a>
  </header>
  
  <main style="padding:2rem 2.5rem;">
    <?php if ($success_msg): ?>
    <div style="background:#f0fdf4;border:1.5px solid var(--g300);color:var(--g700);padding:12px 16px;border-radius:12px;margin-bottom:20px;font-size:.82rem;">
      ✅ <?= htmlspecialchars($success_msg) ?>
    </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
    <div style="background:#fef2f2;border:1.5px solid #fecaca;color:#dc2626;padding:12px 16px;border-radius:12px;margin-bottom:20px;font-size:.82rem;">
      ❌ <?= htmlspecialchars($error_msg) ?>
    </div>
    <?php endif; ?>
    
    <div class="card">
      <div style="overflow-x:auto;">
        <table id="recipesTable" class="table" style="width:100%;">
          <thead>
            <tr>
              <th style="width:60px;">ID</th>
              <th>ชื่อเมนู</th>
              <th>แคลอรี่</th>
              <th>บันทึก (ครั้ง)</th>
              <th>วันที่เพิ่ม</th>
              <th style="width:180px;">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recipes as $r): 
              $is_new = (isset($r['days_old']) && $r['days_old'] <= 3);
            ?>
            <tr <?= $is_new ? 'style="background:#fef9c3;border-left:3px solid #fbbf24;"' : '' ?>>
              <td><?= $r['id'] ?></td>
              <td>
                <div style="font-weight:600;color:var(--txt);">
                  <?= htmlspecialchars($r['title']) ?>
                  <?php if ($is_new): ?>
                  <span style="background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#fff;font-size:.6rem;font-weight:800;padding:2px 8px;border-radius:10px;margin-left:6px;text-shadow:0 1px 2px rgba(0,0,0,.15);">NEW</span>
                  <?php endif; ?>
                </div>
                <div style="font-size:.7rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;">
                  <?= htmlspecialchars($r['description'] ?? '-') ?>
                </div>
              </td>
              <td><span style="background:var(--g50);color:var(--g700);padding:3px 8px;border-radius:6px;font-size:.7rem;font-weight:700;"><?= $r['calories'] ?> kcal</span></td>
              <td><span class="badge" style="background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;"><?= $r['log_count'] ?></span></td>
              <td data-order="<?= strtotime($r['created_at']) ?>"><?= date('j M Y', strtotime($r['created_at'])) ?></td>
              <td>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                  <button onclick='openEditModal(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="btn btn-sm" style="background:#fef3c7;color:#ca8a04;border:1px solid #fde68a;flex:1;justify-content:center;">
                    <i class="fas fa-edit"></i> แก้ไข
                  </button>
                  <form method="POST" style="margin:0; display:flex; flex:1;" onsubmit="return confirm('ยืนยันการลบสูตรอาหารนี้?');">
                    <input type="hidden" name="action" value="delete_recipe">
                    <input type="hidden" name="recipe_id" value="<?= $r['id'] ?>">
                    <button type="submit" class="btn btn-sm" style="background:#fee2e2;color:#dc2626;border:1px solid #fecaca;width:100%;justify-content:center;">
                      <i class="fas fa-trash"></i> ลบ
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<div id="editModal" class="modal">
  <div class="modal-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h3 style="font-family:'Nunito',sans-serif;font-size:1.1rem;font-weight:800;color:var(--txt);">แก้ไขสูตรอาหาร</h3>
      <button type="button" onclick="closeEditModal()" style="width:32px;height:32px;border-radius:8px;border:none;background:var(--g50);color:var(--g600);cursor:pointer;"><i class="fas fa-times"></i></button>
    </div>
    
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="edit_recipe">
      <input type="hidden" name="id" id="edit_id">
      
      <div class="form-group">
        <label class="form-label">ชื่อเมนู</label>
        <input type="text" name="title" id="edit_title" required class="form-input">
      </div>
      
      <div class="form-group">
        <label class="form-label">คำอธิบาย</label>
        <textarea name="description" id="edit_desc" rows="2" class="form-input"></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">วิธีทำ (แยกขั้นตอนด้วยการขึ้นบรรทัดใหม่)</label>
        <textarea name="instructions" id="edit_inst" rows="4" class="form-input"></textarea>
      </div>
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="form-group">
          <label class="form-label">แคลอรี่รวม (kcal)</label>
          <input type="number" name="calories" id="edit_cal" required class="form-input">
        </div>
        <div class="form-group">
          <label class="form-label">อัปเดตรูปภาพใหม่ (ไม่บังคับ)</label>
          <input type="file" name="image" accept="image/*" class="form-input" style="padding:7px 14px; background:#fff;">
        </div>
      </div>

      <div class="form-group" style="margin-top:10px; border-top:1px solid var(--bdr); padding-top:20px;">
        <label class="form-label" style="font-size:.9rem; color:var(--g700);"><i class="fas fa-tags"></i> แท็กเมนูอาหาร</label>
        <div class="tag-checkbox-group">
          <?php foreach($tags_list as $t): 
              $is_allergy = (mb_strpos($t['name'], 'ไม่มี') === 0);
          ?>
            <label class="tag-label <?= $is_allergy ? 'allergy' : 'disease' ?>">
              <input type="checkbox" name="tags[]" value="<?= $t['id'] ?>" id="edit_tag_<?= $t['id'] ?>" style="accent-color: <?= $is_allergy ? '#0284c7' : '#059669' ?>;">
              <?= htmlspecialchars($t['name']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="form-group" style="margin-top:10px; border-top:1px solid var(--bdr); padding-top:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
          <label class="form-label" style="margin:0; font-size:1rem; color:var(--g700);"><i class="fas fa-carrot"></i> ส่วนผสม / วัตถุดิบ</label>
          <button type="button" onclick="addEditIngredientRow()" style="background:#fff; border:1.5px solid var(--g400); color:var(--g700); padding:6px 12px; border-radius:10px; font-size:.75rem; font-family:'Kanit',sans-serif; cursor:pointer; transition:all .2s;">
            <i class="fas fa-plus"></i> เพิ่มวัตถุดิบ
          </button>
        </div>
        <div id="edit_ingredients_container">
          </div>
      </div>
      <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;padding:12px;margin-top:20px;"><i class="fas fa-save"></i> บันทึกการแก้ไข</button>
    </form>
  </div>
</div>

<script>
// ข้อมูลวัตถุดิบทั้งหมดจากฐานข้อมูลสำหรับ Dropdown
const ingredientsData = <?php echo json_encode($ingredients_list, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
let rowIdCounter = 0; // สร้างตัวแปรนับสำหรับกำหนด ID ให้ Select2

// ฟังก์ชันสร้าง Row กรอกวัตถุดิบ
function addEditIngredientRow(selectedId = '', amount = '', unit = '') {
    rowIdCounter++;
    const container = document.getElementById('edit_ingredients_container');
    const row = document.createElement('div');
    row.style.display = 'flex';
    row.style.gap = '10px';
    row.style.marginBottom = '12px';
    row.style.alignItems = 'center';

    const selectId = 'edit_ing_select_' + rowIdCounter;

    // สร้าง Select แบบให้ Select2 ควบคุม
    let selectHTML = `<select name="ingredient_id[]" id="${selectId}" required>`;
    selectHTML += '<option value="">-- ค้นหา/เลือกวัตถุดิบ --</option>';
    ingredientsData.forEach(ing => {
        const selected = (ing.id == selectedId) ? 'selected' : '';
        selectHTML += `<option value="${ing.id}" ${selected}>${ing.name}</option>`;
    });
    selectHTML += '</select>';

    row.innerHTML = `
        <div style="flex:2; min-width:180px;">
            ${selectHTML}
        </div>
        <input type="number" step="0.01" name="amount[]" value="${amount}" placeholder="ปริมาณ" required class="form-input" style="flex:1; padding:10px 14px; min-width:80px;">
        <input type="text" name="unit[]" value="${unit}" placeholder="หน่วย" required class="form-input" style="flex:1; padding:10px 14px; min-width:80px;">
        <button type="button" onclick="this.parentElement.remove()" style="background:#fee2e2; color:#dc2626; border:1px solid #fecaca; width:44px; height:44px; border-radius:12px; cursor:pointer; flex-shrink:0; transition:all .2s; display:flex; align-items:center; justify-content:center;">
            <i class="fas fa-trash"></i>
        </button>
    `;
    container.appendChild(row);

    // เปิดใช้งาน Select2 และตั้งให้ dropdownParent เป็น editModal เพื่อไม่ให้ dropdown ทะลุไปอยู่หลัง Popup
    $(`#${selectId}`).select2({
        placeholder: "-- ค้นหา/เลือกวัตถุดิบ --",
        width: '100%',
        dropdownParent: $('#editModal'),
        language: {
            noResults: function() {
                return "ไม่พบวัตถุดิบที่ค้นหา";
            }
        }
    });
}

// ฟังก์ชันเปิด Edit Modal พร้อมโหลดข้อมูลวัตถุดิบและ Tag
function openEditModal(data) {
  document.getElementById('edit_id').value = data.id;
  document.getElementById('edit_title').value = data.title;
  document.getElementById('edit_desc').value = data.description;
  document.getElementById('edit_inst').value = data.instructions;
  document.getElementById('edit_cal').value = data.calories;
  
  // จัดการข้อมูล Tags ให้ติ๊ก Checkbox ตามที่มีอยู่
  document.querySelectorAll('input[name="tags[]"]').forEach(cb => cb.checked = false);
  if (data.tags && data.tags.length > 0) {
      data.tags.forEach(t_id => {
          let cb = document.getElementById('edit_tag_' + t_id);
          if (cb) cb.checked = true;
      });
  }
  
  // ล้างข้อมูลวัตถุดิบเก่าออกก่อน
  const container = document.getElementById('edit_ingredients_container');
  container.innerHTML = '';
  
  // ตรวจสอบและวนลูปแสดงวัตถุดิบที่มีอยู่แล้ว
  if (data.ingredients && data.ingredients.length > 0) {
      data.ingredients.forEach(ing => {
          addEditIngredientRow(ing.ingredient_id, ing.amount, ing.unit);
      });
  } else {
      // ถ้าไม่มีวัตถุดิบเลย ให้แสดงช่องว่าง 1 ช่อง
      addEditIngredientRow();
  }

  document.getElementById('editModal').classList.add('open');
}

function closeEditModal() { 
  document.getElementById('editModal').classList.remove('open'); 
}

// ปิดเมื่อคลิกพื้นที่ด้านนอก
document.addEventListener('click', function(e) {
  const editModal = document.getElementById('editModal');
  if (e.target === editModal) closeEditModal();
});

$(document).ready(function() {
  $("#recipesTable").DataTable({
    pageLength: 25,
    order: [[0, "desc"]],
    language: {
      search: "ค้นหา:",
      lengthMenu: "แสดง _MENU_ รายการ",
      info: "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
      infoEmpty: "ไม่มีข้อมูล",
      paginate: {next: "ถัดไป", previous: "ก่อนหน้า"}
    },
    dom: "Bfrtip",
    buttons: [
      {
        extend: "excel",
        text: '<i class="fas fa-file-excel"></i> Excel',
        exportOptions: { columns: ":not(:last-child)" }
      },
      {
        extend: "csv",
        text: '<i class="fas fa-file-csv"></i> CSV',
        charset: 'utf-8',
        bom: true, // แก้ปัญหาภาษาไทยใน CSV
        exportOptions: { columns: ":not(:last-child)" }
      },
    ]
  });
});
</script>
</body>
</html>