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

/* HANDLE ACTIONS */
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ----- 1. รีเซ็ตรหัสผ่าน -----
    if ($action === 'reset_password') {
        $target_id = (int)$_POST['user_id'];
        $new_password = 'password123'; // Default password
        $hash = hash('sha256', $new_password);
        
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $target_id);
        
        if ($stmt->execute()) {
            $success_msg = "รีเซ็ตรหัสผ่านเรียบร้อย (เป็นรหัส: password123)";
        } else {
            $error_msg = "เกิดข้อผิดพลาดในการรีเซ็ตรหัสผ่าน";
        }
    }
    
    // ----- 2. แก้ไขข้อมูลผู้ใช้ -----
    if ($action === 'edit_user') {
        $target_id = (int)$_POST['user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        
        // ข้อมูลสุขภาพ
        $target_cal = (int)$_POST['daily_calorie_target'];
        $conditions = trim($_POST['health_conditions']);
        
        // อัปเดตตาราง users
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $username, $email, $first_name, $last_name, $target_id);
        $user_updated = $stmt->execute();
        
        // จัดการตาราง health_profiles
        $check_hp = $conn->prepare("SELECT user_id FROM health_profiles WHERE user_id = ?");
        $check_hp->bind_param("i", $target_id);
        $check_hp->execute();
        $hp_exists = $check_hp->get_result()->num_rows > 0;
        
        if ($hp_exists) {
            $hp_stmt = $conn->prepare("UPDATE health_profiles SET daily_calorie_target = ?, health_conditions = ? WHERE user_id = ?");
            $hp_stmt->bind_param("isi", $target_cal, $conditions, $target_id);
            $hp_stmt->execute();
        } else {
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

    // ----- 3. ลบผู้ใช้ -----
    if ($action === 'delete_user') {
        $target_id = (int)$_POST['user_id'];
        
        $conn->query("DELETE FROM health_profiles WHERE user_id = $target_id");
        $conn->query("DELETE FROM ai_saved_menus WHERE user_id = $target_id");
        $conn->query("DELETE FROM meal_logs WHERE user_id = $target_id");
        $conn->query("DELETE FROM chat_logs WHERE user_id = $target_id");
        $conn->query("DELETE FROM user_allergies WHERE user_id = $target_id");
        
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $target_id);
        
        if ($stmt->execute()) {
            $success_msg = "ลบผู้ใช้และข้อมูลที่เกี่ยวข้องออกจากระบบเรียบร้อยแล้ว";
        } else {
            $error_msg = "เกิดข้อผิดพลาดในการลบผู้ใช้";
        }
    }
    
    // ----- 4. เปลี่ยนสถานะ User ↔ Admin -----
    if ($action === 'toggle_role') {
        $target_id = (int)$_POST['user_id'];
        
        // เช็คว่าเป็น admin หรือ user
        $check_stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $check_stmt->bind_param("i", $target_id);
        $check_stmt->execute();
        $current_role = $check_stmt->get_result()->fetch_assoc()['role'];
        
        // Toggle: 0 → 1 หรือ 1 → 0
        $new_role = ($current_role == 1) ? 0 : 1;
        
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_role, $target_id);
        
        if ($stmt->execute()) {
            $role_text = ($new_role == 1) ? 'Admin' : 'User';
            $success_msg = "เปลี่ยนสถานะเป็น $role_text เรียบร้อยแล้ว";
        } else {
            $error_msg = "เกิดข้อผิดพลาดในการเปลี่ยนสถานะ";
        }
    }
}

/* FETCH USERS */
// แสดงทุกคน (รวม Admin)
$users_sql = "
    SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.role, u.created_at,
           hp.daily_calorie_target, hp.health_conditions,
           (SELECT COUNT(*) FROM meal_logs WHERE user_id = u.id) as meal_count
    FROM users u
    LEFT JOIN health_profiles hp ON u.id = hp.user_id
    ORDER BY u.role DESC, u.created_at DESC
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

