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

/* ══════════════════════════════════════════════════════════════════
   HANDLE ACTIONS
   ══════════════════════════════════════════════════════════════════ */
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'reset_password') {
        $target_id = (int)$_POST['user_id'];
        $new_password = 'password123'; // Default password
        $hash = hash('sha256', $new_password);
        
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $target_id);
        
        if ($stmt->execute()) {
            $success_msg = "รีเซ็ทรหัสผ่านเรียบร้อย (เป็นรหัส: password123)";
        } else {
            $error_msg = "เกิดข้อผิดพลาดในการรีเซ็ตรหัสผ่าน";
        }
    }
    
    // อัปเกรดให้สามารถบันทึกข้อมูลสุขภาพได้ด้วย
    if ($action === 'edit_user') {
        $target_id = (int)$_POST['user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        
        // ข้อมูลสุขภาพ
        $target_cal = (int)$_POST['daily_calorie_target'];
        $conditions = trim($_POST['health_conditions']);
        
        // 1. อัปเดตตาราง users
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $username, $email, $first_name, $last_name, $target_id);
        $user_updated = $stmt->execute();
        
        // 2. จัดการตาราง health_profiles (เช็คก่อนว่ามีข้อมูลหรือยัง)
        $check_hp = $conn->prepare("SELECT user_id FROM health_profiles WHERE user_id = ?");
        $check_hp->bind_param("i", $target_id);
        $check_hp->execute();
        $hp_exists = $check_hp->get_result()->num_rows > 0;
        
        if ($hp_exists) {
            // มีอยู่แล้ว -> อัปเดต
            $hp_stmt = $conn->prepare("UPDATE health_profiles SET daily_calorie_target = ?, health_conditions = ? WHERE user_id = ?");
            $hp_stmt->bind_param("isi", $target_cal, $conditions, $target_id);
            $hp_stmt->execute();
        } else {
            // ยังไม่มี -> สร้างใหม่
            $hp_stmt = $conn->prepare("INSERT INTO health_profiles (user_id, daily_calorie_target, health_conditions) VALUES (?, ?, ?)");
            $hp_stmt->bind_param("iis", $target_id, $target_cal, $conditions);
            $hp_stmt->execute();
        }
        
        if ($user_updated) {
            $success_msg = "อัปเดตข้อมูลผู้ใช้และข้อมูลสุขภาพเรียบร้อยแล้ว";
        } else {
            $error_msg = "เกิดข้อผิดพลาดในการแก้ไขข้อมูล";
        }
    }
}

/* ══════════════════════════════════════════════════════════════════
   FETCH USERS
   ══════════════════════════════════════════════════════════════════ */
$users_sql = "
    SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.created_at,
           hp.daily_calorie_target, hp.health_conditions,
           (SELECT COUNT(*) FROM meal_logs WHERE user_id = u.id) as meal_count
    FROM users u
    LEFT JOIN health_profiles hp ON u.id = hp.user_id
    WHERE u.email NOT LIKE '%@admin.com'
    ORDER BY u.created_at DESC
