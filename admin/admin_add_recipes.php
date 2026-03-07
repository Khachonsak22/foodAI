<?php
session_start();
include '../config/connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$admin_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$admin_stmt->bind_param("i", $user_id);
$admin_stmt->execute();
$admin_data = $admin_stmt->get_result()->fetch_assoc();

if (!str_ends_with($admin_data['email'], '@admin.com')) {
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
   HANDLE INSERT ACTION
   ══════════════════════════════════════════════════════════════════ */
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $instructions = trim($_POST['instructions']);
    $calories = (int)$_POST['calories'];
    
    // จัดการการอัปโหลดไฟล์รูปภาพ
    $image = '';
    if (!empty($_FILES['image']['name'])) {
        $image = time() . '_' . basename($_FILES['image']['name']);
        $target = '../public/uploads/' . $image;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $error_msg = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ";
        }
    }

    if (empty($error_msg)) {
        // บันทึกข้อมูลสูตรอาหารลงตาราง recipes ก่อน
        $stmt = $conn->prepare("INSERT INTO recipes (title, description, instructions, calories, image) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssis", $title, $description, $instructions, $calories, $image);
        
        if ($stmt->execute()) {
            $new_recipe_id = $conn->insert_id; // ไอดีของเมนูอาหารที่เพิ่งเพิ่มใหม่

            // บันทึกข้อมูลวัตถุดิบลงตาราง recipe_ingredients
            if (isset($_POST['ingredient_id']) && is_array($_POST['ingredient_id'])) {
                $ing_stmt = $conn->prepare("INSERT INTO recipe_ingredients (recipe_id, ingredient_id, amount, unit) VALUES (?, ?, ?, ?)");
                
                for ($i = 0; $i < count($_POST['ingredient_id']); $i++) {
                    $ing_id = (int)$_POST['ingredient_id'][$i];
                    $amount = (float)$_POST['amount'][$i];
                    $unit = trim($_POST['unit'][$i]);

                    if ($ing_id > 0 && $amount > 0) {
                        $ing_stmt->bind_param("iids", $new_recipe_id, $ing_id, $amount, $unit);
                        $ing_stmt->execute();
                    }
                }
            }
            
            // ─── บันทึกข้อมูล Tag ลงตาราง recipe_tags ───
            if (isset($_POST['tags']) && is_array($_POST['tags'])) {
                $tag_stmt = $conn->prepare("INSERT INTO recipe_tags (recipe_id, tag_id) VALUES (?, ?)");
                foreach ($_POST['tags'] as $t_id) {
                    $t_id = (int)$t_id;
                    $tag_stmt->bind_param("ii", $new_recipe_id, $t_id);
                    $tag_stmt->execute();
                }
            }

            // เมื่อสำเร็จให้ส่งกลับไปหน้าสูตรอาหาร
            echo "<script>alert('เพิ่มสูตรอาหาร วัตถุดิบ และแท็กสำเร็จ!'); window.location.href='admin_recipes.php';</script>";
            exit();
        } else {
            $error_msg = "เกิดข้อผิดพลาดในการบันทึกข้อมูลลงฐานข้อมูล";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เพิ่มสูตรอาหาร — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* CSS โครงสร้างหลัก */
:root{--g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;--g400:#4ade80;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--bg:#f5f8f5;--card:#fff;--bdr:#e8f0e9;--txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;--sb-w:260px;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,#c8e6c9 1px,transparent 1px);background-size:28px 28px;opacity:.35;}
.page-wrap{margin-left:var(--sb-w);flex:1;position:relative;z-index:1;min-width:0;}
.topbar{height:66px;background:rgba(255,255,255,.95);backdrop-filter:blur(12px);border-bottom:1px solid #e5ede6;display:flex;align-items:center;padding:0 2.5rem;gap:14px;position:sticky;top:0;z-index:50;}
.card{background:#fff;border:1px solid var(--bdr);border-radius:18px;padding:30px;max-width:800px;margin:0 auto;}

/* Form CSS */
.form-group{margin-bottom:20px;}
.form-label{display:block;font-size:.85rem;font-weight:600;color:var(--sub);margin-bottom:8px;}
.form-input{width:100%;padding:12px 16px;border:1.5px solid var(--bdr);border-radius:12px;font-family:'Kanit',sans-serif;font-size:.9rem;outline:none;transition:border-color .2s;background:var(--g50);}
.form-input:focus{border-color:var(--g400);box-shadow:0 0 0 3px rgba(74,222,128,.12);}
.btn{padding:12px 20px;border-radius:12px;font-size:.9rem;font-weight:600;cursor:pointer;transition:all .18s;display:inline-flex;align-items:center;justify-content:center;gap:8px;border:none;text-decoration:none;font-family:'Kanit',sans-serif;}
.btn-green{background:linear-gradient(135deg,var(--g500),#14b8a6);color:#fff;box-shadow:0 4px 12px rgba(34,197,94,.2);width:100%;}
.btn-green:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(34,197,94,.3);color:#fff;}
.btn-back{background:#fff;border:1.5px solid var(--bdr);color:var(--sub);padding:8px 16px;border-radius:10px;font-size:.8rem;cursor:pointer;transition:all .2s;}
.btn-back:hover{background:var(--g50);border-color:var(--g200);}

/* Tags CSS */
.tag-checkbox-group { display: flex; flex-wrap: wrap; gap: 8px; }
.tag-label { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; cursor: pointer; border: 1px solid var(--bdr); transition: all 0.2s; }
.tag-label:hover { background: var(--g50); }
.tag-label.disease { background: #ecfdf5; border-color: #a7f3d0; color: #059669; }
.tag-label.allergy { background: #eff6ff; border-color: #bae6fd; color: #0284c7; }

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
</style>
</head>
<body>

<?php include '../includes/sidebar_admin.php' ?>

<div class="page-wrap">
  <header class="topbar">
    <button class="hamburger"><i class="fas fa-bars"></i></button>
    <div style="flex:1; display:flex; align-items:center; gap:15px;">
      <a href="admin_recipes.php" class="btn-back"><i class="fas fa-arrow-left"></i> กลับ</a>
      <div>
        <div style="font-family:'Nunito',sans-serif;font-size:1.1rem;font-weight:800;color:var(--txt);">เพิ่มสูตรอาหารใหม่</div>
        <div style="font-size:.72rem;color:var(--muted);">กรอกข้อมูลเพื่อสร้างเมนูใหม่ในระบบ</div>
      </div>
    </div>
  </header>
  
  <main style="padding:2.5rem;">
    <?php if ($error_msg): ?>
    <div style="background:#fef2f2;border:1.5px solid #fecaca;color:#dc2626;padding:12px 16px;border-radius:12px;margin-bottom:20px;font-size:.82rem;max-width:800px;margin-left:auto;margin-right:auto;">
      ❌ <?= htmlspecialchars($error_msg) ?>
    </div>
    <?php endif; ?>

    <div class="card">
      <form method="POST" enctype="multipart/form-data">
        
        <div class="form-group">
          <label class="form-label">ชื่อเมนูอาหาร <span style="color:red;">*</span></label>
          <input type="text" name="title" required placeholder="เช่น อกไก่ผัดพริกไทยดำ" class="form-input">
        </div>
        
        <div class="form-group">
          <label class="form-label">คำอธิบายแบบย่อ</label>
          <textarea name="description" rows="2" placeholder="จุดเด่นของเมนูนี้..." class="form-input"></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">วิธีทำ / ขั้นตอนการทำ <span style="color:red;">*</span></label>
          <textarea name="instructions" rows="6" required placeholder="1. นำอกไก่ไปหมัก...&#10;2. ตั้งกระทะ..." class="form-input"></textarea>
        </div>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
          <div class="form-group">
            <label class="form-label">ปริมาณแคลอรี่รวม (kcal) <span style="color:red;">*</span></label>
            <input type="number" name="calories" required placeholder="เช่น 350" class="form-input">
          </div>
          <div class="form-group">
            <label class="form-label">รูปภาพเมนูอาหาร</label>
            <input type="file" name="image" accept="image/*" class="form-input" style="padding:9px 14px; background:#fff;">
          </div>
        </div>

        <div class="form-group" style="margin-top:10px; border-top:1px solid var(--bdr); padding-top:20px;">
          <label class="form-label" style="font-size:1rem; color:var(--g700);"><i class="fas fa-tags"></i> แท็กคุณสมบัติของเมนู</label>
          <p style="font-size:.75rem; color:var(--muted); margin-bottom:16px;">เลือกแท็กเพื่อให้ระบบจับคู่และคัดกรองเมนูให้ผู้ใช้อย่างแม่นยำ</p>
          
          <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div style="background: #fff; border: 1px solid #d1fae5; border-radius: 16px; padding: 18px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.05);">
                <div style="font-family:'Nunito', sans-serif; font-weight:800; font-size:1rem; color:#059669; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                    <div style="width:32px; height:32px; border-radius:10px; background:#d1fae5; display:flex; align-items:center; justify-content:center; font-size:1rem;">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    เหมาะสำหรับโรค
                </div>
                <div class="tag-checkbox-group">
                    <?php foreach($tags_list as $t):
                        // ข้ามแท็กที่มีคำว่า 'ไม่มี' ไป เพราะเป็นสารก่อภูมิแพ้
                        if (mb_strpos($t['name'], 'ไม่มี') === 0) continue; 
                    ?>
                      <label class="tag-label disease">
                        <input type="checkbox" name="tags[]" value="<?= $t['id'] ?>" style="accent-color: #059669;">
                        <?= htmlspecialchars($t['name']) ?>
                      </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="background: #fff; border: 1px solid #e0f2fe; border-radius: 16px; padding: 18px; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.05);">
                <div style="font-family:'Nunito', sans-serif; font-weight:800; font-size:1rem; color:#0284c7; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                    <div style="width:32px; height:32px; border-radius:10px; background:#e0f2fe; display:flex; align-items:center; justify-content:center; font-size:1rem;">
                        <i class="fas fa-ban"></i>
                    </div>
                    ปราศจากสิ่งที่แพ้
                </div>
                <div class="tag-checkbox-group">
                    <?php foreach($tags_list as $t):
                        // ดึงมาแสดงเฉพาะแท็กที่ขึ้นต้นด้วย 'ไม่มี'
                        if (mb_strpos($t['name'], 'ไม่มี') !== 0) continue; 
                    ?>
                      <label class="tag-label allergy">
                        <input type="checkbox" name="tags[]" value="<?= $t['id'] ?>" style="accent-color: #0284c7;">
                        <?= htmlspecialchars($t['name']) ?>
                      </label>
                    <?php endforeach; ?>
                </div>
            </div>
          </div>
        </div>

        <div class="form-group" style="margin-top:20px; border-top:1px solid var(--bdr); padding-top:20px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <label class="form-label" style="margin:0; font-size:1rem; color:var(--g700);"><i class="fas fa-carrot"></i> ส่วนผสม / วัตถุดิบ</label>
            <button type="button" onclick="addIngredientRow()" class="btn-back" style="font-size:.75rem; padding:6px 12px; border-color:var(--g400); color:var(--g700);">
              <i class="fas fa-plus"></i> เพิ่มวัตถุดิบ
            </button>
          </div>
          
          <div id="ingredients_container">
            </div>
        </div>
        <div style="margin-top:30px; border-top:1px solid var(--bdr); padding-top:20px;">
          <button type="submit" class="btn btn-green">
            <i class="fas fa-save"></i> บันทึกและเพิ่มสูตรอาหาร
          </button>
        </div>

      </form>
    </div>
  </main>
</div>

<script>
// รับข้อมูลวัตถุดิบทั้งหมดจาก PHP มาไว้ในตัวแปร JS
const ingredientsData = <?php echo json_encode($ingredients_list, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function addIngredientRow() {
    const container = document.getElementById('ingredients_container');
    const row = document.createElement('div');
    row.style.display = 'flex';
    row.style.gap = '10px';
    row.style.marginBottom = '12px';
    row.style.alignItems = 'center';

    // สร้าง Select Dropdown
    let selectHTML = '<select name="ingredient_id[]" class="form-input" style="flex:2; padding:10px 14px;" required>';
    selectHTML += '<option value="">-- เลือกวัตถุดิบ --</option>';
    ingredientsData.forEach(ing => {
        selectHTML += `<option value="${ing.id}">${ing.name}</option>`;
    });
    selectHTML += '</select>';

    // สร้าง Input สำหรับ Amount และ Unit
    row.innerHTML = `
        ${selectHTML}
        <input type="number" step="0.01" name="amount[]" placeholder="ปริมาณ" required class="form-input" style="flex:1; padding:10px 14px;">
        <input type="text" name="unit[]" placeholder="หน่วย (เช่น กรัม, ฟอง)" required class="form-input" style="flex:1; padding:10px 14px;">
        <button type="button" onclick="this.parentElement.remove()" style="background:#fee2e2; color:#dc2626; border:1px solid #fecaca; width:44px; height:44px; border-radius:12px; cursor:pointer; flex-shrink:0; transition:all .2s;">
            <i class="fas fa-trash"></i>
        </button>
    `;
    
    container.appendChild(row);
}

// เพิ่มแถวเริ่มต้น 1 แถวตอนโหลดหน้าเว็บ
document.addEventListener('DOMContentLoaded', function() {
    addIngredientRow();
});
</script>

</body>
</html>