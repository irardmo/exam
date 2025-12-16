<?php
// admin.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_login(); 
require_role('admin');

// FIX 1: Implement PRG pattern by pulling message from session and clearing it.
$msg = $_SESSION['admin_msg'] ?? '';
unset($_SESSION['admin_msg']);

// ----------------------------------------------------------------------
// 1. Handle Create User (POST) - Restricting to Teacher/Admin Roles for simplicity
// ----------------------------------------------------------------------
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form']??'')==='create_user'){
    $name=trim($_POST['name']??''); // Still collected, but not inserted into users table
    $username=trim($_POST['username']??''); 
    $password=$_POST['password']??''; 
    $role=$_POST['role']??'student';
    
    // FIX 3: Restrict role to Teacher or Admin for this simplified form
    if ($role === 'student') $role = 'teacher'; 

    if($name && $username && $password && in_array($role,['admin','teacher'])){
        $hash=password_hash($password,PASSWORD_BCRYPT);
        
        // FIX 1: UPDATED INSERT QUERY (Removed 'name' column)
        $stmt=$conn->prepare("INSERT INTO users (username,password_hash,role) VALUES (?,?,?)");
        
        if ($stmt) {
             // 'sss' for username, password_hash, role
             $stmt->bind_param('sss',$username,$hash,$role); 
             if ($stmt->execute()) {
                 $_SESSION['admin_msg'] = "User created successfully with role: " . $role;
             } else {
                 $_SESSION['admin_msg'] = "Error creating user: " . $stmt->error;
             }
             $stmt->close(); 
        } else {
             $_SESSION['admin_msg'] = "Database error: " . $conn->error;
        }
    } else { $_SESSION['admin_msg']="Please fill all mandatory fields (Name, Username, Password)."; }
    redirect('admin.php'); 
}

// ----------------------------------------------------------------------
// 2. Handle Create Exam (POST) - SECURE
// ----------------------------------------------------------------------
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form']??'')==='create_exam'){
    $title=trim($_POST['title']??''); 
    $description=trim($_POST['description']??''); 
    $is_active=isset($_POST['is_active'])?1:0;
    
    if($title){
        $uid=$_SESSION['user']['id'];
        $stmt=$conn->prepare("INSERT INTO exams (title,description,is_active,created_by) VALUES (?,?,?,?)");
        
        if ($stmt) {
             $stmt->bind_param('ssii',$title,$description,$is_active,$uid); 
             $stmt->execute(); 
             $stmt->close(); 
             $_SESSION['admin_msg'] = "Exam created.";
        } else {
             $_SESSION['admin_msg'] = "Database error: " . $conn->error;
        }
    } else { $_SESSION['admin_msg']="Title is required."; }
    redirect('admin.php'); 
}

// ----------------------------------------------------------------------
// 3. Handle Toggle Exam (GET) - SECURE
// ----------------------------------------------------------------------
if(isset($_GET['toggle_exam'])){ 
    $id=(int)$_GET['toggle_exam']; 
    $stmt = $conn->prepare("UPDATE exams SET is_active=1-is_active WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['admin_msg'] = "Exam status toggled.";
    redirect('admin.php'); 
}

// ----------------------------------------------------------------------
// 4. Data Retrieval for Display (FIX 2: Using Prepared Statements)
// ----------------------------------------------------------------------

// a) Fetch Users
// NOTE: Since the `users` table no longer has `name`, we join to `students` to get the full name for students, 
// but we only expect teachers and admins here, so we display the username or a hardcoded label.
$stmt_users = $conn->prepare("
    SELECT u.id, u.username, u.role, u.created_at, 
           CONCAT(s.first_name, ' ', s.last_name) as student_name
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id
    ORDER BY u.id DESC
");
$stmt_users->execute();
$users = $stmt_users->get_result();
$stmt_users->close();

// b) Fetch Exams
$stmt_exams = $conn->prepare("
    SELECT e.*, u.username as owner_username
    FROM exams e 
    LEFT JOIN users u ON u.id=e.created_by 
    ORDER BY e.id DESC
");
$stmt_exams->execute();
$exams = $stmt_exams->get_result();
$stmt_exams->close();

?>
<!DOCTYPE html><html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="images/logo.jpg" type="image/x-icon" />
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="flex" style="justify-content:space-between"><h1>Admin Dashboard</h1>
            <div class="flex">
                <span class="badge"><?= h($_SESSION['user']['username']) ?> (admin)</span><a class="badge" href="logout.php">Logout</a>
            </div>
        </div>
<?php if(!empty($msg)): ?><p class="alert alert-info"><?= h($msg) ?></p><?php endif; ?>
        <hr><h2>Create User (Teacher/Admin Only)</h2>
        <form method="post" class="flex" style="align-items:flex-end">
            <input type="hidden" name="form" value="create_user">
            <div style="flex:1">
                <label>Name (For Display)</label>
                <input name="name" required>
            </div>
            <div style="flex:1">
                <label>Username</label>
                <input name="username" required>
            </div>
            <div style="flex:1">
                <label>Password</label>
                <input type="text" name="password" required>
            </div>
            <div><label>Role</label><select name="role">
                <option value="teacher" selected>Teacher</option>
                <option value="admin">Admin</option>
            </select></div>
            <button type="submit">Add User</button></form>

            <h3>Users List</h3><table class="table"><tr><th>ID</th><th>Display Name</th><th>Username</th><th>Role</th><th>Created</th></tr>
            <?php while($u=$users->fetch_assoc()): ?><tr>
            <td><?= $u['id'] ?></td>
            <td><?= $u['student_name'] ? h($u['student_name']) : h($u['username']) ?></td> 
            <td><?= h($u['username']) ?></td>
            <td><span class="badge badge-<?= h($u['role']) ?>"><?= h($u['role']) ?></span></td>
            <td><small class="muted"><?= h($u['created_at']) ?></small></td></tr><?php endwhile; ?></table>

            <hr><h2>Create Exam</h2>
            <form method="post" class="flex" style="align-items:flex-end">
            <input type="hidden" name="form" value="create_exam">
            <div style="flex:2"><label>Title</label><input name="title" required></div>
            <div style="flex:3"><label>Description</label><input name="description"></div>
            <div><label>Active?</label><input type="checkbox" name="is_active"></div>
            <button type="submit">Create Exam</button></form>

            <h3>Exams List</h3><table class="table"><tr><th>ID</th><th>Title</th><th>Owner</th><th>Active</th><th>Created</th><th>Action</th></tr>
            <?php while($e=$exams->fetch_assoc()): ?><tr>
            <td><?= $e['id'] ?></td><td><?= h($e['title']) ?></td><td><?= h($e['owner_username']) ?></td>
            <td><?= $e['is_active']?'<span class="badge badge-success">Yes</span>':'<span class="badge badge-danger">No</span>' ?></td>
            <td><small class="muted"><?= h($e['created_at']) ?></small></td>
            <td><a class="badge" href="?toggle_exam=<?= $e['id'] ?>">Toggle Active</a></td></tr><?php endwhile; ?></table>
</div></div></body></html>