<?php
session_start();
include '../config/connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$gender = 'male'; $age = ''; $weight = ''; $height = ''; $activity = 'sedentary';
$goal_pref = 'lose_normal'; $diet_pref = 'normal';
$conditions_arr = []; $other_text = '';
$diet_arr_check = ['normal'];

$sql = "SELECT * FROM health_profiles WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $gender   = $row['gender'];
    $age      = $row['age'];
    $weight   = $row['weight'];
    $height   = $row['height'];
    $activity = $row['activity_level'];
    $goal_pref = $row['goal_preference'] ?? 'lose_normal';
    $diet_pref = $row['dietary_type'];
    $diet_arr_check = !empty($diet_pref) ? explode(", ", $diet_pref) : ["normal"];
    if (!empty($row['health_conditions']) && $row['health_conditions'] != "ไม่มีโรคประจำตัว") {
        $conditions_arr = explode(", ", $row['health_conditions']);
    }
    $standard_diseases = ["เบาหวาน","ความดันสูง","โรคไต","โรคหัวใจ","ไขมันในเลือดสูง","เก๊าท์","กรดไหลย้อน","ไทรอยด์","โลหิตจาง","ไมเกรน","แพ้อาหารทะเล","แพ้ถั่ว","แพ้นมวัว","แพ้กลูเตน/แป้งสาลี","แพ้ไข่"];
    $others = array_diff($conditions_arr, $standard_diseases);
    if (!empty($others)) $other_text = implode(", ", $others);
}

$current_page = basename($_SERVER['PHP_SELF']);

// User info for sidebar
$u_sql  = "SELECT first_name, last_name FROM users WHERE id = ?";
$u_stmt = $conn->prepare($u_sql);
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$u_data    = $u_stmt->get_result()->fetch_assoc();
$firstName = $u_data['first_name'] ?? 'User';
$lastName  = $u_data['last_name']  ?? '';
$initials  = mb_strtoupper(mb_substr($firstName,0,1)).mb_strtoupper(mb_substr($lastName,0,1));
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ข้อมูลสุขภาพ — FoodAI</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g400:#4ade80;
  --g500:#22c55e;--g600:#16a34a;--g700:#15803d;
  --t500:#14b8a6;--t600:#0d9488;
  --bg:#f5f8f5;--card:#ffffff;--bdr:#e8f0e9;
  --txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;
  --sb-w:248px;--sb-bdr:#e5ede6;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,#c8e6c9 1px,transparent 1px);background-size:28px 28px;opacity:.38;}

/* ปรับให้ขยายตามหน้าจอ แต่ยังคงมีระยะห่างขอบ (Padding) เพื่อความสวยงาม */
main { 
    padding: 2rem 2.5rem 3.5rem; 
    width: 100%; 
    max-width: 100%; /* เปลี่ยนจาก 1280px เป็น 100% */
    margin: 0 auto; 
}

