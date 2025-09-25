<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/similarity.php';
require_login();
require_role('student');

$attempt_id = (int)($_POST['attempt_id'] ?? 0);
if (!$attempt_id) redirect('student.php');

// ✅ Fetch attempt
$attempt = $conn->query("SELECT * FROM attempts WHERE id=$attempt_id AND student_id=".$_SESSION['user']['id'])->fetch_assoc();
if (!$attempt) die("Invalid attempt.");

$exam_id = (int)$attempt['exam_id'];
$answers = $_POST['answers'] ?? [];

$total_score = 0;
$max_score = 0;

// ✅ Get questions for this attempt
$ids = json_decode($attempt['selected_question_ids'], true);
$idlist = implode(',', array_map('intval', $ids));

$qres = $conn->query("SELECT * FROM questions WHERE id IN ($idlist)");
$questions = [];
while ($row = $qres->fetch_assoc()) $questions[$row['id']] = $row;

// ✅ Evaluate answers
foreach ($questions as $qid => $q) {
    $student_answer = trim($answers[$qid] ?? '');
    $points = max(1, (int)$q['points']);
    $max_score += $points;

    $score = 0;
    if ($q['type'] === 'mcq') {
        if (strcasecmp($student_answer, trim($q['correct_answer'])) === 0) {
            $score = $points;
        }
    } else {
        $sa = strtolower(preg_replace('/\s+/', ' ', $student_answer));
        $ca = strtolower(preg_replace('/\s+/', ' ', trim($q['answer_text'])));
        if ($sa === $ca || is_synonym($sa, $ca) || string_similarity($sa, $ca) >= 0.8) {
            $score = $points;
        }
    }

    $total_score += $score;

    // ✅ Save answer
    $stmt2 = $conn->prepare("INSERT INTO attempt_answers (attempt_id, question_id, answer_text, is_correct) VALUES (?,?,?,?)");
    $is_correct = ($score > 0) ? 1 : 0;
    $stmt2->bind_param('iisi', $attempt_id, $qid, $student_answer, $is_correct);
    $stmt2->execute();
}

// ✅ Calculate percentages
$percentage = ($max_score > 0) ? ($total_score / $max_score) * 100 : 0;
$transmuted = 60 + (($percentage / 100) * 40); // Example formula

// ✅ Update attempt as submitted
$stmt3 = $conn->prepare("UPDATE attempts SET score=?, max_score=?, percentage=?, transmuted=?, submitted_at=NOW() WHERE id=?");
$stmt3->bind_param('iiddi', $total_score, $max_score, $percentage, $transmuted, $attempt_id);
$stmt3->execute();

redirect("result.php?attempt_id=$attempt_id");
?>
