<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
// FIX: Include header after session is handled (it's fine here, but good practice)
include 'header.php'; 

require_login();
require_role('student');

$student_id = $_SESSION['user']['id'];
$exam_id = (int)($_GET['exam_id'] ?? 0);

// 🚫 Block retake: check if student already attempted this exam
$stmtCheck = $conn->prepare("SELECT id FROM attempts WHERE exam_id = ? AND student_id = ? AND submitted_at IS NOT NULL LIMIT 1");
if (!$stmtCheck) {
    die("Query error (attempts check): " . $conn->error);
}
$stmtCheck->bind_param("ii", $exam_id, $student_id);
$stmtCheck->execute();
$resCheck = $stmtCheck->get_result();

// Check if a completed attempt exists. If so, redirect to the dashboard.
if ($resCheck && $resCheck->num_rows > 0) {
    header("Location: student_dashboard.php");
    exit;
}
$stmtCheck->close();

// ✅ Fetch exam
$stmt = $conn->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    die("Exam not found for ID: " . $exam_id);
}

// ✅ Fetch 50 random questions
$qstmt = $conn->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY RAND() LIMIT 50");
$qstmt->bind_param("i", $exam_id);
$qstmt->execute();
$questions = $qstmt->get_result();
$qstmt->close();

$question_ids = [];
$fetched_questions = [];
while ($q = $questions->fetch_assoc()) {
    $question_ids[] = $q['id'];
    $fetched_questions[] = $q; // keep for rendering
}

// 🚫 Validation: Check if questions were actually found
if (empty($fetched_questions) || count($fetched_questions) < 50) {
    // FIX: Redirect instead of dying for better UX
    $_SESSION['error'] = 'This exam has fewer than 50 questions. Ask your teacher to add more.';
    header("Location: student_dashboard.php");
    exit;
}

// Store attempt data
$selected_json = json_encode($question_ids);

$stmtAttempt = $conn->prepare("
    INSERT INTO attempts (exam_id, student_id, selected_question_ids, started_at)
    VALUES (?, ?, ?, NOW())
");
$stmtAttempt->bind_param("iis", $exam_id, $student_id, $selected_json);
$stmtAttempt->execute();

$new_attempt_id = $stmtAttempt->insert_id; // Capture the newly created ID
$stmtAttempt->close();
?>

<head>
    <link rel="stylesheet" href="redesign.css"> 
</head>

<div class="card">
    <h2><?php echo htmlspecialchars($exam['title']); ?></h2>
    <p><?php echo htmlspecialchars($exam['description']); ?></p>

    <form action="submit_exam.php" method="POST">
        <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
        <input type="hidden" name="attempt_id" value="<?php echo $new_attempt_id; ?>"> 

        <div class="question-grid">
            <?php
            $qnum = 1;
            foreach ($fetched_questions as $q): ?>
                <div class="q-cell">
                    <div class="form-group" id="exam">
                        <p><strong><?php echo $qnum++ . ". " . htmlspecialchars($q['question_text']); ?></strong></p> 

                        <?php if ($q['type'] === 'mcq'): ?>
                            <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="A"> <?php echo htmlspecialchars($q['option_a']); ?></label>
                            <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="B"> <?php echo htmlspecialchars($q['option_b']); ?></label>
                            <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="C"> <?php echo htmlspecialchars($q['option_c']); ?></label>
                            <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="D"> <?php echo htmlspecialchars($q['option_d']); ?></label>
                        <?php elseif ($q['type'] === 'fill'): ?>
                            <textarea name="answers[<?php echo $q['id']; ?>]" rows="3" placeholder="Type your answer here"></textarea>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="question-submit-container">
            <button type="submit" class="btn" onclick="return confirm('Are you sure you want to submit your exam now?')">Submit Exam</button>
        </div>
    </form>
</div>
<script>
// JavaScript security/anti-cheat measures
document.addEventListener("keydown", function (event) {
    if (event.keyCode === 116 || (event.ctrlKey && event.key.toLowerCase() === "r")) {
        event.preventDefault();
        alert("Page refresh is disabled during the exam.");
    }
});
document.addEventListener("contextmenu", function (event) {
    event.preventDefault();
    alert("Right click is disabled.");
});
window.onbeforeunload = function() {
    return "Are you sure you want to refresh or leave? Your exam progress may be lost.";
};
</script>

<?php include 'footer.php'; ?>