";
$users = $conn->query($users_sql)->fetch_all(MYSQLI_ASSOC);
$total = count($users);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการผู้ใช้ — Admin</title>
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
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<style>
:root{--g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;--g400:#4ade80;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--t400:#2dd4bf;--t500:#14b8a6;--t600:#0d9488;--bg:#f8faf9;--card:#fff;--bdr:#e8f0e9;--txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;--sb-w:260px;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,#c8e6c9 1px,transparent 1px);background-size:28px 28px;opacity:.35;}
.sidebar{width:var(--sb-w);min-height:100vh;background:#fff;border-right:1px solid #e5ede6;display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;z-index:100;box-shadow:4px 0 24px rgba(34,197,94,.06);}
.sb-logo{padding:24px 22px 20px;border-bottom:1px solid #e5ede6;display:flex;align-items:center;gap:11px;}
.sb-logo-icon{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#ef4444,#dc2626);display:flex;align-items:center;justify-content:center;font-size:1.2rem;box-shadow:0 4px 12px rgba(239,68,68,.35);}
.sb-logo-text{font-family:'Nunito',sans-serif;font-size:1.18rem;font-weight:800;color:#dc2626;letter-spacing:-.02em;line-height:1;}
.sb-logo-sub{font-size:.6rem;font-weight:600;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin-top:2px;}
.sb-nav{padding:6px 12px;display:flex;flex-direction:column;gap:2px;flex:1;overflow-y:auto;}
.nav-item{display:flex;align-items:center;gap:11px;padding:11px 14px;border-radius:12px;text-decoration:none;color:var(--sub);font-size:.82rem;font-weight:500;transition:all .18s;}
.nav-item:hover{background:#fef2f2;color:#dc2626;}
.nav-item.active{background:#fef2f2;color:#dc2626;font-weight:600;}
.nav-item.active .ni{background:#dc2626;color:#fff;}
.ni{width:34px;height:34px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;transition:all .18s;color:#dc2626;}
.page-wrap{margin-left:var(--sb-w);flex:1;position:relative;z-index:1;min-width:0;}
.topbar{height:70px;background:rgba(255,255,255,.98);backdrop-filter:blur(12px);border-bottom:1px solid var(--bdr);display:flex;align-items:center;padding:0 2.5rem;gap:14px;position:sticky;top:0;z-index:50;}

/* ปรับปรุงดีไซน์ตาราง */
.card { background:#fff; border:none; border-radius:24px; padding:28px; box-shadow:0 10px 30px rgba(0,0,0,.03); margin-bottom: 2rem;}
.table { width:100%; border-collapse: separate; border-spacing: 0; font-size:.85rem; }
.table th { text-align:left; padding:16px 14px; background:var(--g50); color:var(--g700); font-weight:700; border-bottom:2px solid var(--g200); font-size:.75rem; text-transform: uppercase; letter-spacing: 0.5px;}
.table td { padding:16px 14px; border-bottom:1px solid #f1f5f3; vertical-align: middle; }
.table th:first-child { border-top-left-radius: 12px; }
.table th:last-child { border-top-right-radius: 12px; }
.table tbody tr { transition: all 0.2s ease; }
.table tbody tr:hover { background-color: #fdfdfd; box-shadow: inset 0 0 0 9999px rgba(34,197,94,0.02); }

.badge { font-size:.7rem; font-weight:600; padding:6px 12px; border-radius:99px; display:inline-block; }
.badge-orange { background:#fff7ed; color:#ea580c; border:1px solid #fed7aa; }
.badge-green { background:var(--g50); color:var(--g700); border:1px solid var(--g200); }

.btn { padding:8px 14px; border-radius:10px; font-size:.78rem; font-weight:600; cursor:pointer; transition:all .2s; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:6px; border:none; }
.btn-sm { padding:6px 10px; font-size:.72rem; }
.btn:hover { transform: translateY(-1px); filter: brightness(0.95); }

/* DataTables Custom Styling */
.dataTables_wrapper { font-family:'Kanit',sans-serif!important; }
.dataTables_wrapper .dataTables_length select { border:1.5px solid var(--bdr); border-radius:10px; padding:6px 10px; font-size:.85rem; outline: none;}
.dataTables_wrapper .dataTables_length select:focus { border-color: var(--g500); }
.dataTables_wrapper .dataTables_filter input { border:1.5px solid var(--bdr); border-radius:10px; padding:8px 14px; font-size:.85rem; margin-left:8px; outline: none; transition: all 0.2s; }
.dataTables_wrapper .dataTables_filter input:focus { border-color: var(--g500); box-shadow: 0 0 0 3px rgba(34,197,94,.1); }
.dataTables_wrapper .dataTables_info { font-size:.8rem; color:var(--muted); padding-top:16px; }
.dataTables_wrapper .dataTables_paginate { padding-top:16px; }
.dataTables_wrapper .dataTables_paginate .paginate_button { border:1px solid var(--bdr); border-radius:10px; padding:6px 14px; margin:0 4px; font-size:.8rem; color:var(--sub)!important; transition: all 0.2s; }
.dataTables_wrapper .dataTables_paginate .paginate_button:hover { background:var(--g50)!important; border-color:var(--g300)!important; color:var(--g700)!important; }
.dataTables_wrapper .dataTables_paginate .paginate_button.current { background:var(--g500)!important; color:#fff!important; border-color:var(--g500)!important; box-shadow: 0 4px 10px rgba(34,197,94,.2); }
.dt-buttons { margin-bottom:16px; }
.dt-button { background:linear-gradient(135deg,var(--g500),var(--t500))!important; color:#fff!important; border:none!important; padding:8px 18px!important; border-radius:10px!important; font-size:.8rem!important; font-weight:600!important; margin-right:8px!important; font-family:'Kanit',sans-serif!important; box-shadow: 0 4px 12px rgba(34,197,94,.2)!important; transition: all 0.2s!important; }
.dt-button:hover { transform: translateY(-2px)!important; box-shadow: 0 6px 15px rgba(34,197,94,.3)!important; }

::-webkit-scrollbar{width:6px;}::-webkit-scrollbar-thumb{background:var(--g200);border-radius:99px;}

/* Input ใน Modal */
.form-input { width:100%; padding:10px 14px; border:1.5px solid var(--bdr); border-radius:10px; font-family:'Kanit',sans-serif; font-size:.88rem; outline: none; transition: all 0.2s; background: #fafafa;}
.form-input:focus { border-color: var(--g500); background: #fff; box-shadow: 0 0 0 3px rgba(34,197,94,.1); }
.form-label { display:block; font-size:.82rem; font-weight:600; color:var(--sub); margin-bottom:6px; }

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

<?php include '../includes/sidebar_admin.php' ?>

<div class="page-wrap">
  <header class="topbar">
    <div style="flex:1;">
      <div style="font-family:'Nunito',sans-serif;font-size:1.2rem;font-weight:800;color:#2b3452;">จัดการผู้ใช้ระบบ (Users)</div>
      <div style="font-size:.75rem;color:var(--muted);"><i class="fas fa-users me-1"></i> สมาชิกทั้งหมด <?= number_format($total) ?> คน</div>
    </div>
  </header>
  
  <main style="padding:2.5rem; max-width: 1400px; margin: 0 auto;">
    
    <?php if ($success_msg): ?>
    <div style="background:#dcfce7; border:none; color:#15803d; padding:14px 18px; border-radius:14px; margin-bottom:24px; font-size:.85rem; font-weight:500; display:flex; align-items:center; gap:10px; box-shadow: 0 4px 12px rgba(34,197,94,.1);">
      <i class="fas fa-check-circle" style="font-size: 1.1rem;"></i> <?= htmlspecialchars($success_msg) ?>
    </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
    <div style="background:#fee2e2; border:none; color:#b91c1c; padding:14px 18px; border-radius:14px; margin-bottom:24px; font-size:.85rem; font-weight:500; display:flex; align-items:center; gap:10px;">
      <i class="fas fa-exclamation-circle" style="font-size: 1.1rem;"></i> <?= htmlspecialchars($error_msg) ?>
    </div>
    <?php endif; ?>
    
    <div class="card">
      <div style="overflow-x:auto;">
        <table id="usersTable" class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Email</th>
              <th>ชื่อ-สกุล</th>
              <th>โรคประจำตัว</th>
              <th>Target (kcal)</th>
              <th>Meals</th>
              <th>วันที่สมัคร</th>
              <th style="text-align: right;">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td style="color:var(--muted); font-weight:600;">#<?= $u['id'] ?></td>
              <td>
                <div style="font-weight:600; color:var(--txt);"><?= htmlspecialchars($u['username']) ?></div>
              </td>
              <td style="font-size:.8rem;color:var(--muted);"><?= htmlspecialchars($u['email']) ?></td>
              <td style="font-weight:500;"><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></td>
              <td>
                <?php if ($u['health_conditions']): ?>
                <span class="badge badge-orange" title="<?= htmlspecialchars($u['health_conditions']) ?>">
                  <?= htmlspecialchars(mb_strimwidth($u['health_conditions'], 0, 25, '...', 'UTF-8')) ?>
                </span>
                <?php else: ?>
                <span style="color:var(--muted);font-size:.8rem;">-</span>
                <?php endif; ?>
              </td>
              <td><span style="font-weight:600; color:var(--g600);"><?= $u['daily_calorie_target'] ?: '-' ?></span></td>
              <td><span class="badge badge-green"><i class="fas fa-utensils me-1" style="font-size:.6rem;"></i> <?= $u['meal_count'] ?></span></td>
              <td data-order="<?= strtotime($u['created_at']) ?>" style="font-size:.8rem; color:var(--muted);">
                <?= date('j M Y', strtotime($u['created_at'])) ?>
              </td>
              <td style="text-align: right; min-width: 190px;">
                <div style="display:inline-flex; gap:6px;">
                  <button onclick="openEditModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['first_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['last_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['daily_calorie_target'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($u['health_conditions'] ?? '', ENT_QUOTES) ?>')" 
                          class="btn btn-sm" 
                          style="background:#f8faf9; color:var(--g700); border:1px solid var(--bdr);">
                    <i class="fas fa-user-edit"></i> แก้ไข
                  </button>

                  <form method="POST" style="margin:0;" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะรีเซ็ตรหัสผ่านของผู้ใช้นี้เป็น password123 ?');">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn btn-sm" style="background:#fffbeb; color:#d97706; border:1px solid #fde68a;">
                      <i class="fas fa-key"></i> Reset PW
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

<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;animation:fadeIn 0.2s;">
  <div style="background:#fff;border-radius:24px;padding:32px;max-width:550px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 40px rgba(0,0,0,.15);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;border-bottom:1px solid var(--bdr);padding-bottom:16px;">
      <h3 style="font-family:'Nunito',sans-serif;font-size:1.25rem;font-weight:800;color:#2b3452;margin:0;">
        <i class="fas fa-user-edit" style="color:var(--g500);margin-right:8px;"></i> แก้ไขข้อมูลผู้ใช้
      </h3>
      <button onclick="closeEditModal()" style="width:36px;height:36px;border-radius:10px;border:none;background:#f1f5f3;color:var(--muted);cursor:pointer;font-size:1rem;transition:all 0.2s;">
        <i class="fas fa-times"></i>
      </button>
    </div>
    
    <form method="POST" id="editForm">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="user_id" id="edit_user_id">
      
      <h4 style="font-size:1rem; font-weight:700; color:var(--txt); margin-bottom:12px;">ข้อมูลบัญชี (Account)</h4>
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
        <div>
          <label class="form-label">Username</label>
          <input type="text" name="username" id="edit_username" class="form-input" required>
        </div>
        <div>
          <label class="form-label">Email</label>
          <input type="email" name="email" id="edit_email" class="form-input" required>
        </div>
      </div>
      
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:24px;">
        <div>
          <label class="form-label">ชื่อ</label>
          <input type="text" name="first_name" id="edit_first_name" class="form-input" required>
        </div>
        <div>
          <label class="form-label">นามสกุล</label>
          <input type="text" name="last_name" id="edit_last_name" class="form-input" required>
        </div>
      </div>

      <h4 style="font-size:1rem; font-weight:700; color:var(--txt); margin-bottom:12px; border-top:1px solid var(--bdr); padding-top:16px;">ข้อมูลสุขภาพ (Health Data)</h4>
      <div style="display:grid; grid-template-columns: 1fr 1.5fr; gap:16px; margin-bottom:24px;">
        <div>
          <label class="form-label">เป้าหมาย (kcal/วัน)</label>
          <input type="number" name="daily_calorie_target" id="edit_target" class="form-input" placeholder="เช่น 2000">
        </div>
        <div>
          <label class="form-label">โรคประจำตัว</label>
          <input type="text" name="health_conditions" id="edit_conditions" class="form-input" placeholder="เช่น เบาหวาน, ความดัน (หากไม่มีเว้นว่าง)">
        </div>
      </div>
      
      <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:32px;">
        <button type="button" onclick="closeEditModal()" class="btn" style="background:#f1f5f3; color:var(--sub); padding:12px 20px;">ยกเลิก</button>
        <button type="submit" class="btn" style="background:linear-gradient(135deg,var(--g500),var(--t500)); color:#fff; padding:12px 24px; box-shadow:0 4px 12px rgba(34,197,94,.2);">
          <i class="fas fa-save"></i> บันทึกข้อมูล
        </button>
      </div>
    </form>
  </div>
</div>

<style>
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
#editModal button[onclick="closeEditModal()"]:hover {
    background: #fee2e2 !important;
    color: #dc2626 !important;
}
</style>

<script>
$(document).ready(function() {
  $('#usersTable').DataTable({
    pageLength: 25,
    order: [[0, 'desc']],
    language: {
      search: "",
      searchPlaceholder: "🔍 ค้นหาผู้ใช้...",
      lengthMenu: "แสดง _MENU_ รายการ",
      info: "แสดง _START_ ถึง _END_ จาก _TOTAL_ คน",
      infoEmpty: "ไม่มีข้อมูล",
      infoFiltered: "(กรองจาก _MAX_ คนทั้งหมด)",
      paginate: {
        first: "แรก",
        last: "สุดท้าย",
        next: "ถัดไป",
        previous: "ก่อนหน้า"
      }
    },
    dom: "<'row'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
         "<'row'<'col-sm-12'tr>>" +
         "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
    buttons: [
      {
        extend: "excel",
        text: '<i class="fas fa-file-excel"></i> ส่งออก Excel',
        exportOptions: { columns: ":not(:last-child)" },
        className: 'dt-button'
      },
      {
        extend: "csv",
        text: '<i class="fas fa-file-csv"></i> ส่งออก CSV',
        charset: 'utf-8',
        bom: true,
        exportOptions: { columns: ":not(:last-child)" },
        className: 'dt-button'
      },
    ]
  });
});

// อัปเดตฟังก์ชันรับพารามิเตอร์ข้อมูลสุขภาพเพิ่ม
function openEditModal(id, username, email, first_name, last_name, target, conditions) {
  document.getElementById('edit_user_id').value = id;
  document.getElementById('edit_username').value = username;
  document.getElementById('edit_email').value = email;
  document.getElementById('edit_first_name').value = first_name;
  document.getElementById('edit_last_name').value = last_name;
  
  // ยัดข้อมูลสุขภาพลงไปใน Form
  document.getElementById('edit_target').value = target;
  document.getElementById('edit_conditions').value = conditions;
  
  document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
}

// Close modal on outside click
document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) closeEditModal();
});
</script>

</body>
</html>