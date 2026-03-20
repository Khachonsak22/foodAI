<?php

ini_set('display_errors',1);
ini_set('display_statup_errors',1);
error_reporting(E_ALL);

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

/* ══════════════════════════════════════════════════════════════════
   HANDLE ACTIONS
   ══════════════════════════════════════════════════════════════════ */
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // เพิ่มข้อมูลวัตถุดิบ
    if ($action === 'add_ingredient') {
        $name = trim($_POST['name']);
        
        $stmt = $conn->prepare("INSERT INTO ingredients (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        
        // ใช้ try...catch เพื่อดักจับ Error จาก Database (เช่น การกรอกชื่อซ้ำ)
        try {
            if ($stmt->execute()) {
                $success_msg = "เพิ่มวัตถุดิบเรียบร้อย";
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) { // 1062 คือรหัส Error ของข้อมูลซ้ำ (Duplicate entry)
                $error_msg = "มีวัตถุดิบชื่อ '{$name}' อยู่ในระบบแล้ว ไม่สามารถเพิ่มซ้ำได้";
            } else {
                $error_msg = "เกิดข้อผิดพลาดในการเพิ่มข้อมูล: " . $e->getMessage();
            }
        }
    }

    // แก้ไขข้อมูลวัตถุดิบ
    if ($action === 'edit_ingredient') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        
        $stmt = $conn->prepare("UPDATE ingredients SET name=? WHERE id=?");
        $stmt->bind_param("si", $name, $id);
        
        try {
            if ($stmt->execute()) {
                $success_msg = "แก้ไขวัตถุดิบเรียบร้อย";
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $error_msg = "มีวัตถุดิบชื่อ '{$name}' อยู่ในระบบแล้ว ไม่สามารถใช้ชื่อนี้ได้";
            } else {
                $error_msg = "เกิดข้อผิดพลาดในการแก้ไขข้อมูล: " . $e->getMessage();
            }
        }
    }
    
    // ลบวัตถุดิบ
    if ($action === 'delete_ingredient') {
        $id = (int)$_POST['ingredient_id'];
        $stmt = $conn->prepare("DELETE FROM ingredients WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        try {
            if ($stmt->execute()) {
                $success_msg = "ลบวัตถุดิบเรียบร้อย";
            }
        } catch (mysqli_sql_exception $e) {
            $error_msg = "เกิดข้อผิดพลาดในการลบ: " . $e->getMessage();
        }
    }
}

// Fetch all ingredients (ดึงแค่ id และ name)
$ing_sql = "
    SELECT id, name
    FROM ingredients
    ORDER BY name ASC
