<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_login();
require_role('student');

$exam_id = (int)($_GET['exam_id'] ?? 0);
if (!$exam_id) redirect('student.php');

// ✅ Check if student already has an attempt
$student_id = $_SESSION['user']['id'];
$check = $conn->query("SELECT * FROM attempts WHERE exam_id=$exam_id AND student_id=$student_id AND submitted_at IS NULL");
if ($check->num_rows > 0) {
    $attempt = $check->fetch_assoc();
    $attempt_id = $attempt['id'];
    $question_ids = json_decode($attempt['selected_question_ids'], true);
} else {
    // ✅ Create a new attempt with random 50 questions
    $qres = $conn->query("SELECT id FROM questions WHERE exam_id=$exam_id ORDER BY RAND() LIMIT 50");
    $question_ids = [];
    while ($row = $qres->fetch_assoc()) $question_ids[] = (int)$row['id'];

    $stmt = $conn->prepare("INSERT INTO attempts (exam_id, student_id, selected_question_ids, created_at) VALUES (?,?,?,NOW())");
    $json_ids = json_encode($question_ids);
    $stmt->bind_param('iis', $exam_id, $student_id, $json_ids);
    $stmt->execute();
    $attempt_id = $conn->insert_id;
}

// ✅ Fetch questions
$idlist = implode(',', array_map('intval', $question_ids));
$qres = $conn->query("SELECT * FROM questions WHERE id IN ($idlist)");
$questions = [];
while ($row = $qres->fetch_assoc()) $questions[$row['id']] = $row;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Start Exam</title>
    <style>
        <link rel="stylesheet" href="style-all.css">
        body { font-family: Arial; padding: 20px; }
        .question { margin-bottom: 20px; }
    </style>
</head>
<body>
<h2>Exam</h2>
<form method="POST" action="exam_submit.php">
    <input type="hidden" name="attempt_id" value="<?= $attempt_id ?>">
    <?php foreach ($question_ids as $index => $qid): ?>
        <?php $q = $questions[$qid]; ?>
        <div class="question">
            <p><strong>Q<?= $index + 1 ?>:</strong> <?= htmlspecialchars($q['question_text']) ?></p>
            <?php if ($q['type'] === 'mcq'): ?>
                <?php
                $options = json_decode($q['options'], true);
                foreach ($options as $opt): ?>
                    <label><input type="radio" name="answers[<?= $qid ?>]" value="<?= htmlspecialchars($opt) ?>"> <?= htmlspecialchars($opt) ?></label><br>
                <?php endforeach; ?>
            <?php else: ?>
                <input type="text" name="answers[<?= $qid ?>]" placeholder="Your answer">
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <button type="submit">Submit Exam</button>
</form>
</body>
</html>
