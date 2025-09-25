<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/similarity.php';

require_login();
require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('student_dashboard.php');
    exit();
}

$student_id = (int)($_SESSION['user']['id'] ?? 0);
$exam_id    = (int)($_POST['exam_id'] ?? 0);
$answers    = $_POST['answers'] ?? [];

if ($student_id <= 0 || $exam_id <= 0) {
    redirect('student_dashboard.php');
    exit();
}

$total_score = 0;
$max_score   = 0;
$raw_score   = 0;
$needs_manual = 0;
$answered_qids = [];

/*
 * 1) Create attempt first (with zero scores)
 */
// ✅ Use the attempt created in take_exam
$attempt_id = $_SESSION['current_attempt_id'][$exam_id] ?? 0;
if (!$attempt_id) {
    die("No active attempt found. Please restart the exam.");
}


// ✅ Only grade the questions shown in take_exam
$question_ids = $_SESSION['exam_questions'][$exam_id] ?? [];
if (empty($question_ids)) {
    die("No question set found for this exam attempt.");
}

// Fetch only those question rows
$in = implode(',', array_fill(0, count($question_ids), '?'));
$types = str_repeat('i', count($question_ids));
$sql = "SELECT id, type, correct_option, answer_text FROM questions WHERE id IN ($in)";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$question_ids);
$stmt->execute();
$res = $stmt->get_result();


while ($q = $res->fetch_assoc()) {
    $qid = (int)$q['id'];
    $qtype = $q['type'] ?? 'mcq';
    $correct_option = $q['correct_option'] ?? '';
    $correct_text   = $q['answer_text'] ?? '';
    $points = 1;

    $max_score += $points;

    $student_answer = trim($answers[$qid] ?? '');
    $answered_qids[] = $qid;

    $score = 0;
    $is_correct = 0;

    if ($qtype === 'mcq') {
        if ($student_answer !== '' && strcasecmp($student_answer, $correct_option) === 0) {
            $score = $points;
            $is_correct = 1;
        }
    } else {
        if ($student_answer !== '' && $correct_text !== '') {
            $sa = strtolower(trim(preg_replace('/\s+/', ' ', $student_answer)));
            $ca = strtolower(trim(preg_replace('/\s+/', ' ', $correct_text)));

            if ($sa === $ca) {
                $score = $points;
                $is_correct = 1;
            } elseif (function_exists('is_synonym') && is_synonym($sa, $ca)) {
                $score = $points;
                $is_correct = 1;
            } elseif (function_exists('string_similarity') && string_similarity($sa, $ca) >= 0.8) {
                $score = $points;
                $is_correct = 1;
            } else {
                $needs_manual = 1;
            }
        } else {
            $needs_manual = 1;
        }
    }

    $raw_score   += $score;
    $total_score += $score;

    /*
     * ✅ Save each answer
     */
    $stmtAns = $conn->prepare("
        INSERT INTO attempt_answers (attempt_id, exam_id, student_id, question_id, answer_text, is_correct)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmtAns->bind_param('iiiisi', $attempt_id, $exam_id, $student_id, $qid, $student_answer, $is_correct);
    $stmtAns->execute();
    $stmtAns->close();
}
$stmt->close();

/*
 * 3) Update attempt with final score
 */
$percentage = ($max_score > 0) ? ($total_score / $max_score) * 100 : 0;
$transmuted = transmute($raw_score); // use helper mapping

$selected_json = json_encode($answered_qids);

$stmtU = $conn->prepare("
    UPDATE attempts
    SET selected_question_ids=?, raw_score=?, transmuted=?, needs_manual_grading=?,
        score=?, max_score=?, percentage=?, submitted_at=NOW()
    WHERE id=?
");
$stmtU->bind_param(
    'sdiididi',
    $selected_json,
    $raw_score,
    $transmuted,
    $needs_manual,
    $total_score,
    $max_score,
    $percentage,
    $attempt_id
);
$stmtU->execute();
$stmtU->close();

/*
 * 4) Redirect to results page
 */
unset($_SESSION['exam_questions'][$exam_id]);
redirect("result.php?attempt_id=" . $attempt_id);
exit();