";
$ingredients = $conn->query($ing_sql)->fetch_all(MYSQLI_ASSOC);
$total = count($ingredients);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการวัตถุดิบ — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<style>
/* CSS โครงสร้างเดิมทั้งหมด */
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
.btn{padding:8px 16px;border-radius:10px;font-size:.78rem;font-weight:600;cursor:pointer;transition:all .18s;display:inline-flex;align-items:center;gap:6px;border:none;}
.btn-sm{padding:6px 12px;font-size:.72rem;}
.btn-green{background:linear-gradient(135deg,var(--g500),#14b8a6);color:#fff;box-shadow:0 4px 12px rgba(34,197,94,.2);}
.btn-green:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(34,197,94,.3);}
/* Modal */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;opacity:0;transition:opacity .2s;}
.modal.open{display:flex;opacity:1;}
.modal-content{background:#fff;border-radius:20px;padding:28px;max-width:500px;width:90%;transform:translateY(20px);transition:transform .3s;max-height:90vh;overflow-y:auto;}
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
</style>
</head>
<body>

<?php include '../includes/sidebar_admin.php' ?>

<div class="page-wrap">
  <header class="topbar">
    <button class="hamburger"><i class="fas fa-bars"></i></button>
    <div style="flex:1;">
      <div style="font-family:'Nunito',sans-serif;font-size:1.1rem;font-weight:800;color:var(--txt);">จัดการวัตถุดิบ</div>
      <div style="font-size:.72rem;color:var(--muted);">ทั้งหมด <?= number_format($total) ?> รายการ</div>
    </div>
    <button onclick="openModal()" class="btn btn-green">
      <i class="fas fa-plus"></i> เพิ่มวัตถุดิบ
    </button>
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
        <table id="ingredientsTable" class="table" style="width:100%;">
          <thead>
            <tr>
              <th style="width:60px;">ID</th>
              <th>ชื่อวัตถุดิบ</th>
              <th style="width:180px;">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ingredients as $ing): ?>
            <tr>
              <td><?= $ing['id'] ?></td>
              <td style="font-weight:600;"><?= htmlspecialchars($ing['name']) ?></td>
              <td>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                  <button onclick='openEditModal(<?= json_encode($ing, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="btn btn-sm" style="background:#fef3c7;color:#ca8a04;border:1px solid #fde68a;flex:1;justify-content:center;">
                    <i class="fas fa-edit"></i> แก้ไข
                  </button>
                  <form method="POST" style="margin:0; display:flex; flex:1;" onsubmit="return confirm('ยืนยันการลบวัตถุดิบนี้?');">
                    <input type="hidden" name="action" value="delete_ingredient">
                    <input type="hidden" name="ingredient_id" value="<?= $ing['id'] ?>">
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

<div id="addModal" class="modal">
  <div class="modal-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h3 style="font-family:'Nunito',sans-serif;font-size:1.1rem;font-weight:800;color:var(--txt);">เพิ่มวัตถุดิบใหม่</h3>
      <button type="button" onclick="closeModal()" style="width:32px;height:32px;border-radius:8px;border:none;background:var(--g50);color:var(--g600);cursor:pointer;"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_ingredient">
      <div class="form-group">
        <label class="form-label">ชื่อวัตถุดิบ</label>
        <input type="text" name="name" required class="form-input" placeholder="กรอกชื่อวัตถุดิบ...">
      </div>
      <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;padding:12px;margin-top:10px;"><i class="fas fa-save"></i> บันทึกข้อมูล</button>
    </form>
  </div>
</div>

<div id="editModal" class="modal">
  <div class="modal-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h3 style="font-family:'Nunito',sans-serif;font-size:1.1rem;font-weight:800;color:var(--txt);">แก้ไขวัตถุดิบ</h3>
      <button type="button" onclick="closeEditModal()" style="width:32px;height:32px;border-radius:8px;border:none;background:var(--g50);color:var(--g600);cursor:pointer;"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit_ingredient">
      <input type="hidden" name="id" id="edit_id">
      <div class="form-group">
        <label class="form-label">ชื่อวัตถุดิบ</label>
        <input type="text" name="name" id="edit_name" required class="form-input">
      </div>
      <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;padding:12px;margin-top:10px;"><i class="fas fa-save"></i> บันทึกการแก้ไข</button>
    </form>
  </div>
</div>

<script>
// จัดการ Modal เพิ่มข้อมูล
function openModal() { document.getElementById('addModal').classList.add('open'); }
function closeModal() { document.getElementById('addModal').classList.remove('open'); }

// จัดการ Modal แก้ไขข้อมูล (รับแค่ id และ name)
function openEditModal(data) {
  document.getElementById('edit_id').value = data.id;
  document.getElementById('edit_name').value = data.name;
  document.getElementById('editModal').classList.add('open');
}
function closeEditModal() { document.getElementById('editModal').classList.remove('open'); }

// ปิดเมื่อคลิกพื้นที่ด้านนอก Modal
document.addEventListener('click', function(e) {
  const addModal = document.getElementById('addModal');
  const editModal = document.getElementById('editModal');
  if (e.target === addModal) closeModal();
  if (e.target === editModal) closeEditModal();
});

$(document).ready(function() {
  $("#ingredientsTable").DataTable({
    pageLength: 50,
    order: [[1, "asc"]],
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