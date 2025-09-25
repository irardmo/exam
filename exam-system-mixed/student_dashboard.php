<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

require_login();
require_role('student');

// Logged in student
$student_id = $_SESSION['user']['id'];

// Fetch exams with latest attempt info per exam
$query = "
    SELECT e.id AS exam_id, e.title,
           a.id AS attempt_id,
           a.score,
           a.max_score,
           a.percentage,
           a.submitted_at AS last_submission
    FROM exams e
    LEFT JOIN attempts a 
      ON e.id = a.exam_id 
     AND a.student_id = ?
     AND a.id = (
         SELECT id FROM attempts 
         WHERE exam_id = e.id AND student_id = ? 
         ORDER BY submitted_at DESC LIMIT 1
     )
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param('ii', $student_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="style-all.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; background: #0f172a; color:e5e7eb;}
        .container { max-width: 900px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; color: #000;}
        h1 { text-align: center; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: center; border-bottom: 1px solid #ddd; }
        th { background-color: #4CAF50; color: white; }
        tr:hover { background-color: #f1f1f1; }
        .btn { background: #4CAF50; color: #fff; padding: 8px 12px; text-decoration: none; border-radius: 5px; }
        .btn:hover { background: #45a049; }
        .progress-container { background: #ddd; border-radius: 20px; width: 100%; }
        .progress-bar { height: 12px; border-radius: 20px; background: #4CAF50; width: 0%; }
        .no-exam { text-align: center; margin-top: 20px; color: #777; }

    </style>
</head>
<body>
<div class="container">
    <div style="text-align:right; margin-bottom:15px;">
    <a href="logout.php" class="btn" style="background:#dc3545;">Logout</a>
</div>

    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user']['name']); ?></h1>
    <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student_id); ?></p>

    <?php if ($result->num_rows > 0): ?>
        <table>
            <tr>
                <th>Exam Title</th>
                <th>Total Questions</th>
                <th>Score</th>
                
                <th>Last Submission</th>
                <th>Action</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                $score = $row['score'] ?? 0;
                $total = $row['max_score'] ?? 0;
                $percentage = $row['percentage'] ?? 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                    <td><?php echo $total > 0 ? $total : 'N/A'; ?></td>
                    <td><?php echo $score; ?></td>
                   
                    <td><?php echo $row['last_submission'] ? $row['last_submission'] : 'N/A'; ?></td>
                    <td>
                        <a href="take_exam.php?exam_id=<?php echo $row['exam_id']; ?>" class="btn">Take Exam</a>
                        <?php if ($row['attempt_id']): ?>
                            <a href="result.php?attempt_id=<?php echo $row['attempt_id']; ?>" class="btn">View Result</a>
                        <?php else: ?>
                            <span style="color:#999;">No Attempt</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p class="no-exam">No exams available yet.</p>
    <?php endif; ?>
</div>
</body>
</html>
