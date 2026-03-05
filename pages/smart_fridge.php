<?php
session_start();
include '../config/connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* ── User info ── */
$u_stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$u_data    = $u_stmt->get_result()->fetch_assoc();
$firstName = $u_data['first_name'] ?? 'User';
$lastName  = $u_data['last_name']  ?? '';
$initials  = mb_strtoupper(mb_substr($firstName,0,1)).mb_strtoupper(mb_substr($lastName,0,1));

/* ── Handle POST: Add custom ingredient ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'add_ingredient') {
        $name = trim($_POST['name'] ?? '');
        
        if (empty($name)) {
            echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุชื่อวัตถุดิบ']);
            exit();
        }
        
        // Check if exists
        $check = $conn->prepare("SELECT id FROM ingredients WHERE name = ?");
        $check->bind_param("s", $name);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        
        if ($exists) {
            echo json_encode(['status' => 'exists', 'id' => $exists['id'], 'name' => $name]);
            exit();
        }
        
        // Insert new
        $insert = $conn->prepare("INSERT INTO ingredients (name) VALUES (?)");
        $insert->bind_param("s", $name);
        
        if ($insert->execute()) {
            echo json_encode(['status' => 'success', 'id' => $insert->insert_id, 'name' => $name]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึก']);
        }
        exit();
    }
}

/* ── Fetch all ingredients ── */
$ing_sql = "SELECT id, name FROM ingredients ORDER BY name ASC";
$ing_res = $conn->query($ing_sql);
$all_ingredients = [];
while ($row = $ing_res->fetch_assoc()) {
    $all_ingredients[] = $row;
}

/* ── Get user health profile ── */
$hp = $conn->prepare("SELECT daily_calorie_target, dietary_type, health_conditions FROM health_profiles WHERE user_id = ?");
$hp->bind_param("i", $user_id);
$hp->execute();
$hp_row = $hp->get_result()->fetch_assoc();
$has_profile = !empty($hp_row);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ตู้เย็นอัจฉริยะ — FoodAI</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
  --g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;
  --g400:#4ade80;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;
  --t400:#2dd4bf;--t500:#14b8a6;--t600:#0d9488;
  --bg:#f5f8f5;--card:#fff;--bdr:#e8f0e9;
  --txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;
  --sb-w:248px;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,#c8e6c9 1px,transparent 1px);background-size:28px 28px;opacity:.35;}

/* ปรับให้ขยายตามหน้าจอ แต่ยังคงมีระยะห่างขอบ (Padding) เพื่อความสวยงาม */
main { 
    padding: 2rem 2.5rem 3.5rem; 
    width: 100%; 
    max-width: 100%; /* เปลี่ยนจาก 1280px เป็น 100% */
    margin: 0 auto; 
}

