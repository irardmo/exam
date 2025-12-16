<?php
require_once __DIR__ . '/db.php'; 
require_once __DIR__ . '/helpers.php';
require_login(); 
require_role('student');

$uid = $_SESSION['user']['id']; 
$exam_id = (int)($_POST['exam_id'] ?? $_GET['exam_id'] ?? 0); 
if (!$exam_id) redirect('student_dashboard.php'); // FIX: Redirect to dashboard, not 'student.php'

// 1. Check if exam is active (SECURE)
$stmt = $conn->prepare("SELECT is_active FROM exams WHERE id=?");
$stmt->bind_param('i', $exam_id);
$stmt->execute();
$active = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$active || !$active['is_active']) redirect('student_dashboard.php'); // FIX: Redirect to dashboard

// 2. Check for an existing, unsubmitted attempt (SECURE)
$stmt = $conn->prepare("SELECT * FROM attempts WHERE exam_id=? AND student_id=? AND submitted_at IS NULL ORDER BY id DESC LIMIT 1");
$stmt->bind_param('ii', $exam_id, $uid);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$attempt) {
    // 3. Create new attempt if none exists (SECURE)
    
    // Select questions (still uses RAND() which is slow but secure here)
    $stmt_q = $conn->prepare("SELECT id FROM questions WHERE exam_id=? ORDER BY RAND() LIMIT 50");
    $stmt_q->bind_param('i', $exam_id);
    $stmt_q->execute();
    $res_q = $stmt_q->get_result();
    
    $ids = []; 
    while($row = $res_q->fetch_assoc()) {
        $ids[] = (int)$row['id'];
    }
    $stmt_q->close();

    if (count($ids) < 50) {
        // FIX: Redirect instead of dying
        $_SESSION['error'] = 'This exam has fewer than 50 questions. Ask your teacher to add more.';
        redirect('student_dashboard.php');
    }
    
    $ids_json = json_encode($ids); 
    
    // Insert new attempt record (SECURE)
    $stmt_i = $conn->prepare("INSERT INTO attempts (exam_id, student_id, selected_question_ids, max_score) VALUES (?, ?, ?, 50)"); // Max score fixed at 50
    $stmt_i->bind_param('iis', $exam_id, $uid, $ids_json); 
    $stmt_i->execute(); 
    $insert_id = $conn->insert_id;
    $stmt_i->close();
    
    // Fetch the new attempt record (SECURE)
    $stmt_a = $conn->prepare("SELECT * FROM attempts WHERE id=?");
    $stmt_a->bind_param('i', $insert_id);
    $stmt_a->execute();
    $attempt = $stmt_a->get_result()->fetch_assoc();
    $stmt_a->close();
}

$ids = json_decode($attempt['selected_question_ids'], true) ?: []; 
if (!$ids) {
    $_SESSION['error'] = 'Attempt data missing. Please try again.';
    redirect('student_dashboard.php');
}

// 4. Fetch questions for the exam (SECURE)
$idlist = implode(',', array_map('intval', $ids)); 

// We cannot use a prepared statement with IN ($idlist) easily, so we rely on array_map('intval') for safety
// to ensure $idlist is purely comma-separated integers.
$qs = $conn->query("SELECT id, question_text, option_a, option_b, option_c, option_d, type FROM questions WHERE id IN ($idlist)"); 

$questions = []; 
while($q = $qs->fetch_assoc()) {
    $questions[$q['id']] = $q;
}

// 5. Order questions correctly
$ordered = []; 
foreach($ids as $qid) {
    if (isset($questions[$qid])) {
        $ordered[] = $questions[$qid];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Exam</title>
  <link rel="stylesheet" href="style-all.css">
  <body>
<div class="wrap">
  <div class="card">
<div class="flex" style="justify-content:space-between">
  <h1>Exam</h1>
  <div class="flex">
    <span class="badge">Attempt #<?= $attempt['id'] ?></span>
    <a class="badge" href="student_dashboard.php" onclick="return confirm('Leave exam? Unsaved until submit.')">Exit</a>
  </div>
  </div> 
<form method="post" action="exam_submit.php">
  <input type="hidden" name="attempt_id" value="<?= $attempt['id'] ?>">
<?php $i=1; foreach($ordered as $q): ?>
<div class="q">
<div>
  <strong><?= $i ?>.</strong> 
  <?= h($q['question_text']) ?>
</div>
<?php if($q['type']==='mcq'): ?>
  <?php foreach(['A','B','C','D'] as $opt): ?>
    <label>
      <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $opt ?>"> <?= $opt ?>) <?= h($q['option_'.strtolower($opt)]) ?>
      </label><?php endforeach; ?>
<?php else: ?>
  <label>Answer</label>
  <input type="text" name="answers[<?= $q['id'] ?>]" placeholder="Type your answer here">
<?php endif; ?>
</div>
<?php $i++; endforeach; ?>
<button type="submit" onclick="return confirm('Submit answers now?')">Submit Exam</button>
</form>
</div>
</div>
</body>
</html>