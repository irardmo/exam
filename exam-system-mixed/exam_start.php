<?php
require_once __DIR__ . '/db.php'; require_once __DIR__ . '/helpers.php';
require_login(); require_role('student');
$uid=$_SESSION['user']['id']; $exam_id=(int)($_POST['exam_id']??$_GET['exam_id']??0); if(!$exam_id) redirect('student.php');
$active=$conn->query("SELECT is_active FROM exams WHERE id=$exam_id")->fetch_assoc(); if(!$active||!$active['is_active']) redirect('student.php');
$attempt=$conn->query("SELECT * FROM attempts WHERE exam_id=$exam_id AND student_id=$uid AND submitted_at IS NULL ORDER BY id DESC LIMIT 1")->fetch_assoc();
if(!$attempt){
  $res=$conn->query("SELECT id FROM questions WHERE exam_id=$exam_id ORDER BY RAND() LIMIT 50"); $ids=[]; while($row=$res->fetch_assoc()) $ids[]=(int)$row['id'];
  if(count($ids)<50) die('This exam has fewer than 50 questions. Ask your teacher to add more.');
  $ids_json=json_encode($ids); $stmt=$conn->prepare("INSERT INTO attempts (exam_id,student_id,selected_question_ids) VALUES (?,?,?)");
  $stmt->bind_param('iis',$exam_id,$uid,$ids_json); $stmt->execute(); $attempt=$conn->query("SELECT * FROM attempts WHERE id=".$conn->insert_id)->fetch_assoc();
}
$ids=json_decode($attempt['selected_question_ids'],true)?:[]; if(!$ids) die('Attempt data missing.');
$idlist=implode(',', array_map('intval',$ids)); $qs=$conn->query("SELECT * FROM questions WHERE id IN ($idlist)"); $questions=[]; while($q=$qs->fetch_assoc()) $questions[$q['id']]=$q;
$ordered=[]; foreach($ids as $qid) if(isset($questions[$qid])) $ordered[]=$questions[$qid];
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
    <a class="badge" href="student.php" onclick="return confirm('Leave exam? Unsaved until submit.')">Exit</a>
  </div>
  /div>
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
