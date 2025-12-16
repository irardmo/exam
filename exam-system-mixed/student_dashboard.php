    <?php
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/helpers.php';
    include 'header.php';

    require_login();
    require_role('student');

    // Logged in student
    $student_id = $_SESSION['user']['id'];

    // Fetch exams with latest attempt info per exam
    $query = "
        SELECT e.id AS exam_id, e.title,
               a.id AS attempt_id,
               a.raw_score AS score,   /* FIX: Aliased raw_score as score */
               a.max_score,            /* FIX: Requires adding column to DB */
               a.percentage,           /* FIX: Requires adding column to DB */
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
        // This check is now robust and will only fail if the table or columns are truly missing
        die("Prepare failed: " . $conn->error); 
    }

    $stmt->bind_param('ii', $student_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    ?>

    <div class="card">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user']['name']); ?></h2>

        <h3>Available Exams &amp; Results</h3>

        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Exam Title</th>
                        <th>Total Questions</th>
                        <th>Score</th>
                        <th>Last Submission</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                        // PHP now correctly receives the aliased 'score'
                        $score = $row['score'] ?? 0; 
                        $total = $row['max_score'] ?? 0;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo $total > 0 ? $total : 'N/A'; ?></td>
                            <td><?php echo $score; ?> / <?php echo $total; ?></td>
                            <td><?php echo $row['last_submission'] ? $row['last_submission'] : 'N/A'; ?></td>
                            <td>
                                <a href="take_exam.php?exam_id=<?php echo $row['exam_id']; ?>" class="btn">Take Exam</a>
                                <?php if ($row['attempt_id']): ?>
                                    <a href="result.php?attempt_id=<?php echo $row['attempt_id']; ?>" class="btn">View Result</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No exams available yet.</p>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>