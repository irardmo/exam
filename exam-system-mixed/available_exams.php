<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_login();
require_role('student');

$student_id = $_SESSION['user']['id'];

// FIX 1: Filter to only show exams that are marked as active (is_active = 1)
$stmt = $conn->prepare("SELECT e.id, e.title, e.description, e.created_at FROM exams e WHERE e.is_active = 1 ORDER BY e.created_at DESC");
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
        .btn.resume { background: #ffc107; }
    </style>
</head>
<body>
    <?php include 'header.php'; // Assuming you use header/footer includes for navigation ?>
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
                <td><?= h($row['title']) ?></td>
                <td><?= h($row['description']) ?></td>
                <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                <td>
                    <?php
                    // FIX 3: Check for completed attempts (submitted_at IS NOT NULL) and unsubmitted attempts (submitted_at IS NULL)

                    // 1. Check for a COMPLETED attempt
                    $stmt_completed = $conn->prepare("SELECT id FROM attempts WHERE exam_id=? AND student_id=? AND submitted_at IS NOT NULL LIMIT 1");
                    $stmt_completed->bind_param('ii', $row['id'], $student_id);
                    $stmt_completed->execute();
                    $completed_id = $stmt_completed->get_result()->fetch_assoc()['id'] ?? null;
                    $stmt_completed->close(); // FIX 2: Close statement

                    if ($completed_id) {
                        echo '<a class="btn disabled" href="result.php?attempt_id='.$completed_id.'">Completed</a>';
                    } else {
                        // 2. Check for an ACTIVE/UNSUBMITTED attempt
                        $stmt_active = $conn->prepare("SELECT id FROM attempts WHERE exam_id=? AND student_id=? AND submitted_at IS NULL LIMIT 1");
                        $stmt_active->bind_param('ii', $row['id'], $student_id);
                        $stmt_active->execute();
                        $active_id = $stmt_active->get_result()->fetch_assoc()['id'] ?? null;
                        $stmt_active->close(); // FIX 2: Close statement
                        
                        if ($active_id) {
                             // If an active attempt exists, allow the student to resume it
                             echo '<a class="btn resume" href="take_exam.php?exam_id='.$row['id'].'">Resume Exam</a>';
                        } else {
                             // Otherwise, allow the student to start a new exam
                             echo '<a class="btn" href="take_exam.php?exam_id='.$row['id'].'">Take Exam</a>';
                        }
                    }
                    ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
    <?php include 'footer.php'; // Assuming you use header/footer includes ?>
</body>
</html>