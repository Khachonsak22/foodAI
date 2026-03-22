<?php
session_start();
include '../config/connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['first_name'] ?? 'คุณลูกค้า';

// ── แก้ไข: ดึงข้อมูลชื่อ นามสกุล และ รูปโปรไฟล์ จากฐานข้อมูล ──
$sql  = "SELECT first_name, last_name, profile_image FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

$profile_image = '';
$lastName = '';
if ($row = $res->fetch_assoc()) {
    $user_name = !empty($row['first_name']) ? $row['first_name'] : $user_name;
    $lastName  = $row['last_name'] ?? '';
    $profile_image = $row['profile_image'] ?? '';
}

$firstName = $user_name;
$initials  = mb_strtoupper(mb_substr($firstName,0,1)).mb_strtoupper(mb_substr($lastName,0,1));

// เตรียม URL รูปโปรไฟล์ (เช็กว่ามีไฟล์อยู่จริงไหม)
$profile_image_url = null;
if (!empty($profile_image) && file_exists("../public/uploads/avatars/" . $profile_image)) {
    $profile_image_url = "../public/uploads/avatars/" . htmlspecialchars($profile_image) . "?t=" . time();
}

// ── แก้ไขการดึงประวัติแชทให้แยกระหว่าง "ข้อความ" และ "ข้อมูลเมนูอาหาร" ──
$history   = [];
$chat_sql  = "SELECT sender, message FROM chat_logs WHERE user_id = ? ORDER BY id ASC";
$chat_stmt = $conn->prepare($chat_sql);
$chat_stmt->bind_param("i", $user_id);
$chat_stmt->execute();
$chat_res  = $chat_stmt->get_result();

