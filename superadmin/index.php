<?php
// superadmin/index.php  — Login page
require_once __DIR__ . '/config.php';

// Already logged in → redirect
if (!empty($_SESSION['sa_authed'])) {
    header('Location: dashboard.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($user === SA_USERNAME && $pass === SA_PASSWORD) {
        $_SESSION['sa_authed'] = true;
        $_SESSION['csrf']      = bin2hex(random_bytes(32));
        header('Location: dashboard.php'); exit;
    }
    $error = 'Invalid username or password.';
    // Small delay to slow brute-force
    sleep(1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>EvacuMate — Superadmin Login</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5;
           display: flex; align-items: center; justify-content: center;
           min-height: 100vh; }
    .box { background: #fff; border-radius: 16px; padding: 40px 36px;
           width: 100%; max-width: 380px;
           box-shadow: 0 4px 24px rgba(0,0,0,.1); }
    .logo { text-align: center; margin-bottom: 28px; }
    .logo .icon { font-size: 2.5rem; }
    .logo h1 { font-size: 1.4rem; font-weight: 700; color: #ef5350; margin-top: 8px; }
    .logo p  { font-size: .85rem; color: #888; }
    label { display: block; font-size: .82rem; font-weight: 600;
            color: #555; margin-bottom: 4px; }
    input { width: 100%; padding: 10px 14px; border: 1px solid #ddd;
            border-radius: 8px; font-size: .9rem; margin-bottom: 16px; }
    input:focus { outline: none; border-color: #ef5350; }
    .btn { width: 100%; padding: 11px; background: #ef5350; color: #fff;
           border: none; border-radius: 8px; font-size: 1rem;
           font-weight: 700; cursor: pointer; transition: opacity .15s; }
    .btn:hover { opacity: .88; }
    .error { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a;
             border-radius: 8px; padding: 10px 14px;
             font-size: .85rem; margin-bottom: 16px; }
    .warning { background: #fff8e1; color: #e65100; border: 1px solid #ffcc80;
               border-radius: 8px; padding: 10px 14px;
               font-size: .8rem; margin-top: 16px; text-align: center; }
  </style>
</head>
<body>
<div class="box">
  <div class="logo">
    <div class="icon">📍</div>
    <h1>Superadmin Panel</h1>
    <p>EvacuMate &mdash; Restricted Access</p>
  </div>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <label for="email">Username</label>
    <input type="text" id="username" name="username"
           autocomplete="off" required>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>

    <button class="btn" type="submit">Sign In</button>
  </form>

  <div class="warning">
    ⚠️ This panel is for superadmin use only.<br>
    Unauthorised access is prohibited.
  </div>
</div>
</body>
</html>