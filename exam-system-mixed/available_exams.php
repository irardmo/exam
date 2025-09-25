<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_login();
require_role('student');

$student_id = $_SESSION['user']['id'];

// ✅ Fetch all available exams
$stmt = $conn->prepare("SELECT e.id, e.title, e.description, e.created_at FROM exams e ORDER BY e.created_at DESC");
$stmt->execute();
$res = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Available Exams</title>
    <style>
        table { width: 80%; margin: 20px auto; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background: #f4f4f4; }
        .btn { padding: 8px 12px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; }
        .btn.disabled { background: #ccc; pointer-events: none; }
    </style>
</head>
<body>
    <h2 style="text-align:center;">Available Exams</h2>
    <table>
        <tr>
            <th>Title</th>
            <th>Description</th>
            <th>Date</th>
            <th>Action</th>
        </tr>
        <?php while($row = $res->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['title']) ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                <td>
                    <?php
                    // Check if student already attempted
                    $stmt2 = $conn->prepare("SELECT id FROM attempts WHERE exam_id=? AND student_id=?");
                    $stmt2->bind_param('ii', $row['id'], $student_id);
                    $stmt2->execute();
                    $stmt2->store_result();
                    if ($stmt2->num_rows > 0) {
                        echo '<a class="btn disabled">Completed</a>';
                    } else {
                        echo '<a class="btn" href="take_exam.php?exam_id='.$row['id'].'">Take Exam</a>';
                    }
                    ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
