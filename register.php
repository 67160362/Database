<?php
require __DIR__ . '/config_mysqli.php';
require __DIR__ . '/csrf.php';

/* =========================
   Register (minimal-change)
   ========================= */

$errors = [];
$success = "";
// คงตัวแปรเดิมไว้เพื่อแสดงค่าเดิมในฟอร์ม
$username = $email = $full_name = "";

// ฟังก์ชันกัน XSS เวลา echo กลับ
function e($str){ return htmlspecialchars($str ?? "", ENT_QUOTES, "UTF-8"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // --- CSRF ให้ตรงระบบ (ใช้ name="csrf" + csrf_check()) ---
  if (empty($_POST['csrf']) || !csrf_check($_POST['csrf'])) {
    $errors[] = "CSRF token ไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองอีกครั้ง";
  }

  // รับค่าจากฟอร์ม (คงชื่อฟิลด์เดิมไว้)
  $username  = trim($_POST['username'] ?? "");
  $password  = $_POST['password'] ?? "";
  $email     = trim($_POST['email'] ?? "");
  $full_name = trim($_POST['name'] ?? "");

  // ตรวจความถูกต้องเดิม ๆ (คง regex username ไว้ถึงแม้จะไม่บันทึกลง DB)
  if ($username === "" || !preg_match('/^[A-Za-z0-9_\.]{3,30}$/', $username)) {
    $errors[] = "กรุณากรอก username 3–30 ตัวอักษร (a-z, A-Z, 0-9, _, .)";
  }
  if (strlen($password) < 8) {
    $errors[] = "รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร";
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "อีเมลไม่ถูกต้อง";
  }
  if ($full_name === "" || mb_strlen($full_name) > 190) {
    $errors[] = "กรุณากรอกชื่อ–นามสกุล (ไม่เกิน 190 ตัวอักษร)";
  }

  // --- ตรวจซ้ำด้วยอีเมลอย่างเดียว (ตารางจริงไม่มี username) ---
  if (!$errors) {
    $sql = "SELECT 1 FROM users WHERE email = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
      $errors[] = "อีเมลนี้ถูกใช้แล้ว";
    }
    $stmt->close();
  }

  // --- INSERT ให้ตรงสคีมา: email, display_name, password_hash ---
  if (!$errors) {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (email, display_name, password_hash) VALUES (?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("sss", $email, $full_name, $password_hash); // map $full_name -> display_name
    if ($stmt->execute()) {
      $success = "สมัครสมาชิกสำเร็จ! คุณสามารถล็อกอินได้แล้วค่ะ";
      // เคลียร์ฟอร์ม
      $username = $email = $full_name = "";
    } else {
      // duplicate email
      if ($mysqli->errno == 1062) {
        $errors[] = "อีเมลนี้ถูกใช้แล้ว";
      } else {
        $errors[] = "บันทึกข้อมูลไม่สำเร็จ: ".e($mysqli->error);
      }
    }
    $stmt->close();
  }
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register</title>
  <style>
    body{font-family:system-ui, sans-serif; background:#f7f7fb; margin:0; padding:0;}
    .container{max-width:480px; margin:40px auto; background:#fff; border-radius:16px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,.06);}
    h1{margin:0 0 16px;}
    .alert{padding:12px 14px; border-radius:12px; margin-bottom:12px; font-size:14px;}
    .alert.error{background:#ffecec; color:#a40000; border:1px solid #ffc9c9;}
    .alert.success{background:#efffed; color:#0a7a28; border:1px solid #c9f5cf;}
    label{display:block; font-size:14px; margin:10px 0 6px;}
    input{width:100%; padding:12px; border-radius:12px; border:1px solid #ddd;}
    button{width:100%; padding:12px; border:none; border-radius:12px; margin-top:14px; background:#3b82f6; color:#fff; font-weight:600; cursor:pointer;}
    button:hover{filter:brightness(.95);}
    .hint{font-size:12px; color:#666;}
  </style>
</head>
<body>
  <div class="container">
    <h1>สมัครสมาชิก</h1>

    <?php if (!empty($errors)): ?>
      <div class="alert error">
        <?php foreach ($errors as $m) echo "<div>".e($m)."</div>"; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="alert success"><?= e($success) ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <!-- ใช้ชื่อฟิลด์ csrf ให้ตรงระบบ -->
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <label>Username</label>
      <input type="text" name="username" value="<?= e($username) ?>" required>
      <div class="hint">อนุญาต a-z, A-Z, 0-9, _ และ . (3–30 ตัว)</div>

      <label>Password</label>
      <input type="password" name="password" required>
      <div class="hint">อย่างน้อย 8 ตัวอักษร</div>

      <label>Email</label>
      <input type="email" name="email" value="<?= e($email) ?>" required>

      <label>ชื่อ–นามสกุล</label>
      <input type="text" name="name" value="<?= e($full_name) ?>" required>

      <button type="submit">สมัครสมาชิก</button>
    </form>
  </div>
</body>
</html>