<style>
:root{--g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;--g400:#4ade80;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--t400:#2dd4bf;--t500:#14b8a6;--t600:#0d9488;--bg:#f8faf9;--card:#fff;--bdr:#e8f0e9;--txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;--sb-w:260px;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,#c8e6c9 1px,transparent 1px);background-size:28px 28px;opacity:.35;}
.page-wrap{margin-left:var(--sb-w);flex:1;position:relative;z-index:1;min-width:0;transition: margin-left 0.3s ease;}
.topbar{height:70px;background:rgba(255,255,255,.98);backdrop-filter:blur(12px);border-bottom:1px solid var(--bdr);display:flex;align-items:center;padding:0 2.5rem;gap:14px;position:sticky;top:0;z-index:50;}

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

/* ปรับปรุงดีไซน์ตารางให้อ่านง่าย สมดุล */
.card { background:#fff; border:1px solid rgba(0,0,0,.04); border-radius:24px; padding:32px; box-shadow:0 12px 36px rgba(0,0,0,.03); margin-bottom: 2rem;}
.table { width:100%; border-collapse: separate; border-spacing: 0; font-size:.85rem; }
.table th { text-align:left; padding:18px 16px; background:var(--g50); color:var(--g700); font-weight:700; border-bottom:2px solid var(--g200); font-size:.78rem; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap;}
.table td { padding:16px; border-bottom:1px solid #f1f5f3; vertical-align: middle; }
.table th:first-child { border-top-left-radius: 14px; }
.table th:last-child { border-top-right-radius: 14px; }
.table tbody tr { transition: all 0.2s ease; }
.table tbody tr:hover { background-color: #fcfdfc; box-shadow: inset 4px 0 0 var(--g400); }

.badge { font-size:.7rem; font-weight:600; padding:6px 14px; border-radius:99px; display:inline-block; white-space: nowrap; }
.badge-orange { background:#fff7ed; color:#ea580c; border:1px solid #fed7aa; }
.badge-green { background:var(--g50); color:var(--g700); border:1px solid var(--g200); }

.btn { padding:10px 16px; border-radius:12px; font-size:.8rem; font-weight:600; cursor:pointer; transition:all .2s; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:8px; border:none; }
.btn-sm { padding:8px 12px; font-size:.75rem; border-radius:10px; }
.btn:hover { transform: translateY(-2px); filter: brightness(0.96); }

/* ✨ Custom DataTables Styling (ให้สมดุลกับ Tailwind) */
.dataTables_wrapper { font-family:'Kanit',sans-serif!important; }
.dt-top-wrap { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; }
.dt-bottom-wrap { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--bdr); }

.dataTables_wrapper .dataTables_length select { border:1.5px solid var(--bdr); border-radius:10px; padding:8px 14px; font-size:.85rem; outline: none; background: #fff;}
.dataTables_wrapper .dataTables_length select:focus { border-color: var(--g500); }
.dataTables_wrapper .dataTables_filter input { border:1.5px solid var(--bdr); border-radius:12px; padding:10px 16px; font-size:.85rem; margin-left:8px; outline: none; transition: all 0.2s; width: 250px; background: #fafafa;}
.dataTables_wrapper .dataTables_filter input:focus { border-color: var(--g500); background: #fff; box-shadow: 0 0 0 3px rgba(34,197,94,.1); }
.dataTables_wrapper .dataTables_info { font-size:.82rem; color:var(--sub); padding-top:0 !important; font-weight: 500;}
.dataTables_wrapper .dataTables_paginate { padding-top:0 !important; }
.dataTables_wrapper .dataTables_paginate .paginate_button { border:1px solid var(--bdr) !important; border-radius:10px !important; padding:6px 14px !important; margin:0 4px !important; font-size:.82rem !important; font-weight: 500 !important; color:var(--sub)!important; transition: all 0.2s !important; background: #fff !important;}
.dataTables_wrapper .dataTables_paginate .paginate_button:hover { background:var(--g50)!important; border-color:var(--g300)!important; color:var(--g700)!important; }
.dataTables_wrapper .dataTables_paginate .paginate_button.current, .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover { background:linear-gradient(135deg,var(--g500),var(--t500))!important; color:#fff!important; border:none!important; box-shadow: 0 4px 12px rgba(34,197,94,.25) !important; }

.dt-buttons { display: flex; gap: 8px; }
.dt-button { background:linear-gradient(135deg,var(--g500),var(--t500))!important; color:#fff!important; border:none!important; padding:10px 20px!important; border-radius:12px!important; font-size:.82rem!important; font-weight:600!important; margin-right:0!important; font-family:'Kanit',sans-serif!important; box-shadow: 0 4px 14px rgba(34,197,94,.2)!important; transition: all 0.2s!important; display: inline-flex; align-items: center; gap: 6px;}
.dt-button:hover { transform: translateY(-2px)!important; box-shadow: 0 6px 18px rgba(34,197,94,.3)!important; }

::-webkit-scrollbar{width:6px;}::-webkit-scrollbar-thumb{background:var(--g200);border-radius:99px;}

/* Input ใน Modal */
.form-input { width:100%; padding:12px 16px; border:1.5px solid var(--bdr); border-radius:12px; font-family:'Kanit',sans-serif; font-size:.88rem; outline: none; transition: all 0.2s; background: #fafafa;}
.form-input:focus { border-color: var(--g500); background: #fff; box-shadow: 0 0 0 4px rgba(34,197,94,.1); }
.form-label { display:block; font-size:.85rem; font-weight:600; color:var(--sub); margin-bottom:8px; }
.swal2-container { font-family: 'Kanit', sans-serif !important; }
</style>
</head>
<body>

<?php include '../includes/sidebar_admin.php' ?>

<div class="page-wrap">
  <header class="topbar">
    
    <button class="hamburger">
      <i class="fas fa-bars"></i>
    </button>
    <div style="flex:1;">
      <div style="font-family:'Nunito',sans-serif;font-size:1.25rem;font-weight:800;color:#2b3452;">จัดการผู้ใช้ระบบ</div>
      <div style="font-size:.78rem;color:var(--muted);font-weight:500;"><i class="fas fa-users me-1"></i> สมาชิกทั้งหมด <?= number_format($total) ?> คน</div>
    </div>
  </header>
  
  <main style="padding:2.5rem; max-width: 1400px; margin: 0 auto;">
    
    <div class="card">
      <div style="overflow-x:auto;">
        <table id="usersTable" class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Email</th>
              <th>ชื่อ-สกุล</th>
              <th>สถานะ</th>
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
                <div style="font-weight:700; color:var(--txt); font-size:.9rem;"><?= htmlspecialchars($u['username']) ?></div>
              </td>
              <td style="font-size:.82rem;color:var(--sub);"><?= htmlspecialchars($u['email']) ?></td>
              <td style="font-weight:500; color:var(--txt);"><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></td>
              <td>
                <?php if ($u['role'] == 1): ?>
                <span class="badge" style="background:#fef3c7;color:#92400e;border:1px solid #fbbf24;">
                  <i class="fas fa-crown"></i> Admin
                </span>
                <?php else: ?>
                <span class="badge" style="background:#dbeafe;color:#1e40af;border:1px solid #93c5fd;">
                  <i class="fas fa-user"></i> User
                </span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($u['health_conditions']): ?>
                <span class="badge badge-orange" title="<?= htmlspecialchars($u['health_conditions']) ?>">
                  <?= htmlspecialchars(mb_strimwidth($u['health_conditions'], 0, 25, '...', 'UTF-8')) ?>
                </span>
                <?php else: ?>
                <span style="color:var(--muted);font-size:.8rem;font-weight:600;">-</span>
                <?php endif; ?>
              </td>
              <td><span style="font-weight:700; color:var(--g600);"><?= $u['daily_calorie_target'] ?: '-' ?></span></td>
              <td><span class="badge badge-green"><i class="fas fa-utensils me-1" style="font-size:.6rem;"></i> <?= $u['meal_count'] ?></span></td>
              <td data-order="<?= strtotime($u['created_at']) ?>" style="font-size:.82rem; color:var(--muted); font-weight:500;">
                <?= date('j M Y', strtotime($u['created_at'])) ?>
              </td>
              <td style="text-align: right; min-width: 330px;">
                <div style="display:inline-flex; gap:6px; flex-wrap:wrap; justify-content:flex-end;">
                  
                  <button type="button" onclick="openEditModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['first_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['last_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['daily_calorie_target'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($u['health_conditions'] ?? '', ENT_QUOTES) ?>')" class="btn btn-sm" style="background:#f1f5f3; color:var(--sub); border:1px solid var(--bdr);">
                    <i class="fas fa-user-edit"></i> แก้ไข
                  </button>
                  
                  <button type="button" onclick="confirmToggleRole(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', <?= $u['role'] ?>)" class="btn btn-sm" style="background:#f3e8ff; color:#7c3aed; border:1px solid #e9d5ff;">
                    <i class="fas fa-user-shield"></i> <?= $u['role'] == 1 ? 'User' : 'Admin' ?>
                  </button>

                  <button type="button" onclick="confirmReset(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')" class="btn btn-sm" style="background:#fff7ed; color:#d97706; border:1px solid #ffedd5;">
                    <i class="fas fa-key"></i> Reset PW
                  </button>

                  <button type="button" onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')" class="btn btn-sm" style="background:#fef2f2; color:#dc2626; border:1px solid #fee2e2;">
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

<form id="actionForm" method="POST" style="display:none;">
    <input type="hidden" name="action" id="form_action">
    <input type="hidden" name="user_id" id="form_user_id">
</form>

<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(6px);z-index:9999;align-items:center;justify-content:center;animation:fadeIn 0.25s;">
  <div style="background:#fff;border-radius:24px;padding:36px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 24px 48px rgba(0,0,0,.2);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;border-bottom:1px solid var(--bdr);padding-bottom:16px;">
      <h3 style="font-family:'Nunito',sans-serif;font-size:1.35rem;font-weight:800;color:#2b3452;margin:0;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-user-edit" style="color:var(--g500);"></i> แก้ไขข้อมูลผู้ใช้
      </h3>
      <button type="button" onclick="closeEditModal()" style="width:36px;height:36px;border-radius:10px;border:none;background:#f1f5f3;color:var(--muted);cursor:pointer;font-size:1rem;transition:all 0.2s;">
        <i class="fas fa-times"></i>
      </button>
    </div>
    
    <form method="POST" id="editForm">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="user_id" id="edit_user_id">
      
      <div style="background:#f8faf9; border:1px solid var(--bdr); border-radius:16px; padding:20px; margin-bottom:24px;">
        <h4 style="font-size:1rem; font-weight:700; color:var(--txt); margin-bottom:16px; display:flex; align-items:center; gap:8px;">
          <i class="fas fa-id-card text-gray-400"></i> ข้อมูลบัญชี (Account)
        </h4>
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
        
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
          <div>
            <label class="form-label">ชื่อ</label>
            <input type="text" name="first_name" id="edit_first_name" class="form-input" required>
          </div>
          <div>
            <label class="form-label">นามสกุล</label>
            <input type="text" name="last_name" id="edit_last_name" class="form-input" required>
          </div>
        </div>
      </div>

      <div style="background:#f8faf9; border:1px solid var(--bdr); border-radius:16px; padding:20px; margin-bottom:28px;">
        <h4 style="font-size:1rem; font-weight:700; color:var(--txt); margin-bottom:16px; display:flex; align-items:center; gap:8px;">
          <i class="fas fa-heartbeat text-gray-400"></i> ข้อมูลสุขภาพ (Health Data)
        </h4>
        <div style="display:grid; grid-template-columns: 1fr 1.5fr; gap:16px;">
          <div>
            <label class="form-label">เป้าหมาย (kcal/วัน)</label>
            <input type="number" name="daily_calorie_target" id="edit_target" class="form-input" placeholder="เช่น 2000">
          </div>
          <div>
            <label class="form-label">โรคประจำตัว</label>
            <input type="text" name="health_conditions" id="edit_conditions" class="form-input" placeholder="เช่น เบาหวาน, ความดัน (หากไม่มีเว้นว่าง)">
          </div>
        </div>
      </div>
      
      <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:32px; border-top:1px solid var(--bdr); padding-top:20px;">
        <button type="button" onclick="closeEditModal()" class="btn" style="background:#f1f5f3; color:var(--sub); padding:12px 24px;">ยกเลิก</button>
        <button type="submit" class="btn" style="background:linear-gradient(135deg,var(--g500),var(--t500)); color:#fff; padding:12px 28px; box-shadow:0 4px 14px rgba(34,197,94,.25);">
          <i class="fas fa-save"></i> บันทึกข้อมูลผู้ใช้
        </button>
      </div>
    </form>
  </div>
</div>

<style>
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.98); }
  to { opacity: 1; transform: scale(1); }
}
#editModal button[onclick="closeEditModal()"]:hover {
    background: #fee2e2 !important;
    color: #dc2626 !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($success_msg): ?>
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ!',
            text: '<?= htmlspecialchars($success_msg) ?>',
            confirmButtonColor: '#22c55e',
            confirmButtonText: 'ตกลง',
            customClass: { popup: 'swal2-custom-popup' }
        });
    <?php endif; ?>

    <?php if ($error_msg): ?>
        Swal.fire({
            icon: 'error',
            title: 'พบข้อผิดพลาด!',
            text: '<?= htmlspecialchars($error_msg) ?>',
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'ตกลง',
            customClass: { popup: 'swal2-custom-popup' }
        });
    <?php endif; ?>
});

function confirmDelete(id, username) {
    Swal.fire({
        title: 'ยืนยันการลบผู้ใช้',
        html: `คุณต้องการลบผู้ใช้ <b>${username}</b> ใช่หรือไม่?<br><span style="color:#dc2626; font-size:0.9rem;">(ข้อมูลประวัติอาหารและการแชทจะถูกลบทั้งหมด)</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#9ca3af',
        confirmButtonText: 'ลบผู้ใช้!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('form_action').value = 'delete_user';
            document.getElementById('form_user_id').value = id;
            document.getElementById('actionForm').submit();
        }
    });
}

function confirmReset(id, username) {
    Swal.fire({
        title: 'รีเซ็ตรหัสผ่าน',
        html: `คุณแน่ใจหรือไม่ที่จะรีเซ็ตรหัสผ่านของ <b>${username}</b><br>ให้เป็นรหัสเริ่มต้น (password123)`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d97706',
        cancelButtonColor: '#9ca3af',
        confirmButtonText: 'รีเซ็ตเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('form_action').value = 'reset_password';
            document.getElementById('form_user_id').value = id;
            document.getElementById('actionForm').submit();
        }
    });
}

function confirmToggleRole(id, username, currentRole) {
    const newRole = currentRole == 1 ? 'User' : 'Admin';
    const newRoleColor = currentRole == 1 ? '#1e40af' : '#92400e';
    const icon = currentRole == 1 ? 'fa-user' : 'fa-crown';
    
    Swal.fire({
        title: 'เปลี่ยนสถานะผู้ใช้',
        html: `คุณต้องการเปลี่ยนสถานะของ <b>${username}</b><br>เป็น <span style="color:${newRoleColor};"><i class="fas ${icon}"></i> ${newRole}</span> ใช่หรือไม่?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#7c3aed',
        cancelButtonColor: '#9ca3af',
        confirmButtonText: `เปลี่ยนเป็น ${newRole}!`,
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('form_action').value = 'toggle_role';
            document.getElementById('form_user_id').value = id;
            document.getElementById('actionForm').submit();
        }
    });
}

$(document).ready(function() {
  $('#usersTable').DataTable({
    pageLength: 25,
    order: [[0, 'desc']],
    language: {
      search: "",
      searchPlaceholder: "ค้นหาผู้ใช้...",
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
    // ✨ ปรับแก้ Layout ของ DataTables ให้เข้ากับ Tailwind สมดุลขึ้น
    dom: "<'dt-top-wrap'Bf>rt<'dt-bottom-wrap'lip><'clear'>",
    buttons: [
      {
        extend: "excel",
        text: '<i class="fas fa-file-excel"></i> Excel',
        exportOptions: { columns: ":not(:last-child)" },
        className: 'dt-button'
      },
      {
        extend: "csv",
        text: '<i class="fas fa-file-csv"></i> CSV',
        charset: 'utf-8',
        bom: true,
        exportOptions: { columns: ":not(:last-child)" },
        className: 'dt-button'
      },
    ]
  });
});

function openEditModal(id, username, email, first_name, last_name, target, conditions) {
  document.getElementById('edit_user_id').value = id;
  document.getElementById('edit_username').value = username;
  document.getElementById('edit_email').value = email;
  document.getElementById('edit_first_name').value = first_name;
  document.getElementById('edit_last_name').value = last_name;
  
  document.getElementById('edit_target').value = target;
  document.getElementById('edit_conditions').value = conditions;
  
  document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
}

document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) closeEditModal();
});
</script>

</body>
</html>