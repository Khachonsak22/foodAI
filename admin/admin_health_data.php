<?php
session_start();
include '../config/connect.php';

if (!isset($_SESSION['user_id'])) { 
    header("Location: ../pages/login.php"); 
    exit(); 
}

$admin_stmt = $conn->prepare("SELECT email, first_name FROM users WHERE id = ?");
$admin_stmt->bind_param("i", $_SESSION['user_id']);
$admin_stmt->execute();
$admin_data = $admin_stmt->get_result()->fetch_assoc();

if (!str_ends_with($admin_data['email'], '@admin.com')) { 
    header("Location: ../pages/dashboard.php"); 
    exit(); 
}

$success_msg = '';
$error_msg = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_condition') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? '🏥');
        
        if (!empty($name)) {
            $stmt = $conn->prepare("INSERT INTO tags (name, type, description, icon, color) VALUES (?, 'health_condition', ?, ?, '#22c55e')");
            $stmt->bind_param("sss", $name, $description, $icon);
            if ($stmt->execute()) {
                $success_msg = "เพิ่มโรคประจำตัว: $name สำเร็จ!";
            } else {
                $error_msg = "เกิดข้อผิดพลาด";
            }
        }
    }
    
    if ($action === 'add_allergen') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? '⚠️');
        
        if (!empty($name)) {
            $stmt = $conn->prepare("INSERT INTO tags (name, type, description, icon, color) VALUES (?, 'allergen', ?, ?, '#fbbf24')");
            $stmt->bind_param("sss", $name, $description, $icon);
            if ($stmt->execute()) {
                $success_msg = "เพิ่มอาหารที่แพ้: $name สำเร็จ!";
            } else {
                $error_msg = "เกิดข้อผิดพลาด";
            }
        }
    }
    
    if ($action === 'edit_tag') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        
        if ($id > 0 && !empty($name)) {
            $stmt = $conn->prepare("UPDATE tags SET name = ?, description = ?, icon = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $description, $icon, $id);
            if ($stmt->execute()) {
                $success_msg = "อัปเดต: $name สำเร็จ!";
            } else {
                $error_msg = "เกิดข้อผิดพลาด";
            }
        }
    }
    
    if ($action === 'delete_tag') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM tags WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success_msg = "ลบสำเร็จ!";
            } else {
                $error_msg = "เกิดข้อผิดพลาด";
            }
        }
    }
}

// Get Health Conditions
$conditions = [];
$cond_query = "SELECT * FROM tags WHERE type = 'health_condition' ORDER BY name";
$cond_result = $conn->query($cond_query);
if ($cond_result) {
    while ($row = $cond_result->fetch_assoc()) {
        $conditions[] = $row;
    }
}