while ($row = $chat_res->fetch_assoc()) {
    // ใช้ |||MENUS||| เป็นตัวแบ่งระหว่างข้อความแชท และ JSON ของเมนูที่แนะนำ
    $parts = explode('|||MENUS|||', $row['message']);
    $msg_text = $parts[0];
    $menus_json = isset($parts[1]) ? json_decode($parts[1], true) : null;
    
    $history[] = [
        'sender' => $row['sender'],
        'message' => $msg_text,
        'menus' => $menus_json
    ];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เชฟ AI — FoodAI</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
:root {
  --g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g400:#4ade80;
  --g500:#22c55e;--g600:#16a34a;--g700:#15803d;
  --t400:#2dd4bf;--t500:#14b8a6;
  --bg:#f5f8f5;--card:#ffffff;--bdr:#e8f0e9;
  --txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;
  --sb-w:248px;--sb-bdr:#e5ede6;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);display:flex;height:100vh;overflow:hidden;}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,#c8e6c9 1px,transparent 1px);background-size:28px 28px;opacity:.3;}

/* ── Sidebar (shared) ── */
.sidebar{width:var(--sb-w);height:100vh;background:#fff;border-right:1px solid var(--sb-bdr);display:flex;flex-direction:column;position:fixed;left:0;top:0;z-index:100;box-shadow:4px 0 24px rgba(34,197,94,.06);}
.sb-logo{padding:24px 22px 20px;border-bottom:1px solid var(--sb-bdr);display:flex;align-items:center;gap:11px;}
.sb-logo-icon{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--g500),var(--t500));display:flex;align-items:center;justify-content:center;font-size:1.1rem;box-shadow:0 4px 12px rgba(34,197,94,.35);flex-shrink:0;}
.sb-logo-text{font-family:'Nunito',sans-serif;font-size:1.18rem;font-weight:800;color:var(--g700);letter-spacing:-.02em;line-height:1;}
.sb-logo-sub{font-size:.6rem;font-weight:600;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin-top:2px;}
.sb-label{font-size:.6rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);padding:18px 22px 8px;}
.sb-nav{padding:6px 12px;display:flex;flex-direction:column;gap:2px;flex:1;overflow-y:auto;}
.nav-item{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:12px;text-decoration:none;color:var(--sub);font-size:.82rem;font-weight:500;transition:background .18s,color .18s,transform .18s;}
.nav-item:hover{background:var(--g50);color:var(--g700);transform:translateX(2px);}
.nav-item.active{background:var(--g50);color:var(--g600);font-weight:600;box-shadow:inset 3px 0 0 var(--g500);}
.nav-item.active .nav-icon-wrap{background:linear-gradient(135deg,var(--g500),var(--t500));color:white;box-shadow:0 3px 10px rgba(34,197,94,.38);}
.nav-icon-wrap{width:34px;height:34px;border-radius:10px;background:var(--g50);border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;transition:all .18s;color:var(--g600);}
.nav-item:hover .nav-icon-wrap{background:var(--g100);border-color:var(--g200);}
.nav-badge{margin-left:auto;background:var(--g500);color:white;font-size:.6rem;font-weight:700;padding:2px 7px;border-radius:99px;}
.nav-badge.orange{background:#f97316;}
.sb-divider{height:1px;background:var(--sb-bdr);margin:6px 12px;}
.sb-user{border-top:1px solid var(--sb-bdr);padding:16px;display:flex;align-items:center;gap:11px;background:var(--g50);flex-shrink:0;}
.sb-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--t400));display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:800;color:white;flex-shrink:0;font-family:'Nunito',sans-serif;box-shadow:0 2px 8px rgba(34,197,94,.3);}
.sb-user-name{font-size:.78rem;font-weight:600;color:var(--txt);line-height:1.2;}
.sb-logout{margin-left:auto;width:30px;height:30px;border-radius:8px;border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:.72rem;text-decoration:none;transition:all .18s;background:transparent;}
.sb-logout:hover{background:#fee2e2;border-color:#fecaca;color:#dc2626;}

/* Scroll container */
.scroll-box { display:flex;flex-direction:column;gap:9px;max-height:360px;overflow-y:auto;padding-right:3px; }
::-webkit-scrollbar { width:4px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:var(--g200);border-radius:99px; }

/* ── Page shell ── */
.page-wrap{margin-left:var(--sb-w);flex:1;display:flex;flex-direction:column;overflow:hidden;position:relative;z-index:1;}

/* ── Chat topbar ── */
.chat-topbar{height:66px;background:rgba(255,255,255,.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--sb-bdr);display:flex;align-items:center;padding:0 1.75rem;gap:14px;flex-shrink:0;box-shadow:0 2px 12px rgba(34,197,94,.05);}
.chef-avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--g100),var(--g200));border:2px solid var(--g200);display:flex;align-items:center;justify-content:center;font-size:1.3rem;position:relative;flex-shrink:0;}
.chef-online{position:absolute;bottom:1px;right:1px;width:11px;height:11px;border-radius:50%;background:var(--g500);border:2px solid white;}
@keyframes livePulse{0%,100%{transform:scale(1);opacity:1;}50%{transform:scale(.65);opacity:.3;}}
.chef-online{animation:livePulse 2s ease-in-out infinite;}
.tb-back{width:36px;height:36px;border-radius:10px;background:white;border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;color:var(--sub);text-decoration:none;font-size:.8rem;transition:all .18s;flex-shrink:0;}
.tb-back:hover{background:var(--g50);border-color:var(--g200);color:var(--g600);}

/* ── Chat area ── */
.chat-area{flex:1;overflow-y:auto;padding:24px 20px;display:flex;flex-direction:column;gap:0;}
.chat-area::-webkit-scrollbar{width:4px;}
.chat-area::-webkit-scrollbar-thumb{background:var(--g200);border-radius:99px;}

/* ── Bubbles ── */
@keyframes bubblePop{from{opacity:0;transform:scale(.94) translateY(8px);}to{opacity:1;transform:scale(1) translateY(0);}}
.msg-row{display:flex;align-items:flex-end;gap:10px;margin-bottom:16px;animation:bubblePop .3s cubic-bezier(.22,1,.36,1);}
.msg-row.user { flex-direction: row; justify-content: flex-end; gap: 10px; }

.bubble-ai{background:white;border:1px solid var(--bdr);border-radius:18px 18px 18px 4px;padding:14px 17px;max-width:75%;font-size:.85rem;line-height:1.72;color:var(--txt);box-shadow:0 2px 10px rgba(0,0,0,.05);}
.bubble-user { background: linear-gradient(135deg, var(--g500), var(--t500)); color: white; border-radius: 18px 18px 4px 18px; padding: 13px 17px; max-width: 75%; font-size: .85rem; line-height: 1.72; box-shadow: 0 4px 12px rgba(34, 197, 94, 0.2); }

