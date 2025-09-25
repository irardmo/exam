<?php require_once __DIR__ . '/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Exam System - Login</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="card">
    <h1>Welcome Back</h1>
    <p class="subtitle">Sign in to continue</p>

    <?php if(!empty($error)): ?>
      <p style="color:#f87171"><?= h($error) ?></p>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="action" value="login">

      <label>Username</label>
      <input name="username" placeholder="Enter your username" required>

      <label>Password</label>
      <input type="password" name="password" placeholder="Enter your password" required>

      <button type="submit">Login</button>
    </form>

    <div class="alt-link">
      Don’t have an account? <a href="register.php">Create a new account</a>
    </div>

    <p class="alt-link" style="margin-top:8px">
    </p>
  </div>
</body>
</html>
