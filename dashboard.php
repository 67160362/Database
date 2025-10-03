<?php
require __DIR__ . '/config_mysqli.php';
if (empty($_SESSION['user_id'])) {
  header('Location: login.php'); exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-white">
  <nav class="navbar navbar-light bg-light border-bottom mb-4">
    <div class="container">
      <span class="navbar-brand">MyApp</span>
      <div class="d-flex align-items-center gap-3">
        <span class="text-muted small">Hi, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        <a class="btn btn-outline-secondary btn-sm" href="logout.php">Logout</a>
      </div>
    </div>
  </nav>

  <div class="container">
    <div class="p-4 border rounded-3">
      <h1 class="h4 mb-2">You are logged in ✅</h1>
      <p class="mb-0">This is a protected page. Put your app here.</p>
    </div>
  </div>
</body>
</html>
