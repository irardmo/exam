<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
ensure_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';

    if ($u === '' || $p === '') {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, name, username, password_hash, role FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $u);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();

        if ($user && password_verify($p, $user['password_hash'])) {
            // ✅ Store user info in session
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'username' => $user['username'],
                'role' => $user['role']
            ];

            // ✅ Redirect based on role
            if ($user['role'] === 'admin') {
                redirect('admin.php');
            } elseif ($user['role'] === 'teacher') {
                redirect('teacher_dashboard.php');
            } elseif ($user['role'] === 'student') {
                redirect('student_dashboard.php');
            } else {
                $error = "Unknown user role.";
            }
        } else {
            $error = "Invalid credentials.";
        }
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    redirect('login.php');
}
?>
