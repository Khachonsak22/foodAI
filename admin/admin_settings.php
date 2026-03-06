<?php
session_start();
include '../config/connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูล User
$u_stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$u_data = $u_stmt->get_result()->fetch_assoc();
$firstName = $u_data['first_name'] ?? 'User';
$lastName = $u_data['last_name'] ?? '';
$initials = mb_strtoupper(mb_substr($firstName,0,1)).mb_strtoupper(mb_substr($lastName,0,1));

// สร้างตาราง ai_settings ถ้ายังไม่มี
$conn->query("CREATE TABLE IF NOT EXISTS ai_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    api_key TEXT,
    model_name VARCHAR(100) DEFAULT 'gemini-1.5-flash',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user (user_id)
)");

// ดึงการตั้งค่าปัจจุบัน
$settings_stmt = $conn->prepare("SELECT api_key, model_name FROM ai_settings WHERE user_id = ?");
$settings_stmt->bind_param("i", $user_id);
$settings_stmt->execute();
$settings_result = $settings_stmt->get_result();
$current_settings = $settings_result->fetch_assoc();

$current_api_key = $current_settings['api_key'] ?? '';
$current_model = $current_settings['model_name'] ?? 'gemini-1.5-flash';

// บันทึกการตั้งค่า
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_api_key = trim($_POST['api_key'] ?? '');
    $new_model = trim($_POST['model_name'] ?? 'gemini-1.5-flash');
    
    if (empty($new_api_key)) {
        $error_msg = 'กรุณาใส่ API Key';
    } else {
        // ทดสอบ API Key
        $test_url = "https://generativelanguage.googleapis.com/v1beta/models/{$new_model}:generateContent?key={$new_api_key}";
        $test_data = json_encode([
            "contents" => [["parts" => [["text" => "Hello"]]]]
        ]);
        
        $ch = curl_init($test_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $test_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $test_response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpcode == 200) {
            // API Key ใช้งานได้
            $save_stmt = $conn->prepare("INSERT INTO ai_settings (user_id, api_key, model_name) 
                                         VALUES (?, ?, ?) 
                                         ON DUPLICATE KEY UPDATE api_key = ?, model_name = ?");
            $save_stmt->bind_param("issss", $user_id, $new_api_key, $new_model, $new_api_key, $new_model);
            
            if ($save_stmt->execute()) {
                $success_msg = 'บันทึกการตั้งค่าสำเร็จ!';
                $current_api_key = $new_api_key;
                $current_model = $new_model;
            } else {
                $error_msg = 'เกิดข้อผิดพลาดในการบันทึก';
            }
        } else {
            $error_msg = 'API Key หรือ Model ไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
        }
    }
}