// Get Allergens
$allergens = [];
$allerg_query = "SELECT * FROM tags WHERE type = 'allergen' ORDER BY name";
$allerg_result = $conn->query($allerg_query);
if ($allerg_result) {
    while ($row = $allerg_result->fetch_assoc()) {
        $allergens[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการข้อมูลสุขภาพ — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
:root{--g50:#f0fdf4;--g200:#bbf7d0;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--t500:#14b8a6;--bg:#f5f8f5;--bdr:#e8f0e9;--txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;--sb-w:260px;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,#c8e6c9 1px,transparent 1px);background-size:28px 28px;opacity:.35;}
.page-wrap{margin-left:var(--sb-w);flex:1;position:relative;z-index:1;min-width:0;}
.topbar{height:66px;background:rgba(255,255,255,.95);backdrop-filter:blur(12px);border-bottom:1px solid #e5ede6;display:flex;align-items:center;padding:0 2.5rem;gap:14px;position:sticky;top:0;z-index:50;}
.hamburger{display:none;width:40px;height:40px;border-radius:10px;background:#fff;border:1.5px solid var(--bdr);align-items:center;justify-content:center;cursor:pointer;transition:all .2s;}
.hamburger:hover{background:var(--g50);border-color:var(--g300);}
main{padding:2rem 2.5rem 3.5rem;max-width:1400px;margin:0 auto;}
.card{background:#fff;border:1px solid var(--bdr);border-radius:18px;padding:24px;margin-bottom:24px;box-shadow:0 4px 20px rgba(0,0,0,.04);}
.section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;}
.section-title{font-family:'Nunito',sans-serif;font-size:1.3rem;font-weight:800;color:var(--txt);display:flex;align-items:center;gap:10px;}
.btn{padding:10px 20px;border-radius:10px;font-size:.85rem;font-weight:600;border:none;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:8px;}
.btn-primary{background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;box-shadow:0 4px 12px rgba(34,197,94,.3);}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(34,197,94,.4);}
.btn-secondary{background:#fff;color:var(--sub);border:2px solid var(--bdr);}
.btn-secondary:hover{background:var(--g50);border-color:var(--g300);}
.btn-edit{background:#fef3c7;color:#92400e;border:1px solid #fbbf24;}
.btn-delete{background:#fee2e2;color:#dc2626;border:1px solid #fecaca;}
.alert{padding:14px 18px;border-radius:12px;margin-bottom:1.5rem;display:flex;align-items:center;gap:10px;font-size:.88rem;}
.alert-success{background:#dcfce7;color:#15803d;border:2px solid #86efac;}
.alert-error{background:#fee2e2;color:#dc2626;border:2px solid#fecaca;}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;}
.modal.show{display:flex;}
.modal-content{background:#fff;border-radius:20px;padding:2rem;width:90%;max-width:500px;box-shadow:0 20px 60px rgba(0,0,0,.3);}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;}
.modal-title{font-family:'Nunito',sans-serif;font-size:1.3rem;font-weight:800;color:var(--txt);}
.close-btn{background:none;border:none;font-size:1.5rem;color:var(--muted);cursor:pointer;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;transition:all .2s;}
.close-btn:hover{background:#fee2e2;color:#dc2626;}
.form-group{margin-bottom:1.2rem;}
.form-label{display:block;font-weight:600;color:var(--txt);margin-bottom:6px;font-size:.9rem;}
.form-input{width:100%;padding:12px 16px;border:2px solid var(--bdr);border-radius:10px;font-family:'Kanit',sans-serif;font-size:.9rem;transition:all .2s;}
.form-input:focus{outline:none;border-color:var(--g400);box-shadow:0 0 0 4px rgba(34,197,94,.1);}
.form-textarea{min-height:80px;resize:vertical;}
table.dataTable{width:100%!important;border-collapse:collapse;}
table.dataTable thead th{background:var(--g50);color:var(--g700);font-weight:700;padding:12px;border-bottom:2px solid var(--g200);}
table.dataTable tbody td{padding:12px;border-bottom:1px solid var(--bdr);}
table.dataTable tbody tr:hover{background:var(--g50);}
.tag-icon{font-size:1.3rem;margin-right:8px;}
@media (max-width:1024px){
  .page-wrap{margin-left:0;}
  .hamburger{display:flex;}
}
@media (max-width:768px){
  .topbar{padding:0 1rem;}
  main{padding:1.5rem 1rem;}
  .section-header{flex-direction:column;gap:1rem;align-items:flex-start;}
}
</style>
</head>
<body>

<?php include '../includes/sidebar_admin.php'; ?>

<div class="page-wrap">
  <header class="topbar">
    <button class="hamburger" onclick="toggleSidebar()">
      <i class="fas fa-bars"></i>
    </button>
    <div>
      <div style="font-family:'Nunito',sans-serif;font-size:1.1rem;font-weight:800;">จัดการข้อมูลสุขภาพ</div>
      <div style="font-size:.72rem;color:var(--muted);">โรคประจำตัว และ อาหารที่แพ้</div>
    </div>
  </header>

  <main>
    
    <?php if ($success_msg): ?>
    <div class="alert alert-success">
      <i class="fas fa-check-circle"></i>
      <span><?= htmlspecialchars($success_msg) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
    <div class="alert alert-error">
      <i class="fas fa-exclamation-circle"></i>
      <span><?= htmlspecialchars($error_msg) ?></span>
    </div>
    <?php endif; ?>

    <!-- Health Conditions -->
    <div class="card">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-heartbeat" style="color:var(--g500);"></i>
          โรคประจำตัว (<?= count($conditions) ?> รายการ)
        </h2>
        <button class="btn btn-primary" onclick="openAddConditionModal()">
          <i class="fas fa-plus"></i> เพิ่มโรค
        </button>
      </div>
      <table id="conditionsTable" class="display">
        <thead>
          <tr>
            <th>Icon</th>
            <th>ชื่อโรค</th>
            <th>คำอธิบาย</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($conditions as $c): ?>
          <tr>
            <td><span class="tag-icon"><?= htmlspecialchars($c['icon']) ?></span></td>
            <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
            <td><?= htmlspecialchars($c['description'] ?: '-') ?></td>
            <td>
              <button class="btn btn-edit" onclick='editTag(<?= json_encode($c) ?>)'>
                <i class="fas fa-edit"></i>
              </button>
              <button class="btn btn-delete" onclick="deleteTag(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name']) ?>')">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Allergens -->
    <div class="card">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-triangle-exclamation" style="color:#fbbf24;"></i>
          อาหารที่แพ้ (<?= count($allergens) ?> รายการ)
        </h2>
        <button class="btn btn-primary" onclick="openAddAllergenModal()">
          <i class="fas fa-plus"></i> เพิ่มอาหารแพ้
        </button>
      </div>
      <table id="allergensTable" class="display">
        <thead>
          <tr>
            <th>Icon</th>
            <th>ชื่ออาหาร</th>
            <th>คำอธิบาย</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allergens as $a): ?>
          <tr>
            <td><span class="tag-icon"><?= htmlspecialchars($a['icon']) ?></span></td>
            <td><strong><?= htmlspecialchars($a['name']) ?></strong></td>
            <td><?= htmlspecialchars($a['description'] ?: '-') ?></td>
            <td>
              <button class="btn btn-edit" onclick='editTag(<?= json_encode($a) ?>)'>
                <i class="fas fa-edit"></i>
              </button>
              <button class="btn btn-delete" onclick="deleteTag(<?= $a['id'] ?>, '<?= htmlspecialchars($a['name']) ?>')">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </main>
</div>

<!-- Add Condition Modal -->
<div id="addConditionModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title">เพิ่มโรคประจำตัว</h3>
      <button class="close-btn" onclick="closeModal('addConditionModal')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_condition">
      <div class="form-group">
        <label class="form-label">ชื่อโรค *</label>
        <input type="text" name="name" class="form-input" required>
      </div>
      <div class="form-group">
        <label class="form-label">คำอธิบาย</label>
        <textarea name="description" class="form-input form-textarea"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Icon (emoji)</label>
        <input type="text" name="icon" class="form-input" value="🏥">
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary">บันทึก</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('addConditionModal')">ยกเลิก</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Allergen Modal -->
<div id="addAllergenModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title">เพิ่มอาหารที่แพ้</h3>
      <button class="close-btn" onclick="closeModal('addAllergenModal')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_allergen">
      <div class="form-group">
        <label class="form-label">ชื่ออาหาร *</label>
        <input type="text" name="name" class="form-input" required>
      </div>
      <div class="form-group">
        <label class="form-label">คำอธิบาย</label>
        <textarea name="description" class="form-input form-textarea"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Icon (emoji)</label>
        <input type="text" name="icon" class="form-input" value="⚠️">
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary">บันทึก</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('addAllergenModal')">ยกเลิก</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title">แก้ไขข้อมูล</h3>
      <button class="close-btn" onclick="closeModal('editModal')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit_tag">
      <input type="hidden" name="id" id="edit_id">
      <div class="form-group">
        <label class="form-label">ชื่อ *</label>
        <input type="text" name="name" id="edit_name" class="form-input" required>
      </div>
      <div class="form-group">
        <label class="form-label">คำอธิบาย</label>
        <textarea name="description" id="edit_description" class="form-input form-textarea"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Icon</label>
        <input type="text" name="icon" id="edit_icon" class="form-input">
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary">บันทึก</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">ยกเลิก</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form (hidden) -->
<form id="deleteForm" method="POST" style="display:none;">
  <input type="hidden" name="action" value="delete_tag">
  <input type="hidden" name="id" id="delete_id">
</form>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
  $('#conditionsTable').DataTable({
    language: {
      url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json'
    },
    pageLength: 10
  });
  
  $('#allergensTable').DataTable({
    language: {
      url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json'
    },
    pageLength: 10
  });
});

function toggleSidebar() {
  document.querySelector('.sidebar').classList.toggle('open');
}

function openAddConditionModal() {
  document.getElementById('addConditionModal').classList.add('show');
}

function openAddAllergenModal() {
  document.getElementById('addAllergenModal').classList.add('show');
}

function closeModal(modalId) {
  document.getElementById(modalId).classList.remove('show');
}

function editTag(tag) {
  document.getElementById('edit_id').value = tag.id;
  document.getElementById('edit_name').value = tag.name;
  document.getElementById('edit_description').value = tag.description || '';
  document.getElementById('edit_icon').value = tag.icon || '';
  document.getElementById('editModal').classList.add('show');
}

function deleteTag(id, name) {
  if (confirm(`ต้องการลบ "${name}" ?`)) {
    document.getElementById('delete_id').value = id;
    document.getElementById('deleteForm').submit();
  }
}

// Close modal when clicking outside
window.onclick = function(event) {
  if (event.target.classList.contains('modal')) {
    event.target.classList.remove('show');
  }
}
</script>

</body>
</html>