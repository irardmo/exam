<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

require_login();
require_role('student');

$student_id = $_SESSION['user']['id'];
$exam_id = (int)($_GET['exam_id'] ?? 0);

// 🚫 Block retake: check if student already attempted this exam
$stmtCheck = $conn->prepare("SELECT id FROM attempts WHERE exam_id = ? AND student_id = ? LIMIT 1");
if (!$stmtCheck) {
    die("Query error (attempts check): " . $conn->error);
}
$stmtCheck->bind_param("ii", $exam_id, $student_id);
$stmtCheck->execute();
$resCheck = $stmtCheck->get_result();
if ($resCheck && $resCheck->num_rows > 0) {
    header("Location: result.php?exam_id=" . $exam_id);
    exit;
}

// ✅ Fetch exam
$stmt = $conn->prepare("SELECT * FROM exams WHERE id = ?");
if (!$stmt) {
    die("Query error (exam fetch): " . $conn->error);
}
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();

if (!$exam) {
    die("Exam not found for ID: " . $exam_id);
}

// ✅ Fetch 50 random questions
$qstmt = $conn->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY RAND() LIMIT 50");
if (!$qstmt) {
    die("Query error (questions fetch): " . $conn->error);
}
$qstmt->bind_param("i", $exam_id);
$qstmt->execute();
$questions = $qstmt->get_result();

// ✅ Save these question IDs into session so submit_exam.php knows what to grade
// ✅ Save these question IDs into session so submit_exam.php knows what to grade
$question_ids = [];
$fetched_questions = [];
while ($q = $questions->fetch_assoc()) {
    $question_ids[] = $q['id'];
    $fetched_questions[] = $q; // keep for rendering
}
$_SESSION['exam_questions'][$exam_id] = $question_ids;

// 🔥 FIX: also insert attempt now (if not exists yet)
$selected_json = json_encode($question_ids);

$stmtAttempt = $conn->prepare("
    INSERT INTO attempts (exam_id, student_id, selected_question_ids, started_at)
    VALUES (?, ?, ?, NOW())
");
$stmtAttempt->bind_param("iis", $exam_id, $student_id, $selected_json);
$stmtAttempt->execute();
$_SESSION['current_attempt_id'][$exam_id] = $stmtAttempt->insert_id;
$stmtAttempt->close();

$_SESSION['exam_questions'][$exam_id] = $question_ids;
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($exam['title']); ?></title>
    
  <link rel="stylesheet" href="style-all.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { margin-bottom: 10px; }
        .question { margin-bottom: 20px; padding: 15px; border: 1px solid #ccc; border-radius: 8px; }
        button { padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 5px; }
        button:hover { background: #45a049; cursor: pointer; }
    </style>
</head>
<body>
    <h2><?php echo htmlspecialchars($exam['title']); ?></h2>
    <p><?php echo htmlspecialchars($exam['description']); ?></p>

    <form action="submit_exam.php" method="POST">
        <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">

        <?php 
        $qnum = 1;
        foreach ($fetched_questions as $q): ?>
            <div class="question">
                <p><strong><?php echo $qnum++ . ". " . htmlspecialchars($q['question']); ?></strong></p>

                <?php if ($q['type'] === 'mcq'): ?>
                    <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="A"> <?php echo htmlspecialchars($q['option_a']); ?></label><br>
                    <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="B"> <?php echo htmlspecialchars($q['option_b']); ?></label><br>
                    <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="C"> <?php echo htmlspecialchars($q['option_c']); ?></label><br>
                    <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="D"> <?php echo htmlspecialchars($q['option_d']); ?></label><br>
                <?php elseif ($q['type'] === 'text'): ?>
                    <textarea name="answers[<?php echo $q['id']; ?>]" rows="3" cols="50"></textarea>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <button type="submit">Submit Exam</button>
    </form>
    <script>
document.addEventListener("keydown", function (event) {
    // Block F5 (116) and Ctrl+R
    if (event.keyCode === 116 || (event.ctrlKey && event.key.toLowerCase() === "r")) {
        event.preventDefault();
        alert("Page refresh is disabled during the exam.");
    }
});
</script>
<script>
document.addEventListener("contextmenu", function (event) {
    event.preventDefault();
    alert("Right click is disabled.");
});
</script>
<script>
// ⚠️ Warn if the student tries to refresh, close, or navigate away
window.onbeforeunload = function() {
    return "Are you sure you want to refresh or leave? Your exam progress may be lost.";
};
</script>

</body>
</html>
