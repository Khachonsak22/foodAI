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

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 1. เพิ่มเป้าหมาย
    if ($action === 'add_goal') {
        $goal_key = trim($_POST['goal_key']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $badge = trim($_POST['badge']);
        
        $stmt = $conn->prepare("INSERT INTO goals (goal_key, title, description, badge) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $goal_key, $title, $description, $badge);
        if ($stmt->execute()) {
            $success_msg = "เพิ่มเป้าหมายใหม่เรียบร้อยแล้ว";
        } else {
            $error_msg = "เกิดข้อผิดพลาด: รหัส (Key) อาจซ้ำกันในระบบ";
        }
    }
    
    // 2. แก้ไขเป้าหมาย
    if ($action === 'edit_goal') {
        $id = (int)$_POST['goal_id'];
        $goal_key = trim($_POST['goal_key']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $badge = trim($_POST['badge']);
        
        $stmt = $conn->prepare("UPDATE goals SET goal_key=?, title=?, description=?, badge=? WHERE id=?");
        $stmt->bind_param("ssssi", $goal_key, $title, $description, $badge, $id);
        if ($stmt->execute()) {
            $success_msg = "อัปเดตข้อมูลเป้าหมายสำเร็จ";
        } else {
            $error_msg = "เกิดข้อผิดพลาดในการอัปเดต";
        }
    }

    // 3. ลบเป้าหมาย (พร้อมเคลียร์ข้อมูลใน health_profiles)
    if ($action === 'delete_goal') {
        $id = (int)$_POST['goal_id'];
        
        // 3.1 ค้นหารหัส (goal_key) ของเป้าหมายที่กำลังจะถูกลบก่อน
        $find_stmt = $conn->prepare("SELECT goal_key FROM goals WHERE id = ?");
        $find_stmt->bind_param("i", $id);
        $find_stmt->execute();
        $target_goal = $find_stmt->get_result()->fetch_assoc();
        
        if ($target_goal) {
            $deleted_key = $target_goal['goal_key'];
            
            // 3.2 สั่งเคลียร์ข้อมูลใน health_profiles
            // (ใครก็ตามที่เคยเลือกเป้าหมายที่โดนลบนี้ จะถูกรีเซ็ตกลับเป็นค่าเริ่มต้นคือ 'lose_normal')
            $clear_stmt = $conn->prepare("UPDATE health_profiles SET goal_preference = 'lose_normal' WHERE goal_preference = ?");
            $clear_stmt->bind_param("s", $deleted_key);
            $clear_stmt->execute();
        }

        // 3.3 ลบเป้าหมายนั้นออกจากตาราง goals อย่างสมบูรณ์
        $stmt = $conn->prepare("DELETE FROM goals WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success_msg = "ลบเป้าหมายและรีเซ็ตข้อมูลของผู้ใช้ที่เกี่ยวข้องเรียบร้อยแล้ว";
        } else {
            $error_msg = "เกิดข้อผิดพลาดในการลบเป้าหมาย";
        }
    }
}

// ดึงข้อมูล
$goals = [];
$res = $conn->query("SELECT * FROM goals ORDER BY id ASC");
if ($res) {
    while($r = $res->fetch_assoc()) {
        $goals[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการเป้าหมายสุขภาพ — Admin</title>

<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
:root{--g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;--g400:#4ade80;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--t400:#2dd4bf;--t500:#14b8a6;--t600:#0d9488;--bg:#f8faf9;--card:#fff;--bdr:#e8f0e9;--txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;--sb-w:260px;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,#c8e6c9 1px,transparent 1px);background-size:28px 28px;opacity:.35;}
.page-wrap{margin-left:var(--sb-w);flex:1;position:relative;z-index:1;min-width:0;transition: margin-left 0.3s ease;}
.topbar{height:70px;background:rgba(255,255,255,.98);backdrop-filter:blur(12px);border-bottom:1px solid var(--bdr);display:flex;align-items:center;padding:0 2.5rem;gap:14px;position:sticky;top:0;z-index:50;}

.hamburger { display: none; width: 40px; height: 40px; border-radius: 10px; background: #fff; border: 1px solid var(--bdr); align-items: center; justify-content: center; cursor: pointer; color: var(--txt); font-size: 1.1rem; transition: all .2s; margin-right: 14px; flex-shrink: 0; }
.hamburger:hover { background: var(--g50); color: var(--g600); border-color: var(--g300); }
@media (max-width: 1024px) { .hamburger { display: flex; } .page-wrap { margin-left: 0; } .topbar { padding: 0 1.25rem; } main { padding: 1.5rem 1rem !important; } }

.card { background:#fff; border:none; border-radius:24px; padding:28px; box-shadow:0 10px 30px rgba(0,0,0,.03); margin-bottom: 2rem;}
.table { width:100%; border-collapse: separate; border-spacing: 0; font-size:.88rem; }
.table th { text-align:left; padding:16px 14px; background:var(--g50); color:var(--g700); font-weight:700; border-bottom:2px solid var(--g200); font-size:.8rem; text-transform: uppercase; letter-spacing: 0.5px;}
.table td { padding:16px 14px; border-bottom:1px solid #f1f5f3; vertical-align: middle; }
.table th:first-child { border-top-left-radius: 12px; } .table th:last-child { border-top-right-radius: 12px; }
.table tbody tr:hover { background-color: #fdfdfd; }

.badge { font-size:.7rem; font-weight:600; padding:6px 12px; border-radius:8px; display:inline-block; }
.badge-green { background:var(--g50); color:var(--g700); border:1px solid var(--g200); }
.badge-gray { background:#f3f4f6; color:#4b5563; border:1px solid #e5e7eb; font-family:'Nunito',sans-serif;}

.btn { padding:8px 14px; border-radius:10px; font-size:.8rem; font-weight:600; cursor:pointer; transition:all .2s; border:none; }
.btn:hover { transform: translateY(-1px); filter: brightness(0.95); }
.form-input { width:100%; padding:10px 14px; border:1.5px solid var(--bdr); border-radius:10px; font-family:'Kanit',sans-serif; font-size:.88rem; outline: none; transition: all 0.2s; background: #fafafa;}
.form-input:focus { border-color: var(--g500); background: #fff; box-shadow: 0 0 0 3px rgba(34,197,94,.1); }
.form-label { display:block; font-size:.82rem; font-weight:600; color:var(--sub); margin-bottom:6px; }
.swal2-container { font-family: 'Kanit', sans-serif !important; }
</style>
</head>
<body>

<?php include '../includes/sidebar_admin.php' ?>

<div class="page-wrap">
  <header class="topbar">
    <button class="hamburger"><i class="fas fa-bars"></i></button>
    <div style="flex:1;">
      <div style="font-family:'Nunito',sans-serif;font-size:1.2rem;font-weight:800;color:#2b3452;">จัดการเป้าหมาย (Goals)</div>
      <div style="font-size:.75rem;color:var(--muted);"><i class="fas fa-bullseye me-1"></i> ตัวเลือกเป้าหมายในหน้าตั้งค่า Profile</div>
    </div>
    <button onclick="openModal('add')" class="btn" style="background:linear-gradient(135deg,var(--g500),var(--t500)); color:#fff; box-shadow:0 4px 12px rgba(34,197,94,.2);">
      <i class="fas fa-plus"></i> เพิ่มเป้าหมายใหม่
    </button>
  </header>
  
  <main style="padding:2.5rem; max-width: 1200px; margin: 0 auto;">
    <div class="card">
      <div style="overflow-x:auto;">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Key (รหัส)</th>
              <th>ชื่อเป้าหมาย</th>
              <th>คำอธิบาย</th>
              <th>ป้ายกำกับ (Badge)</th>
              <th style="text-align: right;">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($goals as $g): ?>
            <tr>
              <td style="color:var(--muted); font-weight:600;">#<?= $g['id'] ?></td>
              <td><span class="badge badge-gray"><?= htmlspecialchars($g['goal_key']) ?></span></td>
              <td style="font-weight:600; color:var(--txt);"><?= htmlspecialchars($g['title']) ?></td>
              <td style="color:var(--muted);"><?= htmlspecialchars($g['description']) ?></td>
              <td><span class="badge badge-green"><?= htmlspecialchars($g['badge']) ?></span></td>
              <td style="text-align: right; min-width: 150px;">
                <div style="display:inline-flex; gap:6px;">
                  <button type="button" onclick="openModal('edit', <?= htmlspecialchars(json_encode($g), ENT_QUOTES) ?>)" class="btn" style="background:#f8faf9; color:var(--g700); border:1px solid var(--bdr); padding:6px 10px; font-size:0.75rem;">
                    <i class="fas fa-edit"></i> แก้ไข
                  </button>
                  <button type="button" onclick="confirmDelete(<?= $g['id'] ?>, '<?= htmlspecialchars($g['title'], ENT_QUOTES) ?>')" class="btn" style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca; padding:6px 10px; font-size:0.75rem;">
                    <i class="fas fa-trash-alt"></i> ลบ
                  </button>
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

<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete_goal">
    <input type="hidden" name="goal_id" id="delete_goal_id">
</form>

<div id="goalModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:24px;padding:32px;max-width:500px;width:90%;box-shadow:0 20px 40px rgba(0,0,0,.15);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;border-bottom:1px solid var(--bdr);padding-bottom:16px;">
      <h3 id="modalTitle" style="font-family:'Nunito',sans-serif;font-size:1.25rem;font-weight:800;color:#2b3452;margin:0;">
        <i class="fas fa-bullseye" style="color:var(--g500);margin-right:8px;"></i> <span id="modalTitleText">เพิ่มเป้าหมาย</span>
      </h3>
      <button type="button" onclick="closeModal()" style="width:36px;height:36px;border-radius:10px;border:none;background:#f1f5f3;color:var(--muted);cursor:pointer;font-size:1rem;transition:all 0.2s;">
        <i class="fas fa-times"></i>
      </button>
    </div>
    
    <form method="POST">
      <input type="hidden" name="action" id="formAction" value="add_goal">
      <input type="hidden" name="goal_id" id="goal_id">
      
      <div style="margin-bottom:16px;">
        <label class="form-label">รหัสเป้าหมาย (ภาษาอังกฤษ ห้ามเว้นวรรค เช่น lose_fat)</label>
        <input type="text" name="goal_key" id="goal_key" class="form-input" placeholder="เช่น lose_fat" required>
      </div>
      
      <div style="margin-bottom:16px;">
        <label class="form-label">ชื่อเป้าหมาย (ภาษาไทย)</label>
        <input type="text" name="title" id="title" class="form-input" placeholder="เช่น ลดน้ำหนักเร่งด่วน" required>
      </div>

      <div style="margin-bottom:16px;">
        <label class="form-label">คำอธิบายย่อย</label>
        <input type="text" name="description" id="description" class="form-input" placeholder="เช่น ต้องมีวินัยสูง" required>
      </div>

      <div style="margin-bottom:24px;">
        <label class="form-label">ป้ายกำกับ (Badge)</label>
        <input type="text" name="badge" id="badge" class="form-input" placeholder="เช่น -500 kcal">
      </div>
      
      <div style="display:flex; justify-content:flex-end; gap:12px;">
        <button type="button" onclick="closeModal()" class="btn" style="background:#f1f5f3; color:var(--sub); padding:12px 20px;">ยกเลิก</button>
        <button type="submit" class="btn" style="background:linear-gradient(135deg,var(--g500),var(--t500)); color:#fff; padding:12px 24px; box-shadow:0 4px 12px rgba(34,197,94,.2);">
          <i class="fas fa-save"></i> บันทึกข้อมูล
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($success_msg): ?>
        Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: '<?= htmlspecialchars($success_msg) ?>', confirmButtonColor: '#22c55e' });
    <?php endif; ?>
    <?php if ($error_msg): ?>
        Swal.fire({ icon: 'error', title: 'พบข้อผิดพลาด!', text: '<?= htmlspecialchars($error_msg) ?>', confirmButtonColor: '#dc2626' });
    <?php endif; ?>
});

function confirmDelete(id, title) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        html: `คุณต้องการลบเป้าหมาย <b>${title}</b> ใช่หรือไม่?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#9ca3af',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_goal_id').value = id;
            document.getElementById('deleteForm').submit();
        }
    });
}

function openModal(type, data = null) {
  if(type === 'edit') {
    document.getElementById('modalTitleText').innerText = 'แก้ไขเป้าหมาย';
    document.getElementById('formAction').value = 'edit_goal';
    document.getElementById('goal_id').value = data.id;
    document.getElementById('goal_key').value = data.goal_key;
    document.getElementById('title').value = data.title;
    document.getElementById('description').value = data.description;
    document.getElementById('badge').value = data.badge;
  } else {
    document.getElementById('modalTitleText').innerText = 'เพิ่มเป้าหมายใหม่';
    document.getElementById('formAction').value = 'add_goal';
    document.getElementById('goal_id').value = '';
    document.getElementById('goal_key').value = '';
    document.getElementById('title').value = '';
    document.getElementById('description').value = '';
    document.getElementById('badge').value = '';
  }
  document.getElementById('goalModal').style.display = 'flex';
}

function closeModal() {
  document.getElementById('goalModal').style.display = 'none';
}
</script>

</body>
</html>