/* Sidebar */
.sidebar{width:var(--sb-w);min-height:100vh;background:#fff;border-right:1px solid #e5ede6;display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;z-index:100;box-shadow:4px 0 24px rgba(34,197,94,.06);}
.sb-logo{padding:24px 22px 20px;border-bottom:1px solid #e5ede6;display:flex;align-items:center;gap:11px;}
.sb-logo-icon{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--g500),var(--t500));display:flex;align-items:center;justify-content:center;font-size:1.1rem;box-shadow:0 4px 12px rgba(34,197,94,.35);}
.sb-logo-text{font-family:'Nunito',sans-serif;font-size:1.18rem;font-weight:800;color:var(--g700);letter-spacing:-.02em;line-height:1;}
.sb-logo-sub{font-size:.6rem;font-weight:600;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin-top:2px;}
.sb-label{font-size:.6rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);padding:18px 22px 8px;}
.sb-nav{padding:6px 12px;display:flex;flex-direction:column;gap:2px;flex:1;overflow-y:auto;}
.nav-item{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:12px;text-decoration:none;color:var(--sub);font-size:.82rem;font-weight:500;transition:all .18s;}
.nav-item:hover{background:var(--g50);color:var(--g700);transform:translateX(2px);}
.nav-item.active{background:var(--g50);color:var(--g600);font-weight:600;box-shadow:inset 3px 0 0 var(--g500);}
.nav-item.active .ni{background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;box-shadow:0 3px 10px rgba(34,197,94,.38);}
.ni{width:34px;height:34px;border-radius:10px;background:var(--g50);border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;transition:all .18s;color:var(--g600);}
.nav-item:hover .ni{background:var(--g100);border-color:var(--g200);}
.nb{margin-left:auto;background:var(--g500);color:#fff;font-size:.6rem;font-weight:700;padding:2px 7px;border-radius:99px;}
.nb.orange{background:#f97316;}
.sb-div{height:1px;background:#e5ede6;margin:6px 12px;}
.sb-user{border-top:1px solid #e5ede6;padding:16px;display:flex;align-items:center;gap:11px;background:var(--g50);}
.sb-av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--t400));display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:800;color:#fff;font-family:'Nunito',sans-serif;box-shadow:0 2px 8px rgba(34,197,94,.3);}
.sb-un{font-size:.78rem;font-weight:600;color:var(--txt);line-height:1.2;}
.sb-out{margin-left:auto;width:30px;height:30px;border-radius:8px;border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:.72rem;text-decoration:none;transition:all .18s;}
.sb-out:hover{background:#fee2e2;border-color:#fecaca;color:#dc2626;}

.page-wrap{margin-left:var(--sb-w);flex:1;position:relative;z-index:1;min-width:0;}
.topbar{height:62px;background:rgba(255,255,255,.9);backdrop-filter:blur(12px);border-bottom:1px solid #e5ede6;display:flex;align-items:center;padding:0 2.5rem;gap:14px;position:sticky;top:0;z-index:50;}
.tb-back{width:38px;height:38px;border-radius:11px;background:#fff;border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;color:var(--sub);text-decoration:none;font-size:.8rem;transition:all .18s;}
.tb-back:hover{background:var(--g50);border-color:var(--g200);color:var(--g600);}

@keyframes slideUp{from{opacity:0;transform:translateY(18px);}to{opacity:1;transform:translateY(0);}}
.rv{opacity:0;animation:slideUp .5s cubic-bezier(.22,1,.36,1) forwards;}
.rv1{animation-delay:.04s;}.rv2{animation-delay:.10s;}.rv3{animation-delay:.17s;}.rv4{animation-delay:.24s;}

.stitle{font-family:'Nunito',sans-serif;font-size:1rem;font-weight:800;color:var(--txt);display:flex;align-items:center;gap:8px;}
.gline{height:3px;background:linear-gradient(90deg,var(--g500),transparent);border-radius:99px;}

.card{background:#fff;border:1px solid var(--bdr);border-radius:20px;padding:24px;}

/* Search */
.search-wrap{background:#fff;border:1.5px solid var(--bdr);border-radius:13px;display:flex;align-items:center;gap:10px;padding:0 14px;height:44px;transition:border-color .18s,box-shadow .18s;}
.search-wrap:focus-within{border-color:var(--g400);box-shadow:0 0 0 3px rgba(74,222,128,.12);}
.search-wrap input{border:none;outline:none;background:transparent;font-family:'Kanit',sans-serif;font-size:.85rem;color:var(--txt);width:100%;flex:1;}
.search-wrap input::placeholder{color:var(--muted);}

/* Ingredient chip */
.ing-chip{border:2px solid var(--bdr);background:#fff;border-radius:12px;padding:8px 12px;font-size:.78rem;color:var(--sub);cursor:pointer;transition:all .18s;user-select:none;display:flex;align-items:center;gap:8px;}
.ing-chip:hover{border-color:var(--g300);background:var(--g50);color:var(--g700);}
.ing-chip.selected{border-color:var(--g500);background:var(--g50);color:var(--g700);font-weight:600;}
.ing-chip.selected .check{opacity:1;}
.check{width:18px;height:18px;border-radius:50%;border:2px solid var(--g500);background:var(--g500);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.6rem;opacity:0;transition:opacity .2s;}

/* Selected items */
.selected-item{background:var(--g50);border:1.5px solid var(--g300);border-radius:11px;padding:8px 14px;font-size:.78rem;color:var(--g700);font-weight:600;display:inline-flex;align-items:center;gap:8px;}
.remove-btn{width:20px;height:20px;border-radius:50%;background:var(--g600);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.65rem;cursor:pointer;transition:all .18s;}
.remove-btn:hover{background:var(--g700);transform:scale(1.1);}

/* Button */
.btn-green{background:linear-gradient(135deg,var(--g500),var(--t500));color:#fff;font-size:.82rem;font-weight:700;padding:12px 28px;border-radius:13px;border:none;cursor:pointer;transition:opacity .2s,box-shadow .2s;box-shadow:0 4px 16px rgba(34,197,94,.3);font-family:'Kanit',sans-serif;}
.btn-green:hover{opacity:.88;box-shadow:0 6px 20px rgba(34,197,94,.4);}
.btn-green:disabled{opacity:.5;cursor:not-allowed;}

/* Add custom */
.add-custom{border:2px dashed var(--g300);background:var(--g50);border-radius:13px;padding:14px;display:flex;align-items:center;gap:12px;}
.add-custom input{flex:1;border:none;background:transparent;outline:none;font-family:'Kanit',sans-serif;font-size:.82rem;color:var(--txt);}
.add-custom input::placeholder{color:var(--muted);}
.add-btn{width:36px;height:36px;border-radius:10px;background:var(--g500);color:#fff;border:none;display:flex;align-items:center;justify-content:center;font-size:.8rem;cursor:pointer;transition:all .18s;}
.add-btn:hover{background:var(--g600);transform:scale(1.05);}

::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-thumb{background:var(--g200);border-radius:99px;}

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

<!-- MAIN -->
<div class="page-wrap">

  <header class="topbar">
    <a href="dashboard.php" class="tb-back"><i class="fas fa-arrow-left"></i></a>
    <div>
      <div style="font-family:'Nunito',sans-serif;font-size:.95rem;font-weight:800;color:var(--txt);">ตู้เย็นอัจฉริยะ</div>
      <div style="font-size:.7rem;color:var(--muted);">เลือกวัตถุดิบที่มี เชฟ AI จะแนะนำเมนูให้</div>
    </div>
  </header>

  <main style="padding:2rem 2.5rem 3.5rem;max-width:1080px;width:100%;">

    <!-- HEADER -->
    <div class="rv rv1" style="margin-bottom:1.8rem;">
      <p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Smart Fridge</p>
      <h1 style="font-family:'Nunito',sans-serif;font-size:1.6rem;font-weight:800;color:var(--txt);line-height:1.1;">
        วัตถุดิบในตู้เย็นของคุณ
      </h1>
      <div class="gline" style="width:50px;margin-top:10px;"></div>
    </div>

    <?php if (!$has_profile): ?>
    <!-- Warning: No profile -->
    <div class="rv rv2" style="background:#fef3c7;border:2px solid #fbbf24;border-radius:16px;padding:20px;margin-bottom:2rem;">
      <div style="display:flex;align-items:flex-start;gap:14px;">
        <i class="fas fa-triangle-exclamation" style="color:#f59e0b;font-size:1.4rem;flex-shrink:0;margin-top:2px;"></i>
        <div style="flex:1;">
          <div style="font-size:.92rem;font-weight:700;color:#92400e;margin-bottom:6px;">ยังไม่ได้ตั้งค่าข้อมูลสุขภาพ</div>
          <p style="font-size:.78rem;color:#78350f;line-height:1.65;margin-bottom:12px;">
            เพื่อให้ AI แนะนำเมนูที่เหมาะสมกับคุณ กรุณาตั้งค่าข้อมูลสุขภาพก่อนใช้งาน
          </p>
          <a href="setup_profile.php" style="display:inline-flex;align-items:center;gap:7px;background:#f59e0b;color:#fff;text-decoration:none;padding:8px 18px;border-radius:10px;font-size:.75rem;font-weight:700;">
            <i class="fas fa-user-gear" style="font-size:.7rem;"></i> ตั้งค่าข้อมูลสุขภาพ
          </a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">

      <!-- LEFT: Ingredient selection -->
      <div>

        <!-- Search -->
        <div class="rv rv2 card" style="margin-bottom:20px;">
          <label style="font-size:.78rem;font-weight:600;color:var(--sub);margin-bottom:8px;display:block;">ค้นหาวัตถุดิบ</label>
          <div class="search-wrap">
            <i class="fas fa-magnifying-glass" style="color:var(--muted);font-size:.78rem;"></i>
            <input type="text" id="searchInput" placeholder="พิมพ์ชื่อวัตถุดิบ..." oninput="filterIngredients(this.value)">
          </div>
        </div>

        <!-- Ingredient grid -->
        <div class="rv rv3 card">
          <h2 class="stitle" style="margin-bottom:16px;">เลือกวัตถุดิบที่คุณมีในตู้เย็น</h2>
          
          <div id="ingredientGrid" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px;max-height:400px;overflow-y:auto;padding:2px;">
            <?php foreach ($all_ingredients as $ing): ?>
            <div class="ing-chip" data-id="<?= $ing['id'] ?>" data-name="<?= htmlspecialchars($ing['name']) ?>" onclick="toggleIngredient(this)">
              <span class="check"><i class="fas fa-check"></i></span>
              <span><?= htmlspecialchars($ing['name']) ?></span>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Add custom ingredient -->
          <div style="border-top:1px solid var(--bdr);padding-top:18px;margin-top:18px;">
            <label style="font-size:.78rem;font-weight:600;color:var(--sub);margin-bottom:10px;display:block;">
              ✏️ เพิ่มวัตถุดิบที่ไม่มีในรายการ
            </label>
            <div class="add-custom">
              <i class="fas fa-plus-circle" style="color:var(--g500);font-size:1.1rem;"></i>
              <input type="text" id="customInput" placeholder="ระบุชื่อวัตถุดิบ..." onkeypress="if(event.key==='Enter') addCustomIngredient()">
              <button class="add-btn" onclick="addCustomIngredient()">
                <i class="fas fa-plus"></i>
              </button>
            </div>
          </div>
        </div>

      </div>

      <!-- RIGHT: Selected & Action -->
      <div style="position:sticky;top:80px;">

        <!-- Selected items -->
        <div class="rv rv2 card" style="margin-bottom:20px;">
          <h3 style="font-size:.88rem;font-weight:700;color:var(--txt);margin-bottom:14px;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-check-circle" style="color:var(--g500);"></i>
            วัตถุดิบที่เลือก
          </h3>
          
          <div id="selectedList" style="display:flex;flex-direction:column;gap:8px;min-height:60px;max-height:280px;overflow-y:auto;">
            <p style="font-size:.75rem;color:var(--muted);font-style:italic;text-align:center;padding:1rem 0;">
              ยังไม่ได้เลือกวัตถุดิบ
            </p>
          </div>
          
          <div style="border-top:1px solid var(--bdr);padding-top:14px;margin-top:14px;display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:.75rem;color:var(--muted);">เลือกแล้ว: <strong id="countDisplay" style="color:var(--g700);">0</strong> รายการ</span>
            <button onclick="clearAll()" style="font-size:.7rem;color:var(--muted);background:transparent;border:none;cursor:pointer;text-decoration:underline;">
              ล้างทั้งหมด
            </button>
          </div>
        </div>

        <!-- Action button -->
        <div class="rv rv3">
          <button id="askChefBtn" class="btn-green" style="width:100%;justify-content:center;display:flex;align-items:center;gap:8px;" onclick="askAIChef()" disabled>
            <i class="fas fa-robot"></i> ถามเชฟ AI
          </button>
          <p style="font-size:.7rem;color:var(--muted);text-align:center;margin-top:10px;line-height:1.55;">
            เชฟ AI จะแนะนำเมนูที่ทำได้จากวัตถุดิบที่คุณเลือก
          </p>
        </div>

      </div>

    </div>

  </main>
</div>

<script>
const selectedIngredients = new Set();
const ingredientsData = <?= json_encode($all_ingredients) ?>;

function toggleIngredient(chip) {
  const id = chip.dataset.id;
  const name = chip.dataset.name;
  
  if (selectedIngredients.has(id)) {
    selectedIngredients.delete(id);
    chip.classList.remove('selected');
  } else {
    selectedIngredients.add(id);
    chip.classList.add('selected');
  }
  
  updateSelectedList();
}

function updateSelectedList() {
  const list = document.getElementById('selectedList');
  const count = document.getElementById('countDisplay');
  const btn = document.getElementById('askChefBtn');
  
  count.textContent = selectedIngredients.size;
  btn.disabled = selectedIngredients.size === 0;
  
  if (selectedIngredients.size === 0) {
    list.innerHTML = '<p style="font-size:.75rem;color:var(--muted);font-style:italic;text-align:center;padding:1rem 0;">ยังไม่ได้เลือกวัตถุดิบ</p>';
    return;
  }
  
  let html = '';
  selectedIngredients.forEach(id => {
    const chip = document.querySelector(`.ing-chip[data-id="${id}"]`);
    if (chip) {
      const name = chip.dataset.name;
      html += `
        <div class="selected-item">
          <span style="flex:1;">${name}</span>
          <div class="remove-btn" onclick="removeIngredient('${id}')">
            <i class="fas fa-times"></i>
          </div>
        </div>
      `;
    }
  });
  
  list.innerHTML = html;
}

function removeIngredient(id) {
  selectedIngredients.delete(id);
  const chip = document.querySelector(`.ing-chip[data-id="${id}"]`);
  if (chip) chip.classList.remove('selected');
  updateSelectedList();
}

function clearAll() {
  if (selectedIngredients.size === 0) return;
  if (!confirm('ล้างวัตถุดิบที่เลือกทั้งหมด?')) return;
  
  selectedIngredients.clear();
  document.querySelectorAll('.ing-chip.selected').forEach(c => c.classList.remove('selected'));
  updateSelectedList();
}

function filterIngredients(query) {
  const lower = query.toLowerCase().trim();
  document.querySelectorAll('.ing-chip').forEach(chip => {
    const name = chip.dataset.name.toLowerCase();
    chip.style.display = name.includes(lower) ? '' : 'none';
  });
}

async function addCustomIngredient() {
  const input = document.getElementById('customInput');
  const name = input.value.trim();
  
  if (!name) {
    showToast('⚠️ กรุณาระบุชื่อวัตถุดิบ', true);
    return;
  }
  
  input.disabled = true;
  
  try {
    const fd = new FormData();
    fd.append('action', 'add_ingredient');
    fd.append('name', name);
    
    const res = await fetch('', { method: 'POST', body: fd });
    const data = await res.json();
    
    if (data.status === 'success' || data.status === 'exists') {
      // Add to grid if new
      if (data.status === 'success') {
        const grid = document.getElementById('ingredientGrid');
        const newChip = document.createElement('div');
        newChip.className = 'ing-chip';
        newChip.dataset.id = data.id;
        newChip.dataset.name = data.name;
        newChip.onclick = function() { toggleIngredient(this); };
        newChip.innerHTML = `
          <span class="check"><i class="fas fa-check"></i></span>
          <span>${data.name}</span>
        `;
        grid.insertBefore(newChip, grid.firstChild);
      }
      
      // Auto-select
      const chip = document.querySelector(`.ing-chip[data-id="${data.id}"]`);
      if (chip && !chip.classList.contains('selected')) {
        selectedIngredients.add(String(data.id));
        chip.classList.add('selected');
        updateSelectedList();
      }
      
      input.value = '';
      showToast(data.status === 'success' ? '✅ เพิ่มวัตถุดิบแล้ว' : '✅ เลือกวัตถุดิบแล้ว');
    } else {
      showToast('⚠️ ' + (data.message || 'เกิดข้อผิดพลาด'), true);
    }
  } catch (e) {
    console.error(e);
    showToast('⚠️ เกิดข้อผิดพลาด', true);
  } finally {
    input.disabled = false;
    input.focus();
  }
}

function askAIChef() {
  if (selectedIngredients.size === 0) return;
  
  const names = [];
  selectedIngredients.forEach(id => {
    const chip = document.querySelector(`.ing-chip[data-id="${id}"]`);
    if (chip) names.push(chip.dataset.name);
  });
  
  const message = encodeURIComponent('ผมมีวัตถุดิบ: ' + names.join(', ') + ' แนะนำเมนูที่ทำได้หน่อย');
  window.location.href = `ai_chef.php?ingredients=${message}`;
}

function showToast(msg, isError = false) {
  const toast = document.createElement('div');
  toast.textContent = msg;
  toast.style.cssText = `
    position:fixed;bottom:24px;right:24px;z-index:9999;
    background:${isError?'#fee2e2':'#f0fdf4'};
    color:${isError?'#dc2626':'#15803d'};
    border:1px solid ${isError?'#fecaca':'#bbf7d0'};
    padding:12px 20px;border-radius:12px;font-size:.85rem;font-weight:600;
    box-shadow:0 8px 24px rgba(0,0,0,.12);
    animation:slideInUp .3s ease;
  `;
  document.body.appendChild(toast);
  setTimeout(() => {
    toast.style.animation = 'slideOutDown .3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 2500);
}

const style = document.createElement('style');
style.textContent = `
  @keyframes slideInUp { from{opacity:0;transform:translateY(20px);} to{opacity:1;transform:translateY(0);} }
  @keyframes slideOutDown { from{opacity:1;transform:translateY(0);} to{opacity:0;transform:translateY(20px);} }
`;
document.head.appendChild(style);
</script>

</body>
</html>