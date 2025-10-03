<?php
require __DIR__ . '/config_mysqli.php';
require __DIR__ . '/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: login.php'); exit;
}
if (!csrf_check($_POST['csrf'] ?? '')) {
  $_SESSION['flash'] = 'Invalid request. Please try again.';
  header('Location: login.php'); exit;
}

$email = trim($_POST['email'] ?? '');
$pass  = $_POST['password'] ?? '';

if ($email === '' || $pass === '') {
  $_SESSION['flash'] = 'Email and password are required.';
  header('Location: login.php'); exit;
}

try {
  $stmt = $mysqli->prepare('SELECT id, email, password_hash, display_name FROM users WHERE email = ? LIMIT 1');
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $result = $stmt->get_result();
  $user = $result->fetch_assoc();
  $stmt->close();

  $ok = $user && password_verify($pass, $user['password_hash']);
  if (!$ok) {
    usleep(250000); // 250ms
    $_SESSION['flash'] = 'Invalid email or password.';
    header('Location: login.php'); exit;
  }

  $_SESSION['user_id'] = (int)$user['id'];
  $_SESSION['user_name'] = $user['display_name'] ?: $user['email'];

  $stmt2 = $mysqli->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
  $stmt2->bind_param('i', $_SESSION['user_id']);
  $stmt2->execute();
  $stmt2->close();

  header('Location: dashboard.php'); exit;

} catch (Throwable $e) {
  $_SESSION['flash'] = 'Server error. Please try again.';
  header('Location: login.php'); exit;
}
