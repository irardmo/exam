<?php
// submit_exam.php (Final, Secure Version)

// FIX 2: Retrieve attempt_id from POST, which is the most reliable source
$attempt_id = (int)($_POST['attempt_id'] ?? 0); 

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/similarity.php'; // Required for non-MCQ grading

require_login();
require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $attempt_id <= 0) {
    redirect('student_dashboard.php');
    exit();
}

$student_id = (int)($_SESSION['user']['id'] ?? 0);
$answers    = $_POST['answers'] ?? [];

$raw_score    = 0;
$needs_manual = 0;

/*
 * 1) Verify Attempt and Retrieve Question IDs from DB (CRITICAL FIX)
 * We rely on the database record, not fragile session keys, for the attempt data.
 */
$stmtA = $conn->prepare("SELECT exam_id, selected_question_ids, max_score FROM attempts WHERE id=? AND student_id=? AND submitted_at IS NULL");

if (!$stmtA) {
    die("Database error during attempt lookup setup: " . $conn->error);
}

$stmtA->bind_param('ii', $attempt_id, $student_id);
$stmtA->execute();
$attempt_data = $stmtA->get_result()->fetch_assoc();
$stmtA->close();

if (!$attempt_data) {
    die("No active or unsubmitted attempt found for this submission.");
}

$exam_id = $attempt_data['exam_id'];
$question_ids_db = json_decode($attempt_data['selected_question_ids'], true) ?: []; 
// Max score is the total count of questions assigned to the attempt.
$max_score = count($question_ids_db); 

if (empty($question_ids_db)) {
    die("Attempt data missing question set.");
}

// Intersect: Only grade answers for questions that were actually assigned to this attempt.
$question_ids_to_grade = array_values(array_intersect($question_ids_db, array_keys($answers)));

/*
 * 2) Fetch Question Details for Grading (SECURE)
 */

if (!empty($question_ids_to_grade)) {
    // Dynamically build the WHERE IN clause for the prepared statement
    $in = implode(',', array_fill(0, count($question_ids_to_grade), '?'));
    $types = str_repeat('i', count($question_ids_to_grade));
    
    // Select relevant grading columns
    $sql = "SELECT id, type, correct_answer, answer_text FROM questions WHERE id IN ($in)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Question fetch failed: " . $conn->error);
    }
    
    $stmt->bind_param($types, ...$question_ids_to_grade);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $grade_inserts = [];
    $raw_score = 0; 
    
    // --- GRADING LOOP ---
    while ($q = $res->fetch_assoc()) {
        $qid = (int)$q['id'];
        $qtype = $q['type'] ?? 'mcq';
        $correct_option = $q['correct_answer'] ?? ''; // Consistent column name used
        $correct_text   = $q['answer_text'] ?? '';
        $points = 1;

        $student_answer = trim($answers[$qid] ?? '');

        $score = 0;
        $is_correct = 0;

        if ($qtype === 'mcq') {
            if ($student_answer !== '' && strcasecmp($student_answer, $correct_option) === 0) {
                $score = $points;
                $is_correct = 1;
            }
        } else { // 'fill' or 'essay'
            if ($student_answer !== '' && $correct_text !== '') {
                $sa = strtolower(trim(preg_replace('/\s+/', ' ', $student_answer)));
                $ca = strtolower(trim(preg_replace('/\s+/', ' ', $correct_text)));

                // Automated Grading Logic (Exact Match / Similarity)
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
                    $needs_manual = 1; // Requires manual review
                }
            } else {
                // If answer is empty or no correct answer text is provided, it needs manual review
                $needs_manual = 1; 
            }
        }

        $raw_score  += $score;
        
        // Prepare data for batch insert of answers
        $grade_inserts[] = [$attempt_id, $qid, $student_answer, $is_correct];
    }
    $stmt->close();
    
    // --- BATCH SAVE ANSWERS ---
    if (!empty($grade_inserts)) {
        $values = [];
        $params = [];
        $types = '';
        
        // Build batch INSERT query
        foreach ($grade_inserts as $insert_data) {
            $values[] = '(?, ?, ?, ?)';
            $types .= 'iisi';
            $params = array_merge($params, $insert_data);
        }
        
        $sqlAns = "INSERT INTO attempt_answers (attempt_id, question_id, selected_answer, is_correct) VALUES " . implode(', ', $values);
        $stmtAns = $conn->prepare($sqlAns);
        
        if (!$stmtAns) {
            die("Answer insert prepared statement failed: " . $conn->error);
        }
        
        // Pass parameters to bind_param dynamically
        $stmtAns->bind_param($types, ...$params);
        
        if (!$stmtAns->execute()) {
            die("Answer batch insert failed: " . $stmtAns->error);
        }
        $stmtAns->close();
    }
}


/*
 * 3) Update attempt with final score (SECURE)
 */
$percentage = ($max_score > 0) ? ($raw_score / $max_score) * 100 : 0;
$transmuted = transmute($raw_score); 

$stmtU = $conn->prepare("
    UPDATE attempts
    SET raw_score=?, transmuted=?, needs_manual_grading=?,
        max_score=?, percentage=?, submitted_at=NOW()
    WHERE id=? AND student_id=? AND submitted_at IS NULL
");

if (!$stmtU) {
    die("Attempt update prepared statement failed: " . $conn->error);
}

// Bind parameters for the update (MUST BE BEFORE execute())
$stmtU->bind_param(
    'iiiidii', // i, i, i, i, d, i, i (raw_score, transmuted, manual, max_score, percentage, attempt_id, student_id)
    $raw_score,
    $transmuted,
    $needs_manual,
    $max_score,
    $percentage,
    $attempt_id,
    $student_id // Used in the WHERE clause for security
);

if (!$stmtU->execute()) {
    die("Attempt update execution failed: " . $stmtU->error);
}

// --- CRITICAL CHECK: Check Rows Updated ---
if ($conn->affected_rows === 0) {
    // If the update affected 0 rows, the WHERE clause failed (e.g., already submitted).
    die("FATAL SCORING ERROR: The attempt was not updated. It was either not found or already marked as submitted. Check attempt_id: " . $attempt_id);
}
// -------------------------------
$stmtU->close();

/*
 * 4) Redirect to results page
 */
// Clear any residual session data related to the exam attempt
unset($_SESSION['current_attempt_id']); 
unset($_SESSION['exam_questions']);

redirect("result.php?attempt_id=" . $attempt_id);
exit();