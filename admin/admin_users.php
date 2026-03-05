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
            $success_msg = "รีเซ็ทรหัสผ่านเรียบร้อย (password123)";
        } else {
            $error_msg = "เกิดข้อผิดพลาด";
        }
    }
    
    if ($action === 'edit_user') {
        $target_id = (int)$_POST['user_id'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $username, $email, $first_name, $last_name, $target_id);
        
        if ($stmt->execute()) {
            $success_msg = "แก้ไขข้อมูลผู้ใช้เรียบร้อย";
        } else {
            $error_msg = "เกิดข้อผิดพลาด";
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
<!-- DataTables -->
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
:root{--g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;--g400:#4ade80;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--t400:#2dd4bf;--t500:#14b8a6;--t600:#0d9488;--bg:#f5f8f5;--card:#fff;--bdr:#e8f0e9;--txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;--sb-w:260px;}
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
.topbar{height:66px;background:rgba(255,255,255,.95);backdrop-filter:blur(12px);border-bottom:1px solid #e5ede6;display:flex;align-items:center;padding:0 2.5rem;gap:14px;position:sticky;top:0;z-index:50;}
.card{background:#fff;border:1px solid var(--bdr);border-radius:18px;padding:22px;}
.table{width:100%;font-size:.8rem;}
.table th{text-align:left;padding:12px 10px;background:var(--g50);color:var(--sub);font-weight:600;border-bottom:2px solid var(--g200);font-size:.75rem;}
.table td{padding:12px 10px;border-bottom:1px solid var(--bdr);}
.table tr:hover{background:var(--g50);}
.badge{font-size:.68rem;font-weight:700;padding:4px 10px;border-radius:8px;display:inline-block;}
.btn{padding:6px 12px;border-radius:8px;font-size:.75rem;font-weight:600;cursor:pointer;transition:all .18s;text-decoration:none;display:inline-flex;align-items:center;gap:5px;border:none;}
.btn-sm{padding:5px 10px;font-size:.7rem;}
.search-box{display:flex;gap:10px;background:#fff;border:1.5px solid var(--bdr);border-radius:12px;padding:0 14px;height:42px;align-items:center;}
.search-box input{border:none;outline:none;background:transparent;font-family:'Kanit',sans-serif;font-size:.82rem;color:var(--txt);flex:1;}
.pagination{display:flex;gap:6px;justify-content:center;align-items:center;margin-top:20px;}
.page-btn{width:36px;height:36px;border-radius:10px;border:1px solid var(--bdr);background:#fff;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:600;color:var(--sub);cursor:pointer;transition:all .18s;text-decoration:none;}
.page-btn:hover{background:var(--g50);border-color:var(--g300);}
.page-btn.active{background:var(--g500);color:#fff;border-color:var(--g500);}

/* DataTables Custom Styling */
.dataTables_wrapper{font-family:'Kanit',sans-serif!important;}
.dataTables_wrapper .dataTables_length select{border:1.5px solid var(--bdr);border-radius:8px;padding:4px 8px;font-size:.8rem;}
.dataTables_wrapper .dataTables_filter input{border:1.5px solid var(--bdr);border-radius:8px;padding:6px 12px;font-size:.8rem;margin-left:8px;}
.dataTables_wrapper .dataTables_info{font-size:.75rem;color:var(--muted);padding-top:12px;}
.dataTables_wrapper .dataTables_paginate{padding-top:12px;}
.dataTables_wrapper .dataTables_paginate .paginate_button{border:1px solid var(--bdr);border-radius:8px;padding:4px 12px;margin:0 3px;font-size:.75rem;color:var(--sub)!important;}
.dataTables_wrapper .dataTables_paginate .paginate_button:hover{background:var(--g50)!important;border-color:var(--g300)!important;}
.dataTables_wrapper .dataTables_paginate .paginate_button.current{background:var(--g500)!important;color:#fff!important;border-color:var(--g500)!important;}
.dt-buttons{margin-bottom:12px;}
.dt-button{background:var(--g500)!important;color:#fff!important;border:none!important;padding:8px 16px!important;border-radius:8px!important;font-size:.78rem!important;font-weight:600!important;margin-right:6px!important;font-family:'Kanit',sans-serif!important;}
.dt-button:hover{opacity:.88!important;}

::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-thumb{background:var(--g200);border-radius:99px;}
</style>
</head>
<body>

<?php include '../includes/sidebar_admin.php' ?>

<div class="page-wrap">
  <header class="topbar">
    <div style="flex:1;">
      <div style="font-family:'Nunito',sans-serif;font-size:1.1rem;font-weight:800;color:var(--txt);">จัดการผู้ใช้</div>
      <div style="font-size:.72rem;color:var(--muted);">ทั้งหมด <?= number_format($total) ?> คน</div>
    </div>
  </header>
  
  <main style="padding:2rem 2.5rem;">
    
    <?php if ($success_msg): ?>
    <div style="background:#f0fdf4;border:1.5px solid var(--g300);color:var(--g700);padding:12px 16px;border-radius:12px;margin-bottom:20px;font-size:.82rem;">
      ✅ <?= htmlspecialchars($success_msg) ?>
    </div>
    <?php endif; ?>
    
    <div class="card">
      <div style="overflow-x:auto;">
        <table id="usersTable" class="table" style="width:100%;">
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
              <th>จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td><?= $u['id'] ?></td>
              <td style="font-weight:600;"><?= htmlspecialchars($u['username']) ?></td>
              <td style="font-size:.75rem;color:var(--muted);"><?= htmlspecialchars($u['email']) ?></td>
              <td><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></td>
              <td>
                <?php if ($u['health_conditions']): ?>
                <span class="badge" style="background:#fff7ed;color:#ea580c;border:1px solid #fed7aa;">
                  <?= htmlspecialchars(mb_strimwidth($u['health_conditions'], 0, 25, '...', 'UTF-8')) ?>
                </span>
                <?php else: ?>
                <span style="color:var(--muted);font-size:.75rem;">-</span>
                <?php endif; ?>
              </td>
              <td><?= $u['daily_calorie_target'] ?: '-' ?></td>
              <td><span class="badge" style="background:var(--g50);color:var(--g700);"><?= $u['meal_count'] ?></span></td>
              <td data-order="<?= strtotime($u['created_at']) ?>"><?= date('j M Y', strtotime($u['created_at'])) ?></td>
              <td style="min-width: 170px;">
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                  <button onclick="openEditModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['first_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['last_name'], ENT_QUOTES) ?>')" 
                          class="btn btn-sm" 
                          style="background:var(--g50);color:var(--g600);border:1px solid var(--g200);flex:1;justify-content:center;white-space:nowrap;margin:0;">
                    <i class="fas fa-edit"></i> แก้ไข
                  </button>

                  <form method="POST" style="margin:0; display:flex; flex:1;" onsubmit="return confirm('รีเซ็ทรหัสผ่านเป็น password123?');">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn btn-sm" style="background:#fef3c7;color:#ca8a04;border:1px solid #fde68a;width:100%;justify-content:center;white-space:nowrap;margin:0;">
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

<!-- Edit User Modal -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:20px;padding:28px;max-width:500px;width:90%;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h3 style="font-family:'Nunito',sans-serif;font-size:1.1rem;font-weight:800;color:var(--txt);">แก้ไขข้อมูลผู้ใช้</h3>
      <button onclick="closeEditModal()" style="width:32px;height:32px;border-radius:8px;border:none;background:var(--g50);color:var(--g600);cursor:pointer;font-size:.9rem;">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST" id="editForm">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="user_id" id="edit_user_id">
      
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:.78rem;font-weight:600;color:var(--sub);margin-bottom:6px;">Username</label>
        <input type="text" name="username" id="edit_username" required style="width:100%;padding:10px 14px;border:1.5px solid var(--bdr);border-radius:10px;font-family:'Kanit',sans-serif;font-size:.82rem;">
      </div>
      
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:.78rem;font-weight:600;color:var(--sub);margin-bottom:6px;">Email</label>
        <input type="email" name="email" id="edit_email" required style="width:100%;padding:10px 14px;border:1.5px solid var(--bdr);border-radius:10px;font-family:'Kanit',sans-serif;font-size:.82rem;">
      </div>
      
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:.78rem;font-weight:600;color:var(--sub);margin-bottom:6px;">ชื่อ</label>
        <input type="text" name="first_name" id="edit_first_name" required style="width:100%;padding:10px 14px;border:1.5px solid var(--bdr);border-radius:10px;font-family:'Kanit',sans-serif;font-size:.82rem;">
      </div>
      
      <div style="margin-bottom:20px;">
        <label style="display:block;font-size:.78rem;font-weight:600;color:var(--sub);margin-bottom:6px;">นามสกุล</label>
        <input type="text" name="last_name" id="edit_last_name" required style="width:100%;padding:10px 14px;border:1.5px solid var(--bdr);border-radius:10px;font-family:'Kanit',sans-serif;font-size:.82rem;">
      </div>
      
      <button type="submit" style="width:100%;padding:12px;background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;border:none;border-radius:10px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:'Kanit',sans-serif;">
        <i class="fas fa-save"></i> บันทึก
      </button>
    </form>
  </div>
</div>

<script>
$(document).ready(function() {
  $('#usersTable').DataTable({
    pageLength: 25,
    order: [[0, 'desc']],
    language: {
      search: "ค้นหา:",
      lengthMenu: "แสดง _MENU_ รายการ",
      info: "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
      infoEmpty: "ไม่มีข้อมูล",
      infoFiltered: "(กรองจาก _MAX_ รายการทั้งหมด)",
      paginate: {
        first: "แรก",
        last: "สุดท้าย",
        next: "ถัดไป",
        previous: "ก่อนหน้า"
      }
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

function openEditModal(id, username, email, first_name, last_name) {
  document.getElementById('edit_user_id').value = id;
  document.getElementById('edit_username').value = username;
  document.getElementById('edit_email').value = email;
  document.getElementById('edit_first_name').value = first_name;
  document.getElementById('edit_last_name').value = last_name;
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