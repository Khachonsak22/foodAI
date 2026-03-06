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

// Handle Actions (เอา icon ออกจากการบันทึกลงฐานข้อมูล)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_condition') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (!empty($name)) {
            // ไม่บันทึก icon
            $stmt = $conn->prepare("INSERT INTO tags (name, type, description, color) VALUES (?, 'health_condition', ?, '#22c55e')");
            $stmt->bind_param("ss", $name, $description);
            if ($stmt->execute()) {
                $success_msg = "เพิ่มโรคประจำตัว: $name สำเร็จ!";
            } else {
                $error_msg = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
            }
        }
    }
    
    if ($action === 'add_allergen') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (!empty($name)) {
            // ไม่บันทึก icon
            $stmt = $conn->prepare("INSERT INTO tags (name, type, description, color) VALUES (?, 'allergen', ?, '#fbbf24')");
            $stmt->bind_param("ss", $name, $description);
            if ($stmt->execute()) {
                $success_msg = "เพิ่มอาหารที่แพ้: $name สำเร็จ!";
            } else {
                $error_msg = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
            }
        }
    }
    
    if ($action === 'edit_tag') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if ($id > 0 && !empty($name)) {
            // เอา icon ออกจากคำสั่งอัปเดต
            $stmt = $conn->prepare("UPDATE tags SET name = ?, description = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $description, $id);
            if ($stmt->execute()) {
                $success_msg = "อัปเดตข้อมูล: $name สำเร็จ!";
            } else {
                $error_msg = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล";
            }
        }
    }
    
    if ($action === 'delete_tag') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM tags WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success_msg = "ลบข้อมูลสำเร็จ!";
            } else {
                $error_msg = "เกิดข้อผิดพลาดในการลบข้อมูล";
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
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<style>
:root{--g50:#f0fdf4;--g200:#bbf7d0;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--t500:#14b8a6;--bg:#f8f9fa;--bdr:#e8f0e9;--txt:#1a2e1a;--sub:#4b6b4e;--muted:#6c757d;--sb-w:260px;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,#c8e6c9 1px,transparent 1px);background-size:28px 28px;opacity:.35;}
.page-wrap{margin-left:var(--sb-w);flex:1;position:relative;z-index:1;min-width:0;}
.topbar{height:70px;background:rgba(255,255,255,.98);backdrop-filter:blur(12px);border-bottom:1px solid rgba(0,0,0,0.05);display:flex;align-items:center;padding:0 2.5rem;gap:14px;position:sticky;top:0;z-index:50;}
.hamburger{display:none;width:40px;height:40px;border-radius:10px;background:#fff;border:1px solid #dee2e6;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;}
.hamburger:hover{background:#f8f9fa;}
main{padding:2.5rem;max-width:1400px;margin:0 auto;}

/* Custom Cards */
.custom-card {
    background: #fff;
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,.04);
    margin-bottom: 2rem;
    overflow: hidden;
}
.card-header-custom {
    background: #fff;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    padding: 1.5rem 1.5rem 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-title-custom {
    font-family: 'Nunito', sans-serif;
    font-size: 1.25rem;
    font-weight: 800;
    color: #2b3452;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}
.card-body-custom {
    padding: 1.5rem;
}

/* Custom Buttons */
.btn-gradient-primary {
    background: linear-gradient(135deg, var(--g500), var(--t500));
    color: #fff;
    border: none;
    font-weight: 600;
    padding: 0.5rem 1.2rem;
    border-radius: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(34,197,94,.2);
}
.btn-gradient-primary:hover {
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(34,197,94,.3);
}

/* Modal Styling */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);z-index:1050;align-items:center;justify-content:center;}
.modal.show{display:flex;}
.modal-content-custom {
    background: #fff;
    border-radius: 20px;
    padding: 2rem;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 20px 40px rgba(0,0,0,.15);
    transform: translateY(-20px);
    animation: modalSlideIn 0.3s forwards;
    border: none;
}
@keyframes modalSlideIn {
    to { transform: translateY(0); }
}
.modal-header-custom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.modal-title-custom {
    font-family: 'Nunito', sans-serif;
    font-size: 1.25rem;
    font-weight: 800;
    color: #2b3452;
    margin: 0;
}
.btn-close-custom {
    background: #f8f9fa;
    border: none;
    font-size: 1.2rem;
    color: var(--muted);
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-close-custom:hover {
    background: #fee2e2;
    color: #dc2626;
}

/* Form Styling */
.form-label {
    font-weight: 600;
    color: #495057;
}
.form-control {
    border-radius: 8px;
    padding: 0.6rem 1rem;
    border: 1px solid #dee2e6;
    font-size: 0.95rem;
}
.form-control:focus {
    border-color: var(--g500);
    box-shadow: 0 0 0 0.25rem rgba(34, 197, 94, 0.15);
}

/* DataTables Customization */
table.dataTable.table-striped > tbody > tr:nth-of-type(odd) > * {
    box-shadow: inset 0 0 0 9999px rgba(0,0,0,0.01);
}
table.dataTable > thead > tr > th {
    border-bottom: 2px solid #e9ecef;
    color: #6c757d;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}
table.dataTable > tbody > tr > td {
    vertical-align: middle;
    color: #495057;
    border-bottom: 1px solid #f1f3f5;
}

@media (max-width:1024px){
  .page-wrap{margin-left:0;}
  .hamburger{display:flex;}
}
@media (max-width:768px){
  .topbar{padding:0 1rem;}
  main{padding:1.5rem 1rem;}
  .card-header-custom{flex-direction:column;gap:1rem;align-items:flex-start;}
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
      <div style="font-family:'Nunito',sans-serif;font-size:1.15rem;font-weight:800;color:#2b3452;">จัดการข้อมูลสุขภาพ</div>
      <div style="font-size:.75rem;color:var(--muted);"><i class="fas fa-notes-medical me-1"></i> โรคประจำตัว และ อาหารที่แพ้</div>
    </div>
  </header>

  <main>
    
    <?php if ($success_msg): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" style="background:#dcfce7; color:#15803d; border-radius:12px;" role="alert">
      <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success_msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" style="background:#fee2e2; color:#dc2626; border-radius:12px;" role="alert">
      <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error_msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="custom-card">
      <div class="card-header-custom">
        <h2 class="card-title-custom">
          <div class="icon-box" style="background:#dcfce7; color:var(--g600); width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center;">
             <i class="fas fa-heartbeat"></i>
          </div>
          โรคประจำตัว <span class="badge bg-success rounded-pill" style="font-size:0.75rem;"><?= count($conditions) ?> รายการ</span>
        </h2>
        <button class="btn btn-gradient-primary" onclick="openAddConditionModal()">
          <i class="fas fa-plus me-1"></i> เพิ่มโรคประจำตัว
        </button>
      </div>
      <div class="card-body-custom">
        <div class="table-responsive">
            <table id="conditionsTable" class="table table-hover table-borderless w-100">
            <thead>
                <tr>
                <th width="30%">ชื่อโรค</th>
                <th width="50%">คำอธิบาย</th>
                <th width="20%" class="text-end">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conditions as $c): ?>
                <tr>
                <td><strong class="text-dark"><?= htmlspecialchars($c['name']) ?></strong></td>
                <td class="text-muted"><?= htmlspecialchars($c['description'] ?: '-') ?></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-warning text-dark border-0 bg-warning bg-opacity-10 me-1" onclick='editTag(<?= json_encode($c) ?>)' title="แก้ไข">
                    <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger border-0 bg-danger bg-opacity-10" onclick="deleteTag(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name']) ?>')" title="ลบ">
                    <i class="fas fa-trash"></i>
                    </button>
                </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            </table>
        </div>
      </div>
    </div>

    <div class="custom-card mt-4">
      <div class="card-header-custom">
        <h2 class="card-title-custom">
          <div class="icon-box" style="background:#fef3c7; color:#d97706; width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center;">
             <i class="fas fa-triangle-exclamation"></i>
          </div>
          อาหารที่แพ้ <span class="badge bg-warning text-dark rounded-pill" style="font-size:0.75rem;"><?= count($allergens) ?> รายการ</span>
        </h2>
        <button class="btn btn-warning text-dark fw-bold border-0 shadow-sm" style="border-radius:8px;" onclick="openAddAllergenModal()">
          <i class="fas fa-plus me-1"></i> เพิ่มอาหารที่แพ้
        </button>
      </div>
      <div class="card-body-custom">
        <div class="table-responsive">
            <table id="allergensTable" class="table table-hover table-borderless w-100">
            <thead>
                <tr>
                <th width="30%">ชื่ออาหาร</th>
                <th width="50%">คำอธิบาย</th>
                <th width="20%" class="text-end">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allergens as $a): ?>
                <tr>
                <td><strong class="text-dark"><?= htmlspecialchars($a['name']) ?></strong></td>
                <td class="text-muted"><?= htmlspecialchars($a['description'] ?: '-') ?></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-warning text-dark border-0 bg-warning bg-opacity-10 me-1" onclick='editTag(<?= json_encode($a) ?>)' title="แก้ไข">
                    <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger border-0 bg-danger bg-opacity-10" onclick="deleteTag(<?= $a['id'] ?>, '<?= htmlspecialchars($a['name']) ?>')" title="ลบ">
                    <i class="fas fa-trash"></i>
                    </button>
                </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            </table>
        </div>
      </div>
    </div>

  </main>
</div>

<div id="addConditionModal" class="modal">
  <div class="modal-content-custom">
    <div class="modal-header-custom">
      <h3 class="modal-title-custom"><i class="fas fa-heartbeat text-success me-2"></i>เพิ่มโรคประจำตัว</h3>
      <button type="button" class="btn-close-custom" onclick="closeModal('addConditionModal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_condition">
      <div class="mb-3">
        <label class="form-label">ชื่อโรค <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control bg-light" placeholder="เช่น เบาหวาน, ความดัน..." required>
      </div>
      <div class="mb-4">
        <label class="form-label">คำอธิบาย</label>
        <textarea name="description" class="form-control bg-light" rows="3" placeholder="รายละเอียดเพิ่มเติม (ถ้ามี)"></textarea>
      </div>
      <div class="d-flex gap-2 justify-content-end">
        <button type="button" class="btn btn-light border" onclick="closeModal('addConditionModal')">ยกเลิก</button>
        <button type="submit" class="btn btn-success px-4">บันทึกข้อมูล</button>
      </div>
    </form>
  </div>
</div>

<div id="addAllergenModal" class="modal">
  <div class="modal-content-custom">
    <div class="modal-header-custom">
      <h3 class="modal-title-custom"><i class="fas fa-triangle-exclamation text-warning me-2"></i>เพิ่มอาหารที่แพ้</h3>
      <button type="button" class="btn-close-custom" onclick="closeModal('addAllergenModal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_allergen">
      <div class="mb-3">
        <label class="form-label">ชื่ออาหาร/ส่วนผสม <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control bg-light" placeholder="เช่น ถั่วลิสง, นมวัว..." required>
      </div>
      <div class="mb-4">
        <label class="form-label">คำอธิบาย</label>
        <textarea name="description" class="form-control bg-light" rows="3" placeholder="รายละเอียดเพิ่มเติม (ถ้ามี)"></textarea>
      </div>
      <div class="d-flex gap-2 justify-content-end">
        <button type="button" class="btn btn-light border" onclick="closeModal('addAllergenModal')">ยกเลิก</button>
        <button type="submit" class="btn btn-warning text-dark fw-bold px-4">บันทึกข้อมูล</button>
      </div>
    </form>
  </div>
</div>

<div id="editModal" class="modal">
  <div class="modal-content-custom">
    <div class="modal-header-custom">
      <h3 class="modal-title-custom"><i class="fas fa-edit text-primary me-2"></i>แก้ไขข้อมูล</h3>
      <button type="button" class="btn-close-custom" onclick="closeModal('editModal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit_tag">
      <input type="hidden" name="id" id="edit_id">
      <div class="mb-3">
        <label class="form-label">ชื่อ <span class="text-danger">*</span></label>
        <input type="text" name="name" id="edit_name" class="form-control bg-light" required>
      </div>
      <div class="mb-4">
        <label class="form-label">คำอธิบาย</label>
        <textarea name="description" id="edit_description" class="form-control bg-light" rows="3"></textarea>
      </div>
      <div class="d-flex gap-2 justify-content-end">
        <button type="button" class="btn btn-light border" onclick="closeModal('editModal')">ยกเลิก</button>
        <button type="submit" class="btn btn-primary px-4">อัปเดตข้อมูล</button>
      </div>
    </form>
  </div>
</div>

<form id="deleteForm" method="POST" style="display:none;">
  <input type="hidden" name="action" value="delete_tag">
  <input type="hidden" name="id" id="delete_id">
</form>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
  // ตั้งค่า DataTables ให้เข้ากับ Bootstrap 5
  const dtOptions = {
    language: {
      url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json'
    },
    pageLength: 10,
    ordering: false, // ปิดการเรียงลำดับแบบเก่าเพื่อให้ดูสะอาดตา
    dom: "<'row mb-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
         "<'row'<'col-sm-12'tr>>" +
         "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
  };

  $('#conditionsTable').DataTable(dtOptions);
  $('#allergensTable').DataTable(dtOptions);
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
  // ตัดส่วนที่ดึงค่า icon ออกไป
  document.getElementById('editModal').classList.add('show');
}

function deleteTag(id, name) {
  if (confirm(`คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูล "${name}" ?`)) {
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