.msg-avatar{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;}
.msg-avatar.ai{background:var(--g100);border:1.5px solid var(--g200);}
.msg-avatar.user-av{background:linear-gradient(135deg,var(--g400),var(--t400));color:white;font-size:.72rem;font-weight:800;font-family:'Nunito',sans-serif;}

/* Prose inside AI bubble */
.bubble-ai strong{color:var(--g700);font-weight:700;}
.bubble-ai ul{list-style:disc;padding-left:1.4rem;margin:.4rem 0;}
.bubble-ai ol{list-style:decimal;padding-left:1.4rem;margin:.4rem 0;}
.bubble-ai p{margin-bottom:.4rem;}

/* Loading dots */
@keyframes dot{0%,80%,100%{transform:scale(0);}40%{transform:scale(1);}}
.typing-dot{width:7px;height:7px;border-radius:50%;background:var(--g400);display:inline-block;animation:dot 1.2s ease-in-out infinite;}
.typing-dot:nth-child(2){animation-delay:.2s;}
.typing-dot:nth-child(3){animation-delay:.4s;}

/* Menu cards */
.menu-suggest{background:var(--g50);border:1.5px solid var(--g200);border-radius:15px;padding:13px 15px;display:flex;justify-content:space-between;align-items:center;gap:12px;transition:box-shadow .18s,border-color .18s;animation:bubblePop .3s cubic-bezier(.22,1,.36,1);}
.menu-suggest:hover{box-shadow:0 4px 16px rgba(34,197,94,.12);border-color:var(--g400);}
.menu-suggest-name{font-size:.85rem;font-weight:600;color:var(--g700);}
.menu-suggest-meta{font-size:.72rem;color:var(--muted);margin-top:3px;}
.save-btn{flex-shrink:0;background:white;border:1.5px solid var(--g200);color:var(--g600);font-size:.7rem;font-weight:700;padding:7px 14px;border-radius:10px;cursor:pointer;transition:all .18s;display:flex;align-items:center;gap:5px;font-family:'Kanit',sans-serif;}
.save-btn.saved{background:var(--g500);color:white;border-color:var(--g500);pointer-events:none;}

/* Quick chips */
.chips-row{display:flex;flex-wrap:wrap;gap:7px;padding:10px 20px 4px;flex-shrink:0;}
.chip{background:white;border:1.5px solid var(--bdr);border-radius:99px;padding:6px 14px;font-size:.73rem;font-weight:500;color:var(--sub);cursor:pointer;transition:all .18s;font-family:'Kanit',sans-serif;}
.chip:hover{border-color:var(--g400);background:var(--g50);color:var(--g700);}

/* Input area */
.input-bar{padding:12px 20px 16px;background:rgba(255,255,255,.92);backdrop-filter:blur(12px);border-top:1px solid var(--sb-bdr);flex-shrink:0;}
.input-wrap{background:white;border:2px solid var(--bdr);border-radius:16px;display:flex;align-items:center;gap:10px;padding:6px 6px 6px 16px;transition:border-color .18s,box-shadow .18s;}
.input-wrap:focus-within{border-color:var(--g400);box-shadow:0 0 0 3px rgba(74,222,128,.12);}
.chat-input{flex:1;border:none;outline:none;background:transparent;font-family:'Kanit',sans-serif;font-size:.88rem;color:var(--txt);}
.chat-input::placeholder{color:var(--muted);}
.send-btn{width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,var(--g500),var(--t500));border:none;color:white;font-size:.85rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:opacity .18s,box-shadow .18s;flex-shrink:0;box-shadow:0 3px 10px rgba(34,197,94,.35);}
.send-btn:hover{opacity:.88;box-shadow:0 5px 16px rgba(34,197,94,.42);}
.send-btn:disabled{opacity:.5;cursor:not-allowed;}

