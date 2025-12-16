<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_login(); 
require_role('teacher');

$rows = null;
$ordered_list = null;
$attempt_id = 0;

// --- LIST PENDING ATTEMPTS (INITIAL LOAD) ---
// Securely list attempts needing manual grading
$stmt_list = $conn->prepare("SELECT a.id, a.exam_id, u.name as student, a.started_at, a.submitted_at FROM attempts a JOIN users u ON u.id=a.student_id WHERE a.needs_manual_grading=1 ORDER BY a.id DESC");
if (!$stmt_list) die("Database error during list setup: " . $conn->error);
$stmt_list->execute();
$rows = $stmt_list->get_result();
$stmt_list->close();


// --- PROCESS MANUAL GRADING (POST REQUEST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attempt_id'])) {
    $attempt_id = (int)$_POST['attempt_id'];
    
    // Begin transaction for safety during grading and score recalculation
    $conn->begin_transaction();
    $success = true;

    // FIX 3: Securely process manual grading inputs
    $stmt_update_grade = $conn->prepare("UPDATE attempt_answers SET is_correct=? WHERE id=?");
    if (!$stmt_update_grade) { $success = false; die("Grade update setup failed: " . $conn->error); }

    foreach ($_POST as $k => $v) {
        if (strpos($k, 'grade_') === 0) {
            $aa_id = (int)substr($k, 6);
            $val = ($v == '1') ? 1 : 0;
            
            $stmt_update_grade->bind_param('ii', $val, $aa_id);
            if (!$stmt_update_grade->execute()) { $success = false; break; }
        }
    }
    $stmt_update_grade->close();

    if ($success) {
        // FIX 4a: Securely recalculate raw score
        $stmt_recalc = $conn->prepare("SELECT SUM(CASE WHEN is_correct=1 THEN 1 ELSE 0 END) as c FROM attempt_answers WHERE attempt_id=?");
        if (!$stmt_recalc) { $success = false; die("Recalc setup failed: " . $conn->error); }
        $stmt_recalc->bind_param('i', $attempt_id);
        $stmt_recalc->execute();
        $res = $stmt_recalc->get_result()->fetch_assoc();
        $c = (int)($res['c'] ?? 0);
        $stmt_recalc->close();

        $trans = transmute($c);
        
        // FIX 4b: Securely update final attempt scores and clear manual flag
        $stmt_final = $conn->prepare("UPDATE attempts SET raw_score=?, transmuted=?, needs_manual_grading=0 WHERE id=?");
        if (!$stmt_final) { $success = false; die("Final update setup failed: " . $conn->error); }
        $stmt_final->bind_param('iii', $c, $trans, $attempt_id);
        
        if (!$stmt_final->execute()) { $success = false; }
        $stmt_final->close();
    }
    
    if ($success) {
        $conn->commit();
    } else {
        $conn->rollback();
        die("Transaction failed. Grades not finalized.");
    }
    
    redirect('grade_manual.php');
}

// --- LOAD ATTEMPT DETAILS (GET REQUEST) ---
if(isset($_GET['attempt_id'])){
    $attempt_id = (int)$_GET['attempt_id'];
    
    // FIX 1: Securely fetch attempt details
    $stmt_attempt = $conn->prepare("SELECT * FROM attempts WHERE id=?");
    if (!$stmt_attempt) die("Database error during attempt detail setup: " . $conn->error);
    $stmt_attempt->bind_param('i', $attempt_id);
    $stmt_attempt->execute();
    $attempt = $stmt_attempt->get_result()->fetch_assoc();
    $stmt_attempt->close();
    
    if(!$attempt) die('Attempt not found');
    
    $qidlist_array = json_decode($attempt['selected_question_ids'], true) ?: [];
    if (empty($qidlist_array)) die('Question list missing for this attempt.');
    
    $qidlist = implode(',', array_map('intval', $qidlist_array));
    
    // FIX 2: Securely fetch questions and student answers
    // Rely on array_map('intval') for safety on $qidlist
    $sql_qs = "SELECT q.id, q.question_text, q.correct_answer, q.answer_text, aa.selected_answer, aa.is_correct, aa.id as aa_id 
               FROM questions q 
               JOIN attempt_answers aa ON aa.question_id=q.id AND aa.attempt_id=? 
               WHERE q.id IN ($qidlist)";
               
    $stmt_qs = $conn->prepare($sql_qs);
    if (!$stmt_qs) die("Database error during questions fetch setup: " . $conn->error);
    $stmt_qs->bind_param('i', $attempt_id);
    $stmt_qs->execute();
    $qs = $stmt_qs->get_result();
    
    $ordered = [];
    while($r = $qs->fetch_assoc()) $ordered[$r['id']] = $r;
    $stmt_qs->close();
    
    // Reorder according to original IDs
    $ordered_list = [];
    foreach($qidlist_array as $id) {
        if(isset($ordered[$id])) $ordered_list[] = $ordered[$id];
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Manual Grading</title><link rel="stylesheet" href="style.css"></head><body>
<div class="wrap"><div class="card">
<h1>Manual Grading</h1>
<?php if($rows && $rows->num_rows>0): ?>
  <h2>Pending Attempts</h2>
  <table class="table"><tr><th>Attempt</th><th>Exam ID</th><th>Student</th><th>Submitted</th><th>Action</th></tr>
  <?php $rows->data_seek(0); // Reset result pointer after fetching for listing ?>
  <?php while($r=$rows->fetch_assoc()): ?>
    <tr><td>#<?= $r['id'] ?></td><td><?= h($r['exam_id']) ?></td><td><?= h($r['student']) ?></td><td><small class="muted"><?= h($r['submitted_at']) ?></small></td>
    <td><a class="badge" href="?attempt_id=<?= $r['id'] ?>">Grade</a></td></tr>
  <?php endwhile; ?>
  </table>
<?php else: ?>
  <p>No attempts pending manual grading.</p>
<?php endif; ?>

<?php if(isset($ordered_list)): ?>
  <hr><h2>Grading Attempt #<?= $attempt_id ?></h2>
  <p><strong>Note:</strong> Only questions with a text answer (not MCQ) will appear below for grading.</p>
  <form method="post">
    <input type="hidden" name="attempt_id" value="<?= $attempt_id ?>">
    <?php foreach($ordered_list as $i=>$q): 
      // Only display non-MCQ answers that require review (optional filter, but helpful)
      if ($q['type'] !== 'mcq'): 
    ?>
      <div class="q">
        <div><strong><?= ($i+1) ?>.</strong> <?= h($q['question_text']) ?></div>
        <div class="muted"><strong>Student Answer:</strong> <span style="color:#007bff;"><?= h($q['selected_answer']) ?></span></div>
        <div class="muted"><strong>Correct Answer (Text):</strong> <span style="color:#28a745;"><?= h($q['answer_text']) ?></span></div>
        <div class="flex" style="margin-top: 10px;">
          <label><input type="radio" name="grade_<?= $q['aa_id'] ?>" value="1" <?= $q['is_correct']==1?'checked':'' ?>> Correct (+1 Point)</label>
          <label><input type="radio" name="grade_<?= $q['aa_id'] ?>" value="0" <?= $q['is_correct']==0?'checked':'' ?>> Incorrect (0 Points)</label>
        </div>
      </div>
    <?php 
      endif;
    endforeach; ?>
    <button type="submit">Save Grades & Finalize</button>
  </form>
<?php endif; ?>
</div></div></body></html>