/* ── Sidebar ── */
.sidebar{width:var(--sb-w);min-height:100vh;background:#fff;border-right:1px solid var(--sb-bdr);display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;z-index:100;box-shadow:4px 0 24px rgba(34,197,94,.06);}
.sb-logo{padding:24px 22px 20px;border-bottom:1px solid var(--sb-bdr);display:flex;align-items:center;gap:11px;}
.sb-logo-icon{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--g500),var(--t500));display:flex;align-items:center;justify-content:center;font-size:1.1rem;box-shadow:0 4px 12px rgba(34,197,94,.35);flex-shrink:0;}
.sb-logo-text{font-family:'Nunito',sans-serif;font-size:1.18rem;font-weight:800;color:var(--g700);letter-spacing:-.02em;line-height:1;}
.sb-logo-sub{font-size:.6rem;font-weight:600;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin-top:2px;}
.sb-label{font-size:.6rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);padding:18px 22px 8px;}
.sb-nav{padding:6px 12px;display:flex;flex-direction:column;gap:2px;flex:1;}
.nav-item{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:12px;text-decoration:none;color:var(--sub);font-size:.82rem;font-weight:500;transition:background .18s,color .18s,transform .18s;position:relative;cursor:pointer;}
.nav-item:hover{background:var(--g50);color:var(--g700);transform:translateX(2px);}
.nav-item.active{background:var(--g50);color:var(--g600);font-weight:600;box-shadow:inset 3px 0 0 var(--g500);}
.nav-item.active .nav-icon-wrap{background:linear-gradient(135deg,var(--g500),var(--t500));color:white;box-shadow:0 3px 10px rgba(34,197,94,.38);}
.nav-icon-wrap{width:34px;height:34px;border-radius:10px;background:var(--g50);border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;transition:background .18s,box-shadow .18s;color:var(--g600);}
.nav-item:hover .nav-icon-wrap{background:var(--g100);border-color:var(--g200);}
.nav-badge{margin-left:auto;background:var(--g500);color:white;font-size:.6rem;font-weight:700;padding:2px 7px;border-radius:99px;min-width:20px;text-align:center;}
.nav-badge.orange{background:#f97316;}
.sb-divider{height:1px;background:var(--sb-bdr);margin:6px 12px;}
.sb-user{border-top:1px solid var(--sb-bdr);padding:16px;display:flex;align-items:center;gap:11px;background:var(--g50);}
.sb-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--t400));display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:800;color:white;flex-shrink:0;font-family:'Nunito',sans-serif;box-shadow:0 2px 8px rgba(34,197,94,.3);}
.sb-user-name{font-size:.78rem;font-weight:600;color:var(--txt);line-height:1.2;}
.sb-user-role{font-size:.62rem;color:var(--muted);margin-top:1px;}
.sb-logout{margin-left:auto;width:30px;height:30px;border-radius:8px;background:transparent;border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:.72rem;cursor:pointer;transition:background .18s,color .18s,border-color .18s;text-decoration:none;}
.sb-logout:hover{background:#fee2e2;border-color:#fecaca;color:#dc2626;}

/* ── Page ── */
.page-wrap{margin-left:var(--sb-w);flex:1;display:flex;flex-direction:column;position:relative;z-index:1;min-width:0;}
.topbar{height:62px;background:rgba(255,255,255,.88);backdrop-filter:blur(12px);border-bottom:1px solid var(--sb-bdr);display:flex;align-items:center;padding:0 2.5rem;gap:14px;position:sticky;top:0;z-index:50;}
.tb-back{width:38px;height:38px;border-radius:11px;background:white;border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;color:var(--sub);font-size:.8rem;text-decoration:none;transition:all .18s;}
.tb-back:hover{background:var(--g50);border-color:var(--g200);color:var(--g600);}
.topbar-title{font-family:'Nunito',sans-serif;font-size:1rem;font-weight:800;color:var(--txt);}
.topbar-sub{font-size:.72rem;color:var(--muted);}

@keyframes slideUp{from{opacity:0;transform:translateY(18px);}to{opacity:1;transform:translateY(0);}}
.rv{opacity:0;animation:slideUp .5s cubic-bezier(.22,1,.36,1) forwards;}
.rv1{animation-delay:.04s;}.rv2{animation-delay:.1s;}.rv3{animation-delay:.17s;}.rv4{animation-delay:.24s;}.rv5{animation-delay:.31s;}

/* ── Form elements ── */
.form-card{background:var(--card);border:1px solid var(--bdr);border-radius:20px;padding:28px;}
.section-label{font-family:'Nunito',sans-serif;font-size:.95rem;font-weight:800;color:var(--txt);display:flex;align-items:center;gap:9px;margin-bottom:18px;}
.section-icon{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.82rem;flex-shrink:0;}
.field-label{font-size:.75rem;font-weight:600;color:var(--sub);margin-bottom:6px;display:block;letter-spacing:.02em;}
.field-input{width:100%;padding:11px 14px;background:var(--g50);border:1.5px solid var(--bdr);border-radius:13px;font-family:'Kanit',sans-serif;font-size:.85rem;color:var(--txt);outline:none;transition:border-color .18s,box-shadow .18s;}
.field-input:focus{border-color:var(--g400);box-shadow:0 0 0 3px rgba(74,222,128,.12);}
.field-select{width:100%;padding:11px 14px;background:var(--g50);border:1.5px solid var(--bdr);border-radius:13px;font-family:'Kanit',sans-serif;font-size:.85rem;color:var(--txt);outline:none;appearance:none;cursor:pointer;transition:border-color .18s,box-shadow .18s;}
.field-select:focus{border-color:var(--g400);box-shadow:0 0 0 3px rgba(74,222,128,.12);}

/* Goal radio */
.goal-option input[type=radio]{display:none;}
.goal-card{padding:14px 16px;border-radius:14px;border:2px solid var(--bdr);display:flex;justify-content:space-between;align-items:center;cursor:pointer;transition:all .18s;background:white;}
.goal-card:hover{border-color:var(--g300);background:var(--g50);}
.goal-option input[type=radio]:checked + .goal-card{border-color:var(--g500);background:var(--g50);box-shadow:0 0 0 3px rgba(34,197,94,.1);}
.goal-title{font-size:.85rem;font-weight:600;color:var(--txt);}
.goal-sub{font-size:.7rem;color:var(--muted);margin-top:2px;}
.goal-badge{font-size:.68rem;font-weight:700;padding:4px 10px;border-radius:8px;background:var(--g50);border:1px solid var(--g200);color:var(--g700);white-space:nowrap;}
.goal-option input[type=radio]:checked + .goal-card .goal-badge{background:var(--g500);color:white;border-color:var(--g500);}

/* Diet checkbox */
.diet-option input[type=checkbox]{display:none;}
.diet-tag{display:block;text-align:center;padding:11px 10px;border-radius:13px;border:2px solid var(--bdr);font-size:.8rem;font-weight:500;color:var(--sub);cursor:pointer;transition:all .18s;background:white;}
.diet-tag:hover{border-color:var(--g200);background:var(--g50);}
.diet-option input[type=checkbox]:checked + .diet-tag{border-color:var(--g500);background:var(--g50);color:var(--g700);font-weight:600;}

/* Disease pill */
.dis-option input[type=checkbox]{display:none;}
.dis-pill{display:inline-block;padding:8px 15px;border-radius:99px;border:2px solid var(--bdr);font-size:.78rem;font-weight:500;color:var(--sub);cursor:pointer;transition:all .18s;background:white;}
.dis-pill:hover{border-color:var(--g200);background:var(--g50);}
.dis-option input[type=checkbox]:checked + .dis-pill{border-color:var(--g500);background:var(--g50);color:var(--g700);font-weight:600;}

/* Divider */
.form-divider{height:1px;background:var(--bdr);margin:4px 0;}

/* Submit */
.btn-submit{width:100%;padding:15px;border-radius:15px;background:linear-gradient(135deg,var(--g500),var(--t500));color:white;font-family:'Nunito',sans-serif;font-size:1rem;font-weight:800;border:none;cursor:pointer;transition:opacity .2s,box-shadow .2s,transform .2s;box-shadow:0 6px 20px rgba(34,197,94,.32);}
.btn-submit:hover{opacity:.9;box-shadow:0 8px 24px rgba(34,197,94,.42);transform:translateY(-1px);}

::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-track{background:transparent;}::-webkit-scrollbar-thumb{background:var(--g200);border-radius:99px;}

/* ── สไตล์ปุ่ม Hamburger Menu ── */
.menu-toggle {
  display: none;
  width: 38px; height: 38px; border-radius: 11px;
  background: white; border: 1px solid var(--bdr);
  align-items: center; justify-content: center;
  color: var(--sub); font-size: 0.9rem; cursor: pointer;
}
/* ── การจัดการ Layout บนมือถือ (จอเล็กกว่า 1024px) ── */
@media (max-width: 1024px) {
  .sidebar {
    transform: translateX(-100%); /* ซ่อน Sidebar ออกไปด้านซ้าย */
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .sidebar.show {
    transform: translateX(0); /* เลื่อน Sidebar กลับเข้ามาเมื่อมีคลาส .show */
  }
  .page-wrap {
    margin-left: 0 !important; /* ให้เนื้อหาหลักขยายเต็มจอ */
  }
  .menu-toggle {
    display: flex; /* แสดงปุ่มเมนูบนมือถือ */
  }
}
</style>
</head>
<body>

<?php include '../includes/sidebar.php' ?>

<!-- ══ MAIN ══ -->
<div class="page-wrap">

  <!-- Topbar -->
  <header class="topbar">
    <a href="dashboard.php" class="tb-back"><i class="fas fa-arrow-left"></i></a>
    <div>
      <div class="topbar-title">ข้อมูลสุขภาพ</div>
      <div class="topbar-sub">ระบุข้อมูลให้ครบเพื่อให้ AI วิเคราะห์ได้แม่นยำ</div>
    </div>
    <div style="margin-left:auto;width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--g500),var(--t500));display:flex;align-items:center;justify-content:center;font-size:1.1rem;box-shadow:0 3px 10px rgba(34,197,94,.3);">📏</div>
  </header>

  <main style="padding:2rem 2.5rem 3.5rem;max-width:860px;width:100%;">

    <form action="save_profile.php" method="POST" style="display:flex;flex-direction:column;gap:1.5rem;">

      <!-- ── BODY STATS ── -->
      <div class="form-card rv rv1">
        <div class="section-label">
          <span class="section-icon" style="background:#f0fdf4;border:1px solid var(--g200);">⚖️</span>
          ข้อมูลร่างกาย
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div>
            <label class="field-label">เพศ</label>
            <div style="position:relative;">
              <select name="gender" class="field-select" style="padding-right:36px;">
                <option value="male"   <?= $gender=='male'  ?'selected':'' ?>>ชาย</option>
                <option value="female" <?= $gender=='female'?'selected':'' ?>>หญิง</option>
              </select>
              <i class="fas fa-chevron-down" style="position:absolute;right:13px;top:50%;transform:translateY(-50%);font-size:.65rem;color:var(--muted);pointer-events:none;"></i>
            </div>
          </div>
          <div>
            <label class="field-label">อายุ (ปี)</label>
            <input type="number" name="age" value="<?= $age ?>" required placeholder="25" class="field-input" style="text-align:center;">
          </div>
          <div>
            <label class="field-label">น้ำหนัก (kg)</label>
            <input type="number" step="0.1" name="weight" value="<?= $weight ?>" required placeholder="65.0" class="field-input" style="text-align:center;">
          </div>
          <div>
            <label class="field-label">ส่วนสูง (cm)</label>
            <input type="number" name="height" value="<?= $height ?>" required placeholder="170" class="field-input" style="text-align:center;">
          </div>
        </div>
        <div style="margin-top:16px;">
          <label class="field-label">ระดับกิจกรรมในแต่ละวัน</label>
          <div style="position:relative;">
            <select name="activity_level" class="field-select" style="padding-right:36px;">
              <option value="sedentary"   <?= $activity=='sedentary'  ?'selected':'' ?>>🪑 ไม่ออกกำลังกาย / นั่งทำงานออฟฟิศ</option>
              <option value="light"       <?= $activity=='light'      ?'selected':'' ?>>🚶 ออกกำลังกายเบาๆ (1–3 วัน/สัปดาห์)</option>
              <option value="moderate"    <?= $activity=='moderate'   ?'selected':'' ?>>🏃 ออกกำลังกายปานกลาง (3–5 วัน/สัปดาห์)</option>
              <option value="active"      <?= $activity=='active'     ?'selected':'' ?>>💪 ออกกำลังกายหนัก (6–7 วัน/สัปดาห์)</option>
              <option value="very_active" <?= $activity=='very_active'?'selected':'' ?>>🏋️ นักกีฬา / ใช้แรงงานหนัก</option>
            </select>
            <i class="fas fa-chevron-down" style="position:absolute;right:13px;top:50%;transform:translateY(-50%);font-size:.65rem;color:var(--muted);pointer-events:none;"></i>
          </div>
        </div>
      </div>

      <!-- ── GOAL ── -->
      <div class="form-card rv rv2">
        <div class="section-label">
          <span class="section-icon" style="background:#fff1f2;border:1px solid #fecdd3;">🎯</span>
          เป้าหมายของคุณ
        </div>
        <div style="display:flex;flex-direction:column;gap:9px;">
          <?php
          // ดึงข้อมูลเป้าหมายทั้งหมดจากฐานข้อมูลสดๆ
          $goals = [];
          $g_query = "SELECT goal_key, title, description, badge FROM goals ORDER BY id ASC";
          $g_res = $conn->query($g_query);
          if ($g_res && $g_res->num_rows > 0) {
              while($g_row = $g_res->fetch_assoc()) {
                  $goals[$g_row['goal_key']] = [$g_row['title'], $g_row['description'], $g_row['badge']];
              }
          } else {
              // สำรองไว้เผื่อกรณีตารางว่างเปล่า
              $goals = ['lose_normal' => ['ลดน้ำหนักมาตรฐาน', 'ผลลัพธ์เห็นได้ชัดใน 1–2 เดือน', '-500 kcal']];
          }

          foreach ($goals as $val => [$title, $sub, $badge]):
          ?>
          <label class="goal-option" style="display:block;">
            <input type="radio" name="goal" value="<?= htmlspecialchars($val) ?>" <?= $goal_pref==$val?'checked':'' ?>>
            <div class="goal-card">
              <div>
                <div class="goal-title"><?= htmlspecialchars($title) ?></div>
                <div class="goal-sub"><?= htmlspecialchars($sub) ?></div>
              </div>
              <span class="goal-badge"><?= htmlspecialchars($badge) ?></span>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      
      <!-- ── DIET ── -->
      <div class="form-card rv rv3">
        <div class="section-label">
          <span class="section-icon" style="background:#eff6ff;border:1px solid #bfdbfe;">🥦</span>
          รูปแบบการกิน <span style="font-size:.7rem;font-weight:500;color:var(--muted);">(เลือกได้มากกว่า 1)</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
          <?php
          $diets = [
            'normal'  => ['🍽️','ทั่วไป'],
            'clean'   => ['🥗','คลีนฟู้ด'],
            'keto'    => ['🥑','คีโต'],
            'lowcarb' => ['🫛','โลว์คาร์บ'],
            'vegan'   => ['🌱','มังสวิรัติ'],
            'if'      => ['⏰','Intermittent F'],
          ];
          foreach ($diets as $val => [$icon, $label]):
            $chk = in_array($val, $diet_arr_check) ? 'checked' : '';
          ?>
          <label class="diet-option">
            <input type="checkbox" name="diet[]" value="<?= $val ?>" <?= $chk ?>>
            <div class="diet-tag">
              <div style="font-size:1.3rem;margin-bottom:4px;"><?= $icon ?></div>
              <div style="font-size:.78rem;"><?= $label ?></div>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ── DISEASES ── -->
      <div class="form-card rv rv4">
        <div class="section-label">
          <span class="section-icon" style="background:#fefce8;border:1px solid #fde68a;">🩺</span>
          โรคประจำตัว / ข้อจำกัดอาหาร
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
          <?php
          $diseases_list = ["เบาหวาน","ความดันสูง","โรคไต","โรคหัวใจ","ไขมันในเลือดสูง","เก๊าท์","กรดไหลย้อน","ไทรอยด์","โลหิตจาง","ไมเกรน","แพ้อาหารทะเล","แพ้ถั่ว","แพ้นมวัว","แพ้กลูเตน/แป้งสาลี","แพ้ไข่"];
          foreach ($diseases_list as $d):
            $isChecked = in_array($d, $conditions_arr) ? 'checked' : '';
          ?>
          <label class="dis-option">
            <input type="checkbox" name="diseases[]" value="<?= $d ?>" <?= $isChecked ?>>
            <span class="dis-pill"><?= $d ?></span>
          </label>
          <?php endforeach; ?>
          <label class="dis-option">
            <input type="checkbox" id="checkOther" onchange="toggleOther()" <?= !empty($other_text)?'checked':'' ?>>
            <span class="dis-pill" style="border-style:dashed;">✏️ อื่นๆ</span>
          </label>
        </div>
        <div id="otherBox" class="<?= !empty($other_text)?'':'hidden' ?>" style="margin-top:12px;">
          <input type="text" name="other_disease_text" value="<?= htmlspecialchars($other_text) ?>"
                 placeholder="ระบุโรคหรืออาหารที่แพ้เพิ่มเติม..."
                 class="field-input" style="background:#fefce8;border-color:#fde68a;">
        </div>
      </div>

      <!-- ── SUBMIT ── -->
      <div class="rv rv5">
        <button type="submit" class="btn-submit">
          <i class="fas fa-check-circle" style="margin-right:8px;"></i> บันทึกข้อมูลสุขภาพ
        </button>
      </div>

    </form>
  </main>
</div>

<script>
function toggleOther() {
  const box = document.getElementById('otherBox');
  if (document.getElementById('checkOther').checked) {
    box.classList.remove('hidden');
    box.querySelector('input').focus();
  } else {
    box.classList.add('hidden');
  }
}
</script>
</body>
</html>