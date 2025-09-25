<?php
session_start();
require_once 'helpers.php';
include 'db.php';

// Check if teacher is logged in
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

$message = '';

// Handle adding single question manually
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question']) && empty($_FILES['file']['name'])) {
    $exam_id = (int)$_POST['exam_id'];
    $created_by = $_SESSION['user']['id'];

    $question = mysqli_real_escape_string($conn, $_POST['question']);
    $option_a = mysqli_real_escape_string($conn, $_POST['option_a']);
    $option_b = mysqli_real_escape_string($conn, $_POST['option_b']);
    $option_c = mysqli_real_escape_string($conn, $_POST['option_c']);
    $option_d = mysqli_real_escape_string($conn, $_POST['option_d']);
    $correct_answer = mysqli_real_escape_string($conn, strtoupper($_POST['correct_answer']));

    $sql = "INSERT INTO questions (exam_id, question, option_a, option_b, option_c, option_d, correct_option, created_by, created_at)
            VALUES ('$exam_id', '$question', '$option_a', '$option_b', '$option_c', '$option_d', '$correct_answer', '$created_by', NOW())";
    if (mysqli_query($conn, $sql)) {
        $message = "✅ Question added successfully!";
    } else {
        $message = "❌ Error adding question: " . mysqli_error($conn);
    }
}

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file']['name'])) {
    $exam_id = (int)$_POST['exam_id'];
    $created_by = $_SESSION['user']['id'];

    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $message = "❌ Upload error. Please try again.";
    } else {
        $fileName = $_FILES['file']['tmp_name'];
        $rowCount = 0;

        if (($file = fopen($fileName, "r")) !== false) {
            // skip header if your CSV has one:
            // fgetcsv($file, 10000, ",");

            while (($row = fgetcsv($file, 10000, ",")) !== false) {
                if (count($row) < 6) continue;

                $question = mysqli_real_escape_string($conn, $row[0] ?? '');
                $option_a = mysqli_real_escape_string($conn, $row[1] ?? '');
                $option_b = mysqli_real_escape_string($conn, $row[2] ?? '');
                $option_c = mysqli_real_escape_string($conn, $row[3] ?? '');
                $option_d = mysqli_real_escape_string($conn, $row[4] ?? '');
                $correct_answer = mysqli_real_escape_string($conn, strtoupper(trim($row[5] ?? '')));

                if ($question && $option_a && $option_b && $option_c && $option_d && in_array($correct_answer, ['A','B','C','D'], true)) {
                    $sql = "INSERT INTO questions (exam_id, question, option_a, option_b, option_c, option_d, correct_option, created_by, created_at)
                            VALUES ('$exam_id', '$question', '$option_a', '$option_b', '$option_c', '$option_d', '$correct_answer', '$created_by', NOW())";
                    if (mysqli_query($conn, $sql)) {
                        $rowCount++;
                    }
                }
            }
            fclose($file);
            $message = "✅ $rowCount questions uploaded successfully!";
        } else {
            $message = "❌ Unable to read the CSV file.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Teacher Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style-all.css">
</head>
<body class="p-4">
<h3 class="mb-3 d-flex justify-content-between align-items-center">
  Teacher Dashboard
  <a href="login.php?logout=1" class="btn btn-danger btn-sm">Logout</a>
</h3>

  <?php if ($message): ?>
    <div class="alert alert-info"><?php echo $message; ?></div>
  <?php endif; ?>

  <!-- ✅ Nav Tabs -->
  <ul class="nav nav-tabs" id="teacherTabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#add">Add Questions</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#upload">Upload CSV</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#view">View Results</a></li>
  </ul>

  <!-- ✅ Tab Content -->
  <div class="tab-content mt-3">
    <!-- Add Questions -->
    <div class="tab-pane fade show active" id="add">
      <h4>Add Questions</h4>
      <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
          <label for="exam_id" class="form-label">Select Exam:</label>
          <select name="exam_id" id="exam_id" class="form-select" required>
            <?php
            $exams = $conn->query("SELECT id, title FROM exams ORDER BY created_at DESC");
            while ($exam = $exams->fetch_assoc()):
            ?>
              <option value="<?php echo $exam['id']; ?>"><?php echo htmlspecialchars($exam['title']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="mb-3"><label>Question</label><input type="text" name="question" class="form-control" required></div>
        <div class="mb-3"><label>Option A</label><input type="text" name="option_a" class="form-control" required></div>
        <div class="mb-3"><label>Option B</label><input type="text" name="option_b" class="form-control" required></div>
        <div class="mb-3"><label>Option C</label><input type="text" name="option_c" class="form-control" required></div>
        <div class="mb-3"><label>Option D</label><input type="text" name="option_d" class="form-control" required></div>
        <div class="mb-3"><label>Correct Answer (A/B/C/D)</label><input type="text" name="correct_answer" class="form-control" required></div>
        <button type="submit" class="btn btn-primary">Add Question</button>
      </form>
    </div>

    <!-- Upload CSV -->
    <div class="tab-pane fade" id="upload" role="tabpanel" aria-labelledby="upload-tab">
  <h4>Upload Questions</h4>
  <form action="upload_exam.php" method="POST" enctype="multipart/form-data">
    <div class="mb-3">
      <label for="exam_id" class="form-label">Select Exam:</label>
      <select class="form-select" id="exam_id" name="exam_id" required>
        <option value="">-- Choose Exam --</option>
        <?php
        $examRes = $conn->query("SELECT id, title FROM exams ORDER BY created_at DESC");
        while ($exam = $examRes->fetch_assoc()) {
            echo '<option value="'.$exam['id'].'">'.htmlspecialchars($exam['title']).'</option>';
        }
        ?>
      </select>
    </div>

    <div class="mb-3">
      <label for="file" class="form-label">Select File (CSV):</label>
      <input type="file" class="form-control" id="file" name="file" accept=".csv" required>
    </div>

    <button type="submit" class="btn btn-success">Upload</button>
  </form>
</div>


    <!-- View Results -->
    <div class="tab-pane fade" id="view">
      <?php
$results = $conn->query("
    SELECT a.id AS attempt_id,
           s.name AS student_name,
           e.title AS exam_title,
           a.score,
           a.max_score,
           a.percentage,
           a.submitted_at
    FROM attempts a
    JOIN users s ON a.student_id = s.id
    JOIN exams e ON a.exam_id = e.id
    ORDER BY a.submitted_at DESC
"); 
?>
<table class="table table-bordered">
  <thead>
    <tr>
      <th>Student</th>
      <th>Exam</th>
      <th>Score</th>
      <th>Rating</th>
      <th>Date Taken</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($results && $results->num_rows > 0): ?>
      <?php while ($r = $results->fetch_assoc()): ?>
        <?php
                    $score = (int)$r['score'];
          $max   = (int)$r['max_score'];

          // ✅ Use helper transmutation
          $transmuted = transmute($score);

          // ✅ Rating based on transmuted grade
          if ($transmuted >= 90) {
              $rating = "Excellent";
          } elseif ($transmuted >= 80) {
              $rating = "Good";
          } elseif ($transmuted >= 75) {
              $rating = "Fair";
          } else {
              $rating = "Needs Improvement";
          }

        ?>
        <tr>
  <td><?= htmlspecialchars($r['student_name']) ?></td>
  <td><?= htmlspecialchars($r['exam_title']) ?></td>
  <td><?= $score ?>/<?= $max ?></td>
  <td><?= number_format($transmuted, 2) ?></td>
  <td>
    <?php
      if ($r['submitted_at']) {
          $date = new DateTime($r['submitted_at']);
          echo $date->format('F j, Y H:i');
      } else {
          echo 'N/A';
      }
    ?>
  </td>
</tr>

      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="6">No exam results yet.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
