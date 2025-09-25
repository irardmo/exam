<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $username    = trim($_POST['username'] ?? '');
    $password    = $_POST['password'] ?? '';
    $last_name   = trim($_POST['last_name'] ?? '');
    $course      = trim($_POST['course'] ?? '');
    $year_sec    = trim($_POST['year_section'] ?? '');

    if ($username === '' || $password === '' || $name === '' || $last_name === '' || $course === '' || $year_sec === '') {
        $error = "All fields are required.";
    } else {
        // check if username exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Username already exists!";
        } else {
            $stmt->close();

            // hash password
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // insert user
            $stmt2 = $conn->prepare("
                INSERT INTO users (username, password_hash, role, name, last_name, course, year_section, created_at)
                VALUES (?, ?, 'student', ?, ?, ?, ?, NOW())
            ");
            $stmt2->bind_param("ssssss", $username, $hashed, $name, $last_name, $course, $year_sec);

            if ($stmt2->execute()) {
                $success = "Registration successful! You can now login.";
            } else {
                $error = "Database error: " . $conn->error;
            }
            $stmt2->close();
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Exam System - Register</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="style-all.css">
</head>
<body>
  <div class="card">
    <h1>Create Account</h1>
    <p class="subtitle">Student Registration</p>

    <?php if(!empty($error)): ?>
      <p style="color:#f87171"><?= h($error) ?></p>
    <?php endif; ?>
    <?php if(!empty($success)): ?>
      <p style="color:#4ade80"><?= h($success) ?></p>
    <?php endif; ?>

   <form method="post">
  <label>Username</label>
  <input name="username" placeholder="Enter a username" required>

  <label>Password</label>
  <input type="password" name="password" placeholder="Create a password" required>

  <label>First Name</label>
  <input name="first_name" placeholder="Your first name" required>

  <label>Last Name</label>
  <input name="last_name" placeholder="Your last name" required>

  <label>Course</label>
  <input name="course" placeholder="e.g. BSIT" required>

  <label>Year & Section</label>
  <input name="year_section" placeholder="e.g. 3A" required>

  <button type="submit">Create Account</button>
</form>


    <div class="alt-link">
      Already have an account? <a href="login.php">Login</a>
    </div>
  </div>
</body>
</html>
