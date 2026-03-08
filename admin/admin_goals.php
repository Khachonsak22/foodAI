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
    
    // ==========================================
    // ส่วนที่ 1: จัดการเป้าหมายสุขภาพ (Goals)
    // ==========================================
    if ($action === 'add_goal') {
        $goal_key = trim($_POST['goal_key']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $badge = trim($_POST['badge']);
        $cal_adjust = (int)$_POST['cal_adjust'];
        
        $stmt = $conn->prepare("INSERT INTO goals (goal_key, title, description, badge, cal_adjust) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $goal_key, $title, $description, $badge, $cal_adjust);
        if ($stmt->execute()) $success_msg = "เพิ่มเป้าหมายใหม่เรียบร้อยแล้ว";
        else $error_msg = "เกิดข้อผิดพลาด: รหัส (Key) อาจซ้ำกัน";
    }
    if ($action === 'edit_goal') {
        $id = (int)$_POST['goal_id'];
        $goal_key = trim($_POST['goal_key']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $badge = trim($_POST['badge']);
        $cal_adjust = (int)$_POST['cal_adjust'];
        
        $stmt = $conn->prepare("UPDATE goals SET goal_key=?, title=?, description=?, badge=?, cal_adjust=? WHERE id=?");
        $stmt->bind_param("ssssii", $goal_key, $title, $description, $badge, $cal_adjust, $id);
        if ($stmt->execute()) $success_msg = "อัปเดตข้อมูลเป้าหมายสำเร็จ";
        else $error_msg = "เกิดข้อผิดพลาดในการอัปเดต";
    }
    if ($action === 'delete_goal') {
        $id = (int)$_POST['goal_id'];
        $find_stmt = $conn->prepare("SELECT goal_key FROM goals WHERE id = ?");
        $find_stmt->bind_param("i", $id);
        $find_stmt->execute();
        $target_goal = $find_stmt->get_result()->fetch_assoc();
        if ($target_goal) {
            $clear_stmt = $conn->prepare("UPDATE health_profiles SET goal_preference = 'lose_normal' WHERE goal_preference = ?");
            $clear_stmt->bind_param("s", $target_goal['goal_key']);
            $clear_stmt->execute();
        }
        $stmt = $conn->prepare("DELETE FROM goals WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $success_msg = "ลบเป้าหมายเรียบร้อยแล้ว";
        else $error_msg = "เกิดข้อผิดพลาดในการลบ";
    }

    // ==========================================
    // ส่วนที่ 2: จัดการรูปแบบการกิน (Dietary Types)
    // ==========================================
    if ($action === 'add_diet') {
        $diet_key = trim($_POST['diet_key']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        
        $stmt = $conn->prepare("INSERT INTO dietary_types (diet_key, title, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $diet_key, $title, $description);
        if ($stmt->execute()) $success_msg = "เพิ่มรูปแบบการกินใหม่เรียบร้อยแล้ว";
        else $error_msg = "เกิดข้อผิดพลาด: รหัส (Key) อาจซ้ำกัน";
    }
    if ($action === 'edit_diet') {
        $id = (int)$_POST['diet_id'];
        $diet_key = trim($_POST['diet_key']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        
        $stmt = $conn->prepare("UPDATE dietary_types SET diet_key=?, title=?, description=? WHERE id=?");
        $stmt->bind_param("sssi", $diet_key, $title, $description, $id);
        if ($stmt->execute()) $success_msg = "อัปเดตข้อมูลรูปแบบการกินสำเร็จ";
        else $error_msg = "เกิดข้อผิดพลาดในการอัปเดต";
    }
    if ($action === 'delete_diet') {
        $id = (int)$_POST['diet_id'];
        $stmt = $conn->prepare("DELETE FROM dietary_types WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $success_msg = "ลบรูปแบบการกินเรียบร้อยแล้ว";
        else $error_msg = "เกิดข้อผิดพลาดในการลบ";
    }
}

// ดึงข้อมูล Goals
$goals = [];
$res_goals = $conn->query("SELECT * FROM goals ORDER BY id ASC");
if ($res_goals) { while($r = $res_goals->fetch_assoc()) $goals[] = $r; }

// ดึงข้อมูล Diets
$diets = [];
$res_diets = $conn->query("SELECT * FROM dietary_types ORDER BY id ASC");
if ($res_diets) { while($r = $res_diets->fetch_assoc()) $diets[] = $r; }
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการเป้าหมายและการกิน — Admin</title>

<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

.btn { padding:8px 14px; border-radius:10px; font-size:.8rem; font-weight:600; cursor:pointer; transition:all .2s; border:none; display:inline-flex; align-items:center; justify-content:center; gap:6px;}
.btn:hover { transform: translateY(-1px); filter: brightness(0.95); }
.form-input { width:100%; padding:10px 14px; border:1.5px solid var(--bdr); border-radius:10px; font-family:'Kanit',sans-serif; font-size:.88rem; outline: none; transition: all 0.2s; background: #fafafa;}
.form-input:focus { border-color: var(--g500); background: #fff; box-shadow: 0 0 0 3px rgba(34,197,94,.1); }
.form-label { display:block; font-size:.82rem; font-weight:600; color:var(--sub); margin-bottom:6px; }
.swal2-container { font-family: 'Kanit', sans-serif !important; }

/* DataTables CSS เหมือน admin_recipes */
.dataTables_wrapper{font-family:'Kanit',sans-serif!important;}
.dataTables_wrapper .dataTables_filter input{border:1.5px solid var(--bdr);border-radius:8px;padding:6px 12px;font-size:.8rem;}
.dataTables_wrapper .dataTables_info{font-size:.75rem;color:var(--muted);}
.dataTables_wrapper .dataTables_paginate .paginate_button{border:1px solid var(--bdr);border-radius:8px;padding:4px 12px;font-size:.75rem;}
.dataTables_wrapper .dataTables_paginate .paginate_button.current{background:var(--g500)!important;color:#fff!important;}
.dt-buttons{margin-bottom:12px;}
.dt-button{background:var(--g500)!important;color:#fff!important;border:none!important;padding:8px 16px!important;border-radius:8px!important;}
</style>
</head>
<body>

<?php include '../includes/sidebar_admin.php' ?>

<div class="page-wrap">
  <header class="topbar">
    <button class="hamburger"><i class="fas fa-bars"></i></button>
    <div style="flex:1;">
      <div style="font-family:'Nunito',sans-serif;font-size:1.2rem;font-weight:800;color:#2b3452;">จัดการข้อมูลสุขภาพ</div>
      <div style="font-size:.75rem;color:var(--muted);"><i class="fas fa-database me-1"></i> ควบคุมตัวเลือกเป้าหมายและรูปแบบการกิน</div>
    </div>
  </header>
  
  <main style="padding:2.5rem; max-width: 1200px; margin: 0 auto;">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <h2 style="font-family:'Nunito',sans-serif; font-weight:800; font-size:1.2rem; color:var(--txt);"><i class="fas fa-bullseye" style="color:var(--g500);"></i> เป้าหมายสุขภาพ (Goals)</h2>
      <button onclick="openGoalModal('add')" class="btn" style="background:linear-gradient(135deg,var(--g500),var(--t500)); color:#fff; box-shadow:0 4px 12px rgba(34,197,94,.2);">
        <i class="fas fa-plus"></i> เพิ่มเป้าหมายใหม่
      </button>
    </div>
    
    <div class="card">
      <div style="overflow-x:auto;">
        <table id="goalsTable" class="table" style="width: 100%;">
          <thead>
            <tr>
              <th>ID</th>
              <th>Key (รหัส)</th>
              <th>ชื่อเป้าหมาย</th>
              <th>ป้ายกำกับ (Badge)</th>
              <th>ปรับค่าแคลอรี่</th>
              <th style="text-align: right;">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($goals as $g): ?>
            <tr>
              <td style="color:var(--muted); font-weight:600;">#<?= $g['id'] ?></td>
              <td><span class="badge badge-gray"><?= htmlspecialchars($g['goal_key']) ?></span></td>
              <td style="font-weight:600; color:var(--txt);">
                  <?= htmlspecialchars($g['title']) ?><br>
                  <span style="font-size: 0.75rem; color:var(--muted); font-weight:400;"><?= htmlspecialchars($g['description']) ?></span>
              </td>
              <td><span class="badge badge-green"><?= htmlspecialchars($g['badge']) ?></span></td>
              <td>
                  <?php if($g['cal_adjust'] > 0): ?>
                      <span style="color:#ea580c; font-weight:700;">+<?= $g['cal_adjust'] ?> kcal</span>
                  <?php elseif($g['cal_adjust'] < 0): ?>
                      <span style="color:#16a34a; font-weight:700;"><?= $g['cal_adjust'] ?> kcal</span>
                  <?php else: ?>
                      <span style="color:var(--muted); font-weight:700;">คงที่ (0)</span>
                  <?php endif; ?>
              </td>
              <td style="text-align: right; min-width: 150px;">
                <div style="display:inline-flex; gap:6px;">
                  <button type="button" onclick="openGoalModal('edit', <?= htmlspecialchars(json_encode($g), ENT_QUOTES) ?>)" class="btn" style="background:#f8faf9; color:var(--g700); border:1px solid var(--bdr); padding:6px 10px; font-size:0.75rem;">
                    <i class="fas fa-edit"></i>
                  </button>
                  <button type="button" onclick="confirmDelete('goal', <?= $g['id'] ?>, '<?= htmlspecialchars($g['title'], ENT_QUOTES) ?>')" class="btn" style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca; padding:6px 10px; font-size:0.75rem;">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; margin-top:3rem;">
      <h2 style="font-family:'Nunito',sans-serif; font-weight:800; font-size:1.2rem; color:var(--txt);"><i class="fas fa-seedling" style="color:var(--g500);"></i> รูปแบบการกิน (Dietary Types)</h2>
      <button onclick="openDietModal('add')" class="btn" style="background:linear-gradient(135deg,var(--g500),var(--t500)); color:#fff; box-shadow:0 4px 12px rgba(34,197,94,.2);">
        <i class="fas fa-plus"></i> เพิ่มรูปแบบการกิน
      </button>
    </div>

    <div class="card">
      <div style="overflow-x:auto;">
        <table id="dietsTable" class="table" style="width: 100%;">
          <thead>
            <tr>
              <th>ID</th>
              <th>Key (รหัส)</th>
              <th>ชื่อรูปแบบการกิน</th>
              <th>คำอธิบาย</th>
              <th style="text-align: right;">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($diets as $d): ?>
            <tr>
              <td style="color:var(--muted); font-weight:600;">#<?= $d['id'] ?></td>
              <td><span class="badge badge-gray"><?= htmlspecialchars($d['diet_key']) ?></span></td>
              <td style="font-weight:600; color:var(--txt);"><?= htmlspecialchars($d['title']) ?></td>
              <td style="color:var(--sub); font-size:0.85rem;"><?= htmlspecialchars($d['description']) ?></td>
              <td style="text-align: right; min-width: 150px;">
                <div style="display:inline-flex; gap:6px;">
                  <button type="button" onclick="openDietModal('edit', <?= htmlspecialchars(json_encode($d), ENT_QUOTES) ?>)" class="btn" style="background:#f8faf9; color:var(--g700); border:1px solid var(--bdr); padding:6px 10px; font-size:0.75rem;">
                    <i class="fas fa-edit"></i>
                  </button>
                  <button type="button" onclick="confirmDelete('diet', <?= $d['id'] ?>, '<?= htmlspecialchars($d['title'], ENT_QUOTES) ?>')" class="btn" style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca; padding:6px 10px; font-size:0.75rem;">
                    <i class="fas fa-trash-alt"></i>
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
    <input type="hidden" name="action" id="delete_action">
    <input type="hidden" name="" id="delete_id_field">
</form>

<div id="goalModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:24px;padding:32px;max-width:550px;width:90%;box-shadow:0 20px 40px rgba(0,0,0,.15);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;border-bottom:1px solid var(--bdr);padding-bottom:16px;">
      <h3 id="goalModalTitle" style="font-family:'Nunito',sans-serif;font-size:1.25rem;font-weight:800;color:#2b3452;margin:0;">
        <i class="fas fa-bullseye" style="color:var(--g500);margin-right:8px;"></i> <span id="goalModalTitleText">เพิ่มเป้าหมาย</span>
      </h3>
      <button type="button" onclick="closeModal('goalModal')" style="width:36px;height:36px;border-radius:10px;border:none;background:#f1f5f3;color:var(--muted);cursor:pointer;font-size:1rem;transition:all 0.2s;">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" id="goalFormAction" value="add_goal">
      <input type="hidden" name="goal_id" id="goal_id">
      
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
          <div><label class="form-label">รหัส (ภาษาอังกฤษ ห้ามเว้นวรรค)</label><input type="text" name="goal_key" id="goal_key" class="form-input" required></div>
          <div><label class="form-label">ชื่อเป้าหมาย (ภาษาไทย)</label><input type="text" name="title" id="goal_title" class="form-input" required></div>
      </div>
      <div style="margin-bottom:16px;"><label class="form-label">คำอธิบายย่อย</label><input type="text" name="description" id="goal_description" class="form-input" required></div>
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:24px;">
          <div><label class="form-label">ป้ายกำกับ (เช่น -500 kcal)</label><input type="text" name="badge" id="goal_badge" class="form-input"></div>
          <div><label class="form-label text-orange-600">ค่าปรับแคลอรี่ตัวเลข (kcal)</label><input type="number" name="cal_adjust" id="goal_cal_adjust" class="form-input" required></div>
      </div>
      
      <div style="display:flex; justify-content:flex-end; gap:12px;">
        <button type="button" onclick="closeModal('goalModal')" class="btn" style="background:#f1f5f3; color:var(--sub); padding:12px 20px;">ยกเลิก</button>
        <button type="submit" class="btn" style="background:linear-gradient(135deg,var(--g500),var(--t500)); color:#fff; padding:12px 24px; box-shadow:0 4px 12px rgba(34,197,94,.2);"><i class="fas fa-save"></i> บันทึกข้อมูล</button>
      </div>
    </form>
  </div>
</div>

<div id="dietModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:24px;padding:32px;max-width:550px;width:90%;box-shadow:0 20px 40px rgba(0,0,0,.15);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;border-bottom:1px solid var(--bdr);padding-bottom:16px;">
      <h3 id="dietModalTitle" style="font-family:'Nunito',sans-serif;font-size:1.25rem;font-weight:800;color:#2b3452;margin:0;">
        <i class="fas fa-seedling" style="color:var(--g500);margin-right:8px;"></i> <span id="dietModalTitleText">เพิ่มรูปแบบการกิน</span>
      </h3>
      <button type="button" onclick="closeModal('dietModal')" style="width:36px;height:36px;border-radius:10px;border:none;background:#f1f5f3;color:var(--muted);cursor:pointer;font-size:1rem;transition:all 0.2s;">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" id="dietFormAction" value="add_diet">
      <input type="hidden" name="diet_id" id="diet_id">
      
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
          <div><label class="form-label">รหัส (ภาษาอังกฤษ ห้ามเว้นวรรค)</label><input type="text" name="diet_key" id="diet_key" class="form-input" placeholder="เช่น keto" required></div>
          <div><label class="form-label">ชื่อรูปแบบ (ภาษาไทย)</label><input type="text" name="title" id="diet_title" class="form-input" placeholder="เช่น คีโตเจนิค" required></div>
      </div>
      <div style="margin-bottom:24px;">
          <label class="form-label">คำอธิบาย</label>
          <input type="text" name="description" id="diet_description" class="form-input" placeholder="เช่น เน้นไขมันสูง คาร์บต่ำ" required>
      </div>
      
      <div style="display:flex; justify-content:flex-end; gap:12px;">
        <button type="button" onclick="closeModal('dietModal')" class="btn" style="background:#f1f5f3; color:var(--sub); padding:12px 20px;">ยกเลิก</button>
        <button type="submit" class="btn" style="background:linear-gradient(135deg,var(--g500),var(--t500)); color:#fff; padding:12px 24px; box-shadow:0 4px 12px rgba(34,197,94,.2);"><i class="fas fa-save"></i> บันทึกข้อมูล</button>
      </div>
    </form>
  </div>
</div>

<script>
// สคริปต์เปิดใช้งาน DataTables
$(document).ready(function() {
  const dtConfig = {
    pageLength: 25,
    order: [[0, "asc"]],
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
        bom: true, 
        exportOptions: { columns: ":not(:last-child)" }
      },
    ]
  };

  $("#goalsTable").DataTable(dtConfig);
  $("#dietsTable").DataTable(dtConfig);
});

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($success_msg): ?>
        Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: '<?= htmlspecialchars($success_msg) ?>', confirmButtonColor: '#22c55e' });
    <?php endif; ?>
    <?php if ($error_msg): ?>
        Swal.fire({ icon: 'error', title: 'พบข้อผิดพลาด!', text: '<?= htmlspecialchars($error_msg) ?>', confirmButtonColor: '#dc2626' });
    <?php endif; ?>
});

function confirmDelete(type, id, title) {
    let typeName = type === 'goal' ? 'เป้าหมาย' : 'รูปแบบการกิน';
    let actionName = type === 'goal' ? 'delete_goal' : 'delete_diet';
    let idField = type === 'goal' ? 'goal_id' : 'diet_id';

    Swal.fire({
        title: 'ยืนยันการลบ?',
        html: `คุณต้องการลบ${typeName} <b>${title}</b> ใช่หรือไม่?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#9ca3af',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_action').value = actionName;
            let field = document.getElementById('delete_id_field');
            field.name = idField;
            field.value = id;
            document.getElementById('deleteForm').submit();
        }
    });
}

function openGoalModal(mode, data = null) {
  if(mode === 'edit') {
    document.getElementById('goalModalTitleText').innerText = 'แก้ไขเป้าหมาย';
    document.getElementById('goalFormAction').value = 'edit_goal';
    document.getElementById('goal_id').value = data.id;
    document.getElementById('goal_key').value = data.goal_key;
    document.getElementById('goal_title').value = data.title;
    document.getElementById('goal_description').value = data.description;
    document.getElementById('goal_badge').value = data.badge;
    document.getElementById('goal_cal_adjust').value = data.cal_adjust;
  } else {
    document.getElementById('goalModalTitleText').innerText = 'เพิ่มเป้าหมายใหม่';
    document.getElementById('goalFormAction').value = 'add_goal';
    document.getElementById('goal_id').value = '';
    document.getElementById('goal_key').value = '';
    document.getElementById('goal_title').value = '';
    document.getElementById('goal_description').value = '';
    document.getElementById('goal_badge').value = '';
    document.getElementById('goal_cal_adjust').value = '0';
  }
  document.getElementById('goalModal').style.display = 'flex';
}

function openDietModal(mode, data = null) {
  if(mode === 'edit') {
    document.getElementById('dietModalTitleText').innerText = 'แก้ไขรูปแบบการกิน';
    document.getElementById('dietFormAction').value = 'edit_diet';
    document.getElementById('diet_id').value = data.id;
    document.getElementById('diet_key').value = data.diet_key;
    document.getElementById('diet_title').value = data.title;
    document.getElementById('diet_description').value = data.description;
  } else {
    document.getElementById('dietModalTitleText').innerText = 'เพิ่มรูปแบบการกิน';
    document.getElementById('dietFormAction').value = 'add_diet';
    document.getElementById('diet_id').value = '';
    document.getElementById('diet_key').value = '';
    document.getElementById('diet_title').value = '';
    document.getElementById('diet_description').value = '';
  }
  document.getElementById('dietModal').style.display = 'flex';
}

function closeModal(modalId) {
  document.getElementById(modalId).style.display = 'none';
}
</script>

</body>
</html>