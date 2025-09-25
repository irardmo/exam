<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

require_login();
require_role('student');

$attempt_id = (int)($_GET['attempt_id'] ?? 0);
$student_id = $_SESSION['user']['id'];

if ($attempt_id <= 0) {
    redirect('student_dashboard.php');
    exit;
}

/* 1) Fetch attempt with exam */
$stmt = $conn->prepare("
    SELECT a.*, e.title, e.description
    FROM attempts a
    JOIN exams e ON a.exam_id = e.id
    WHERE a.id = ? AND a.student_id = ?
    LIMIT 1
");
$stmt->bind_param('ii', $attempt_id, $student_id);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$attempt) {
    redirect('student_dashboard.php');
    exit;
}

/* 2) Fetch ONLY answers linked to this attempt */
$stmt2 = $conn->prepare("
    SELECT q.question, q.option_a, q.option_b, q.option_c, q.option_d, 
           q.correct_option, q.type,
           aa.answer_text, aa.is_correct
    FROM attempt_answers aa
    JOIN questions q ON aa.question_id = q.id
    WHERE aa.attempt_id = ?
    ORDER BY aa.id ASC
");
$stmt2->bind_param('i', $attempt_id);
$stmt2->execute();
$answersRes = $stmt2->get_result();
$stmt2->close();

/* helper: turn letter (A/B/C/D) into full option text */
function option_text_for_letter($row, $letter) {
    $letter = strtoupper(trim($letter));
    $map = ['A'=>'option_a','B'=>'option_b','C'=>'option_c','D'=>'option_d'];
    return isset($map[$letter]) ? ($row[$map[$letter]] ?? $letter) : $letter;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Exam Result</title>
<link rel="stylesheet" href="style-all.css">
<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .summary { margin-bottom: 18px; color:e5e7eb;  }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { padding: 10px; border: 1px solid #ddd; vertical-align: top; }
    th { background:#f6f6f6; text-align:left; color: #000000;}
    .correct { color:green; font-weight:bold; }
    .wrong { color:red; font-weight:bold; }
    .btn { display:inline-block; padding:8px 12px; background:#0d6efd; color:#fff; text-decoration:none; border-radius:4px; }
    pre.small { white-space:pre-wrap; font-size:0.95em; color:#e5e7eb; margin:0; }
</style>

  <link rel="stylesheet" href="style-all.css">
</head>
<body>

    <h2>Exam Result: <?= htmlspecialchars($attempt['title']) ?></h2>
    <?php if (!empty($attempt['description'])): ?>
        <p><?= nl2br(htmlspecialchars($attempt['description'])) ?></p>
    <?php endif; ?>

    <div class="summary">
        <strong>Score:</strong> <?= (int)$attempt['score'] ?>/<?= (int)$attempt['max_score'] ?><br>
        <strong>Transmuted Grade:</strong> <?= number_format((float)$attempt['transmuted'], 2) ?><br>
        <strong>Date Taken:</strong> 
<?php
$exam_date = $attempt['submitted_at'] ?? null;
if ($exam_date) {
    $exam_date = new DateTime($exam_date);
    echo $exam_date->format('F j, Y H:i'); // e.g., September 11, 2025 08:00
} else {
    echo 'N/A';
}
?><br>
    </div>

    <h3>Question Breakdown (only your 50 questions)</h3>
    <table>
        <thead>
            <tr>
                <th style="width:48%">Question</th>
                <th style="width:18%">Your Answer</th>
                <th style="width:18%">Correct Answer</th>
                <th style="width:16%">Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($answersRes && $answersRes->num_rows): ?>
            <?php while ($r = $answersRes->fetch_assoc()): ?>
                <?php
                    $student_answer = $r['answer_text'] ?? '';
                    $correct_letter = $r['correct_option'] ?? '';
                    $qtype = strtolower($r['type'] ?? 'mcq');

                    if ($qtype === 'mcq') {
                        $student_display = (strlen(trim($student_answer)) === 1)
                            ? option_text_for_letter($r, $student_answer)
                            : $student_answer;
                        $correct_display = option_text_for_letter($r, $correct_letter);
                    } else {
                        $student_display = $student_answer;
                        $correct_display = $correct_letter;
                    }

                    $is_correct = (isset($r['is_correct']) && $r['is_correct'] == 1);
                ?>
                <tr>
                    <td><pre class="small"><?= htmlspecialchars($r['question']) ?></pre></td>
                    <td><?= htmlspecialchars($student_display) ?></td>
                    <td><?= htmlspecialchars($correct_display) ?></td>
                    <td><?= $is_correct ? '<span class="correct">Correct</span>' : '<span class="wrong">Wrong</span>' ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4">No answers found for this attempt.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <p style="margin-top:18px;"><a href="student_dashboard.php" class="btn">Back to Dashboard</a></p>
</body>
</html>
