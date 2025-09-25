<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_login(); require_role('teacher');

// list attempts needing manual grading
$rows = $conn->query("SELECT a.id, a.exam_id, u.name as student, a.started_at, a.submitted_at FROM attempts a JOIN users u ON u.id=a.student_id WHERE a.needs_manual_grading=1 ORDER BY a.id DESC");

if(isset($_GET['attempt_id'])){
  $attempt_id = (int)$_GET['attempt_id'];
  $attempt = $conn->query("SELECT * FROM attempts WHERE id=$attempt_id")->fetch_assoc();
  if(!$attempt) die('Attempt not found');
  $qidlist = implode(',', array_map('intval', json_decode($attempt['selected_question_ids'], true)));
  $qs = $conn->query("SELECT q.*, aa.selected_answer, aa.is_correct, aa.id as aa_id FROM questions q JOIN attempt_answers aa ON aa.question_id=q.id AND aa.attempt_id=$attempt_id WHERE q.id IN ($qidlist) ");
  $ordered = [];
  while($r = $qs->fetch_assoc()) $ordered[$r['id']] = $r;
  // reorder according to original ids
  $ids = json_decode($attempt['selected_question_ids'], true);
  $ordered_list = [];
  foreach($ids as $id) if(isset($ordered[$id])) $ordered_list[] = $ordered[$id];
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['attempt_id'])){
  $attempt_id = (int)$_POST['attempt_id'];
  // process manual grading inputs: inputs like grade_<aa_id> = 1 or 0
  foreach($_POST as $k=>$v){
    if(strpos($k,'grade_')===0){
      $aa_id = (int)substr($k,6);
      $val = ($v=='1')?1:0;
      $conn->query("UPDATE attempt_answers SET is_correct=$val WHERE id=$aa_id");
    }
  }
  // recalc raw score
  $res = $conn->query("SELECT SUM(CASE WHEN is_correct=1 THEN 1 ELSE 0 END) as c FROM attempt_answers WHERE attempt_id=$attempt_id");
  $c = (int)$res->fetch_assoc()['c'];
  $trans = transmute($c);
  $conn->query("UPDATE attempts SET raw_score=$c, transmuted=$trans, needs_manual_grading=0 WHERE id=$attempt_id");
  redirect('grade_manual.php');
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Manual Grading</title><link rel="stylesheet" href="style.css"></head><body>
<div class="wrap"><div class="card">
<h1>Manual Grading</h1>
<?php if(isset($rows) && $rows->num_rows>0): ?>
  <h2>Pending Attempts</h2>
  <table class="table"><tr><th>Attempt</th><th>Exam</th><th>Student</th><th>Submitted</th><th>Action</th></tr>
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
  <form method="post">
    <input type="hidden" name="attempt_id" value="<?= $attempt_id ?>">
    <?php foreach($ordered_list as $i=>$q): ?>
      <div class="q">
        <div><strong><?= ($i+1) ?>.</strong> <?= h($q['question_text']) ?></div>
        <div class="muted">Student answer: <?= h($q['selected_answer']) ?></div>
        <div class="flex">
          <label><input type="radio" name="grade_<?= $q['aa_id'] ?>" value="1" <?= $q['is_correct']==1?'checked':'' ?>> Correct</label>
          <label><input type="radio" name="grade_<?= $q['aa_id'] ?>" value="0" <?= $q['is_correct']==0?'checked':'' ?>> Incorrect</label>
        </div>
      </div>
    <?php endforeach; ?>
    <button type="submit">Save Grades & Finalize</button>
  </form>
<?php endif; ?>
</div></div></body></html>