// Models ที่รองรับ
$available_models = [
    'gemini-1.5-flash' => 'Gemini 1.5 Flash (แนะนำ - เร็วและประหยัด)',
    'gemini-1.5-flash-8b' => 'Gemini 1.5 Flash 8B (เร็วที่สุด)',
    'gemini-1.5-pro' => 'Gemini 1.5 Pro (ฉลาดที่สุด)',
    'gemini-2.0-flash-exp' => 'Gemini 2.0 Flash (ทดลอง)',
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ตั้งค่า AI — FoodAI</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
  --g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;
  --g400:#4ade80;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;
  --bg:#f5f8f5;--card:#fff;--bdr:#e8f0e9;
  --txt:#1a2e1a;--sub:#4b6b4e;--muted:#8da98f;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Kanit',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;}
.page-wrap{margin-left:248px;flex:1;position:relative;z-index:1;}
main{padding:2rem 2.5rem 3.5rem;max-width:1000px;margin:0 auto;}

.header{margin-bottom:2.5rem;}
.header-top{display:flex;align-items:center;gap:12px;margin-bottom:1rem;}
.back-btn{
  padding:10px 18px;
  background:#fff;
  border:2px solid var(--bdr);
  border-radius:12px;
  color:var(--sub);
  text-decoration:none;
  font-weight:600;
  font-size:.9rem;
  transition:all .2s;
  display:inline-flex;
  align-items:center;
  gap:8px;
}
.back-btn:hover{background:var(--g50);border-color:var(--g300);color:var(--g700);}
.page-title{
  font-family:'Nunito',sans-serif;
  font-size:2rem;
  font-weight:800;
  color:var(--txt);
}
.page-subtitle{color:var(--muted);font-size:.95rem;}

.alert{
  padding:16px 20px;
  border-radius:14px;
  margin-bottom:1.5rem;
  display:flex;
  align-items:center;
  gap:12px;
  font-size:.9rem;
  font-weight:500;
}
.alert-success{background:#dcfce7;color:#15803d;border:2px solid #86efac;}
.alert-error{background:#fee;color:#dc2626;border:2px solid #fca5a5;}
.alert i{font-size:1.2rem;}

.settings-card{
  background:var(--card);
  border:2px solid var(--bdr);
  border-radius:20px;
  padding:2.5rem;
  box-shadow:0 4px 20px rgba(0,0,0,.04);
}
.section-title{
  font-family:'Nunito',sans-serif;
  font-size:1.3rem;
  font-weight:800;
  color:var(--txt);
  margin-bottom:1.5rem;
  display:flex;
  align-items:center;
  gap:10px;
}
.section-title i{
  color:var(--g500);
  font-size:1.2rem;
}

.form-group{margin-bottom:2rem;}
.form-label{
  display:block;
  font-weight:600;
  color:var(--txt);
  margin-bottom:8px;
  font-size:.95rem;
}
.form-label .required{color:#ef4444;}
.form-input{
  width:100%;
  padding:14px 18px;
  border:2px solid var(--bdr);
  border-radius:12px;
  font-family:'Kanit',sans-serif;
  font-size:.95rem;
  color:var(--txt);
  transition:all .2s;
  background:#fff;
}
.form-input:focus{
  outline:none;
  border-color:var(--g400);
  box-shadow:0 0 0 4px rgba(34,197,94,.1);
}
.form-select{
  width:100%;
  padding:14px 18px;
  border:2px solid var(--bdr);
  border-radius:12px;
  font-family:'Kanit',sans-serif;
  font-size:.95rem;
  color:var(--txt);
  background:#fff;
  cursor:pointer;
  transition:all .2s;
}
.form-select:focus{
  outline:none;
  border-color:var(--g400);
  box-shadow:0 0 0 4px rgba(34,197,94,.1);
}
.form-help{
  margin-top:8px;
  font-size:.85rem;
  color:var(--muted);
  line-height:1.5;
}
.form-help a{
  color:var(--g600);
  text-decoration:underline;
}

.password-toggle{
  position:relative;
}
.toggle-btn{
  position:absolute;
  right:14px;
  top:50%;
  transform:translateY(-50%);
  background:none;
  border:none;
  color:var(--muted);
  cursor:pointer;
  font-size:1.1rem;
  padding:4px;
  transition:color .2s;
}
.toggle-btn:hover{color:var(--g600);}

.info-box{
  background:var(--g50);
  border:2px solid var(--g200);
  border-radius:14px;
  padding:18px;
  margin-bottom:2rem;
}
.info-box-title{
  font-weight:700;
  color:var(--g700);
  margin-bottom:8px;
  display:flex;
  align-items:center;
  gap:8px;
}
.info-box-content{
  color:var(--sub);
  font-size:.9rem;
  line-height:1.6;
}
.info-box-content ul{
  margin:8px 0 0 20px;
}

.btn-group{
  display:flex;
  gap:1rem;
  margin-top:2rem;
}
.btn{
  padding:14px 32px;
  border-radius:12px;
  font-weight:700;
  font-size:.95rem;
  border:none;
  cursor:pointer;
  transition:all .3s;
  display:inline-flex;
  align-items:center;
  gap:8px;
  text-decoration:none;
}
.btn-primary{
  background:linear-gradient(135deg,var(--g500),var(--g600));
  color:#fff;
  box-shadow:0 4px 16px rgba(34,197,94,.3);
}
.btn-primary:hover{
  transform:translateY(-2px);
  box-shadow:0 8px 24px rgba(34,197,94,.4);
}
.btn-secondary{
  background:#fff;
  color:var(--sub);
  border:2px solid var(--bdr);
}
.btn-secondary:hover{
  background:var(--g50);
  border-color:var(--g300);
}

.current-config{
  background:#fff;
  border:2px solid var(--g200);
  border-radius:14px;
  padding:18px;
  margin-top:1.5rem;
}
.config-item{
  display:flex;
  justify-content:space-between;
  padding:10px 0;
  border-bottom:1px solid var(--bdr);
}
.config-item:last-child{border-bottom:none;}
.config-label{color:var(--muted);font-size:.9rem;}
.config-value{font-weight:600;color:var(--txt);font-family:monospace;}

@media (max-width:768px){
  .page-wrap{margin-left:0;padding-top:60px;}
  .btn-group{flex-direction:column;}
}
</style>
</head>
<body>

<?php include '../includes/sidebar.php'; ?>

<div class="page-wrap">
  <main>
    
    <div class="header">
      <div class="header-top">
        <a href="ai_chat.php" class="back-btn">
          <i class="fas fa-arrow-left"></i> กลับ
        </a>
      </div>
      <h1 class="page-title">⚙️ ตั้งค่า AI</h1>
      <p class="page-subtitle">กำหนดค่า API Key และเลือก Model สำหรับระบบ AI</p>
    </div>

    <?php if ($success_msg): ?>
    <div class="alert alert-success">
      <i class="fas fa-check-circle"></i>
      <span><?= $success_msg ?></span>
    </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
    <div class="alert alert-error">
      <i class="fas fa-exclamation-circle"></i>
      <span><?= $error_msg ?></span>
    </div>
    <?php endif; ?>

    <div class="settings-card">
      <div class="section-title">
        <i class="fas fa-robot"></i>
        <span>การตั้งค่า Google Gemini API</span>
      </div>

      <div class="info-box">
        <div class="info-box-title">
          <i class="fas fa-info-circle"></i>
          วิธีรับ API Key ฟรี
        </div>
        <div class="info-box-content">
          <ul>
            <li>เข้าไปที่ <a href="https://aistudio.google.com/apikey" target="_blank">Google AI Studio</a></li>
            <li>คลิก "Get API Key" หรือ "Create API Key"</li>
            <li>เลือก Project หรือสร้างใหม่</li>
            <li>Copy API Key มาวางด้านล่าง</li>
          </ul>
        </div>
      </div>

      <form method="POST">
        
        <!-- API Key -->
        <div class="form-group">
          <label class="form-label">
            API Key <span class="required">*</span>
          </label>
          <div class="password-toggle">
            <input 
              type="password" 
              name="api_key" 
              id="apiKey"
              class="form-input" 
              value="<?= htmlspecialchars($current_api_key) ?>"
              placeholder="AIzaSy..."
              required
            >
            <button type="button" class="toggle-btn" onclick="toggleApiKey()">
              <i class="fas fa-eye" id="eyeIcon"></i>
            </button>
          </div>
          <div class="form-help">
            กรอก API Key จาก <a href="https://aistudio.google.com/apikey" target="_blank">Google AI Studio</a> (ฟรี)
          </div>
        </div>

        <!-- Model Selection -->
        <div class="form-group">
          <label class="form-label">
            Model <span class="required">*</span>
          </label>
          <select name="model_name" class="form-select" required>
            <?php foreach ($available_models as $model_key => $model_label): ?>
            <option value="<?= $model_key ?>" <?= $current_model == $model_key ? 'selected' : '' ?>>
              <?= $model_label ?>
            </option>
            <?php endforeach; ?>
          </select>
          <div class="form-help">
            <strong>แนะนำ:</strong> Gemini 1.5 Flash - สมดุลระหว่างความเร็วและความฉลาด
          </div>
        </div>

        <!-- Current Configuration -->
        <?php if ($current_api_key): ?>
        <div class="current-config">
          <h4 style="margin-bottom:12px;font-size:1rem;color:var(--txt);">
            <i class="fas fa-cog"></i> การตั้งค่าปัจจุบัน
          </h4>
          <div class="config-item">
            <span class="config-label">API Key:</span>
            <span class="config-value"><?= substr($current_api_key, 0, 10) ?>...<?= substr($current_api_key, -5) ?></span>
          </div>
          <div class="config-item">
            <span class="config-label">Model:</span>
            <span class="config-value"><?= $current_model ?></span>
          </div>
          <div class="config-item">
            <span class="config-label">สถานะ:</span>
            <span class="config-value" style="color:var(--g600);">
              <i class="fas fa-check-circle"></i> พร้อมใช้งาน
            </span>
          </div>
        </div>
        <?php endif; ?>

        <!-- Buttons -->
        <div class="btn-group">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> บันทึกการตั้งค่า
          </button>
          <a href="ai_chat.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> ยกเลิก
          </a>
        </div>

      </form>
    </div>

  </main>
</div>

<script>
function toggleApiKey() {
  const input = document.getElementById('apiKey');
  const icon = document.getElementById('eyeIcon');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'fas fa-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'fas fa-eye';
  }
}
</script>

</body>
</html>