@media (max-width: 1024px) {
  .sidebar { transform: translateX(-100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
  .sidebar.show { transform: translateX(0); }
  .page-wrap { margin-left: 0 !important; }
}
</style>
</head>
<body>

<?php include '../includes/sidebar.php' ?>

<div class="page-wrap">

  <div class="chat-topbar">
    <a href="dashboard.php" class="tb-back"><i class="fas fa-arrow-left"></i></a>
    <div class="chef-avatar">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chef-hat" style="color: #c1c1c1;">
        <path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"/>
        <line x1="6" x2="18" y1="17" y2="17"/>
      </svg>
      <span class="chef-online"></span>
    </div>
    <div>
      <div style="font-family:'Nunito',sans-serif;font-size:.95rem;font-weight:800;color:var(--txt);">เชฟ AI อัจฉริยะ</div>
      <div style="font-size:.7rem;color:var(--g600);font-weight:500;">พร้อมให้บริการคุณ <?= htmlspecialchars($user_name) ?></div>
    </div>
    <div style="margin-left:auto;display:flex;align-items:center;gap:6px;background:var(--g50);border:1px solid var(--g200);border-radius:10px;padding:6px 12px;">
      <span style="width:7px;height:7px;border-radius:50%;background:var(--g500);display:inline-block;animation:livePulse 2s ease-in-out infinite;"></span>
      <span style="font-size:.68rem;font-weight:700;color:var(--g600);">Online</span>
    </div>
  </div>

  <div class="chat-area" id="chatArea">
    <div class="msg-row">
      <div class="msg-avatar ai">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chef-hat" style="color: #c1c1c1;">
          <path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"/>
          <line x1="6" x2="18" y1="17" y2="17"/>
        </svg></div>
      <div class="bubble-ai">
        สวัสดีครับ! วันนี้อยากทานอะไร หรือให้เชฟช่วยคิดเมนูสำหรับ 1 วันก็ได้นะครับ (เชฟจะเช็คโรคประจำตัวและอาหารที่แพ้ในฐานข้อมูลให้ด้วยครับ)
      </div>
    </div>
  </div>

  <div class="chips-row" id="chipsRow">
    <span class="chip" onclick="sendChip(this)">จัดเมนู 3 มื้อ + 1 ว่าง สำหรับวันนี้</span>
    <span class="chip" onclick="sendChip(this)">เมนูไก่วันนี้</span>
    <span class="chip" onclick="sendChip(this)">เมนูสุขภาพที่ฉันกินได้</span>
  </div>

  <div class="input-bar">
    <div class="input-wrap">
      <input type="text" class="chat-input" id="aiQuery" placeholder="พิมพ์ความต้องการ เช่น 'จัดเมนู 1 วันให้หน่อย'..." autocomplete="off">
      <button class="send-btn" id="sendBtn" onclick="sendUserMessage()">
        <i class="fas fa-paper-plane"></i>
      </button>
    </div>
  </div>

</div>

<script>
const chatArea   = document.getElementById('chatArea');
const inputField = document.getElementById('aiQuery');
const sendBtn    = document.getElementById('sendBtn');
const chipsRow   = document.getElementById('chipsRow');

// ── แก้ไข: ดึงข้อมูล Initial และ Profile Image URL มาใช้งานใน JS ──
const initials        = <?= json_encode($initials ?: 'U') ?>;
const profileImageUrl = <?= json_encode($profile_image_url) ?>;

const chatHistory = <?= json_encode($history) ?>;
const urlParams  = new URLSearchParams(window.location.search);
const autoMessage = urlParams.get('ingredients');

function aiAvatar() { 
  return `<div class="msg-avatar ai">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chef-hat" style="color: #c1c1c1;">
        <path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"/>
        <line x1="6" x2="18" y1="17" y2="17"/>
      </svg></div>`; 
}

// ── แก้ไข: ฟังก์ชันแสดงรูปโปรไฟล์ของ User ในกล่องแชท ──
function userAvatar() { 
  if (profileImageUrl) {
    return `<div class="msg-avatar user-av" style="overflow:hidden; padding:0;"><img src="${profileImageUrl}" style="width:100%;height:100%;object-fit:cover;" alt="User"></div>`;
  }
  return `<div class="msg-avatar user-av">${initials}</div>`; 
}

function renderMessage(sender, text, animate = false, menus = null) {
  const rowEl = document.createElement('div');
  rowEl.className = 'msg-row' + (sender === 'user' ? ' user' : '');
  
  if (sender === 'user') {
    rowEl.innerHTML = `<div class="bubble-user">${escapeHtml(text)}</div>${userAvatar()}`;
  } else {
    rowEl.innerHTML = `
      ${aiAvatar()}
      <div>
        <div class="bubble-ai prose">${marked.parse(text)}</div>
      </div>
    `;
  }
  
  chatArea.appendChild(rowEl);

  // ── ส่วนสร้างการ์ดเมนูโดยมีป้ายบอกว่าเซฟอัตโนมัติแล้ว ──
  if (menus && menus.length > 0) {
      const wrap = document.createElement('div');
      wrap.style.cssText = 'margin-left:42px;display:flex;flex-direction:column;gap:8px;margin-bottom:16px;max-width:75%;';
      
      menus.forEach(menu => {
        const card = document.createElement('div');
        card.className = 'menu-suggest';
        card.innerHTML = `
          <div style="min-width:0;">
            <div class="menu-suggest-name"><i class="fas fa-utensils" style="color: #22c55e;"></i> ${escapeHtml(menu.name)}</div>
            <div class="menu-suggest-meta"><i class="bi bi-fire" style="color: #ff5722;"></i> ${menu.calories} kcal &bull; ${escapeHtml(menu.desc)}</div>
          </div>
          <div class="save-btn saved" style="cursor:default;">
            <i class="fas fa-check"></i> บันทึกอัตโนมัติแล้ว
          </div>`;
        wrap.appendChild(card);
      });
      chatArea.appendChild(wrap);
  }

  scrollBottom();
}

function escapeHtml(t) { return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function scrollBottom() { chatArea.scrollTo({ top: chatArea.scrollHeight, behavior: 'smooth' }); }

document.addEventListener('DOMContentLoaded', async () => {
  // โหลดประวัติแชทพร้อมปุ่มเมนู
  chatHistory.forEach(c => renderMessage(c.sender, c.message, false, c.menus));
  chatArea.scrollTop = chatArea.scrollHeight;

  if (autoMessage) {
    inputField.value = autoMessage; 
    sendUserMessage();             
    window.history.replaceState({}, document.title, window.location.pathname);
  }
});

async function sendUserMessage() {
  const text = inputField.value.trim();
  if (!text) return;

  chipsRow.style.display = 'none';
  renderMessage('user', text, true);
  inputField.value = '';
  sendBtn.disabled = true;

  const loadId = 'ld-' + Date.now();
  const loadRow = document.createElement('div');
  loadRow.id = loadId;
  loadRow.className = 'msg-row';
  loadRow.innerHTML = `${aiAvatar()}<div class="bubble-ai" style="display:flex;align-items:center;gap:6px;padding:14px 18px;">
    <span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>
  </div>`;
  chatArea.appendChild(loadRow);
  scrollBottom();

  try {
    const res  = await fetch('../api/api_chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text })
    });
    const data = await res.json();
    document.getElementById(loadId)?.remove();

    renderMessage('ai', data.chat_response, true, data.recommended_menus);

  } catch (e) {
    document.getElementById(loadId)?.remove();
    renderMessage('ai', 'ขออภัย เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้งครับ');
  } finally {
    sendBtn.disabled = false;
    inputField.focus();
  }
}

function sendChip(el) {
  inputField.value = el.textContent.replace(/^[^\s]+\s/,'').trim();
  sendUserMessage();
}

inputField.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendUserMessage(); } });
</script>
<script>
// เช็คคำทักทายตอนเช้าเมื่อเปิดหน้าเว็บ
document.addEventListener('DOMContentLoaded', function() {
    fetch('api_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_greeting' })
    })
    .then(response => response.json())
    .then(data => {
        // ถ้าระบบส่งคำทักทายมา (แปลว่าเพิ่งเข้ามาครั้งแรกของวัน) ให้โหลดหน้าใหม่เพื่อแสดงแชท
        if (data.chat_response && data.chat_response !== "") {
            window.location.reload(); 
        }
    })
    .catch(error => console.error('Error fetching greeting:', error));
});
</script>
</body>
</html>