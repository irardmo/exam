<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_login(); require_role('admin');

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form']??'')==='create_user'){
  $name=trim($_POST['name']??''); $username=trim($_POST['username']??''); $password=$_POST['password']??''; $role=$_POST['role']??'student';
  if($name && $username && $password && in_array($role,['admin','teacher','student'])){
    $hash=password_hash($password,PASSWORD_BCRYPT);
    $stmt=$conn->prepare("INSERT INTO users (name,username,password_hash,role) VALUES (?,?,?,?)");
    $stmt->bind_param('ssss',$name,$username,$hash,$role); $stmt->execute(); $msg="User created.";
  } else { $msg="Please fill all fields."; }
}

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form']??'')==='create_exam'){
  $title=trim($_POST['title']??''); $description=trim($_POST['description']??''); $is_active=isset($_POST['is_active'])?1:0;
  if($title){
    $uid=$_SESSION['user']['id'];
    $stmt=$conn->prepare("INSERT INTO exams (title,description,is_active,created_by) VALUES (?,?,?,?)");
    $stmt->bind_param('ssii',$title,$description,$is_active,$uid); $stmt->execute(); $msg="Exam created.";
  } else { $msg="Title is required."; }
}

if(isset($_GET['toggle_exam'])){ $id=(int)$_GET['toggle_exam']; $conn->query("UPDATE exams SET is_active=1-is_active WHERE id=$id"); redirect('admin.php'); }

$users=$conn->query("SELECT id,name,username,role,created_at FROM users ORDER BY id DESC");
$exams=$conn->query("SELECT e.*, u.name as owner FROM exams e LEFT JOIN users u ON u.id=e.created_by ORDER BY e.id DESC");
?>
<!DOCTYPE html><html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin</title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" href="images/logo.jpg"
        type="image/x-icon" />
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="flex" style="justify-content:space-between"><h1>Admin Dashboard</h1>
      <div class="flex">
        <span class="badge"><?= h($_SESSION['user']['name']) ?> (admin)</span><a class="badge" href="logout.php">Logout</a>
      </div>
    </div>
<?php if(!empty($msg)): ?><p style="color:#93c5fd"><?= h($msg) ?></p><?php endif; ?>
    <hr><h2>Create User</h2>
    <form method="post" class="flex" style="align-items:flex-end">
        <input type="hidden" name="form" value="create_user">
        <div style="flex:1">
          <label>Name</label>
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
      <div><label>Role</label><select name="role"><option value="teacher">Teacher</option><option value="student" selected>Student</option><option value="admin">Admin</option></select></div>
      <button type="submit">Add</button></form>

      <h3>Users</h3><table class="table"><tr><th>ID</th><th>Name</th><th>Username</th><th>Role</th><th>Created</th></tr>
      <?php while($u=$users->fetch_assoc()): ?><tr><td><?= $u['id'] ?></td><td><?= h($u['name']) ?></td><td><?= h($u['username']) ?></td><td><span class="badge"><?= h($u['role']) ?></span></td><td><small class="muted"><?= h($u['created_at']) ?></small></td></tr><?php endwhile; ?></table>

      <hr><h2>Create Exam</h2>
    <form method="post" class="flex" style="align-items:flex-end">
<input type="hidden" name="form" value="create_exam">
<div style="flex:2"><label>Title</label><input name="title" required></div>
<div style="flex:3"><label>Description</label><input name="description"></div>
<div><label>Active?</label><input type="checkbox" name="is_active"></div>
<button type="submit">Create</button></form>

<h3>Exams</h3><table class="table"><tr><th>ID</th><th>Title</th><th>Owner</th><th>Active</th><th>Created</th><th>Action</th></tr>
<?php while($e=$exams->fetch_assoc()): ?><tr>
<td><?= $e['id'] ?></td><td><?= h($e['title']) ?></td><td><?= h($e['owner']) ?></td>
<td><?= $e['is_active']?'<span class="badge">Yes</span>':'<span class="badge">No</span>' ?></td>
<td><small class="muted"><?= h($e['created_at']) ?></small></td>
<td><a class="badge" href="?toggle_exam=<?= $e['id'] ?>">Toggle Active</a></td></tr><?php endwhile; ?></table>
</div></div></body></html>
