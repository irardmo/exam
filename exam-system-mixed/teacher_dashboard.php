<?php
session_start();
require_once 'helpers.php';
include 'db.php';
include 'header.php';

// Check if teacher is logged in
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

$message = '';

// ----------------------------------------------------------------------
// 1. Handle adding single question manually (SECURE & COLUMN-FIXED)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question']) && empty($_FILES['file']['name'])) {
    $exam_id = (int)$_POST['exam_id'];
    $created_by = $_SESSION['user']['id'];

    $question_text = $_POST['question'] ?? '';
    $option_a = $_POST['option_a'] ?? '';
    $option_b = $_POST['option_b'] ?? '';
    $option_c = $_POST['option_c'] ?? '';
    $option_d = $_POST['option_d'] ?? '';
    $correct_answer = strtoupper(trim($_POST['correct_answer'] ?? ''));

    // FIX: Use prepared statement and correct column names (question_text, correct_answer)
    $sql = "INSERT INTO questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_answer, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $message = "❌ Error preparing statement for manual entry: " . $conn->error;
    } else {
        // 'issssssi' corresponds to: exam_id(i), 5 options(s), correct_answer(s), created_by(i)
        $stmt->bind_param('issssssi', 
            $exam_id, 
            $question_text, 
            $option_a, 
            $option_b, 
            $option_c, 
            $option_d, 
            $correct_answer, 
            $created_by
        );

        if ($stmt->execute()) {
            $message = "✅ Question added successfully!";
        } else {
            $message = "❌ Error adding question: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ----------------------------------------------------------------------
// 2. Handle CSV upload (SECURE, PERFORMANT, & COLUMN-FIXED)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file']['name'])) {
    $exam_id = (int)$_POST['exam_id'];
    $created_by = $_SESSION['user']['id'];

    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $message = "❌ Upload error. Please try again.";
    } else {
        $fileName = $_FILES['file']['tmp_name'];
        $rowCount = 0;

        if (($file = fopen($fileName, "r")) !== false) {
            
            // FIX: Prepare statement once before the loop (performance and security)
            $sql = "INSERT INTO questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_answer, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                $message = "❌ Error preparing statement for CSV upload: " . $conn->error;
            } else {
                // skip header if your CSV has one:
                // fgetcsv($file, 10000, ","); 
                
                while (($row = fgetcsv($file, 10000, ",")) !== false) {
                    if (count($row) < 6) continue;

                    $question_text = $row[0] ?? '';
                    $option_a = $row[1] ?? '';
                    $option_b = $row[2] ?? '';
                    $option_c = $row[3] ?? '';
                    $option_d = $row[4] ?? '';
                    $correct_answer = strtoupper(trim($row[5] ?? ''));

                    if ($question_text && $option_a && $option_b && $option_c && $option_d && in_array($correct_answer, ['A','B','C','D'], true)) {
                        
                        // FIX: Bind and execute prepared statement inside the loop
                        $stmt->bind_param('issssssi', 
                            $exam_id, 
                            $question_text, 
                            $option_a, 
                            $option_b, 
                            $option_c, 
                            $option_d, 
                            $correct_answer, 
                            $created_by
                        );
                        if ($stmt->execute()) {
                            $rowCount++;
                        }
                    }
                }
                $stmt->close();
                $message = "✅ $rowCount questions uploaded successfully!";
            }
            fclose($file);
        } else {
            $message = "❌ Unable to read the CSV file.";
        }
    }
}
?>

<div class="card">
    <h2>Teacher Dashboard</h2>

    <?php if ($message): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="tab-container">
        <button class="tab active" data-tab-target="add-question">Add Questions</button>
        <button class="tab" data-tab-target="upload-csv">Upload CSV</button>
        <button class="tab" data-tab-target="view-results">View Results</button>
    </div>

    <div id="add-question" class="tab-content active">
        <h3>Add Question</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="exam_id">Select Exam:</label>
                <select name="exam_id" id="exam_id" required>
                    <?php
                    $exams = $conn->query("SELECT id, title FROM exams ORDER BY created_at DESC");
                    while ($exam = $exams->fetch_assoc()):
                    ?>
                    <option value="<?php echo $exam['id']; ?>"><?php echo htmlspecialchars($exam['title']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group"><label>Question</label><input type="text" name="question" required></div>
            <div class="form-group"><label>Option A</label><input type="text" name="option_a" required></div>
            <div class="form-group"><label>Option B</label><input type="text" name="option_b" required></div>
            <div class="form-group"><label>Option C</label><input type="text" name="option_c" required></div>
            <div class="form-group"><label>Option D</label><input type="text" name="option_d" required></div>
            <div class="form-group"><label>Correct Answer (A/B/C/D)</label><input type="text" name="correct_answer" required></div>
            <button type="submit" class="btn">Add Question</button>
        </form>
    </div>

    <div id="upload-csv" class="tab-content">
        <h3>Upload Questions</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="exam_id_upload">Select Exam:</label>
                <select id="exam_id_upload" name="exam_id" required>
                    <option value="">-- Choose Exam --</option>
                    <?php
                    // Reuse $examRes if possible, or fetch again if necessary
                    $examRes = $conn->query("SELECT id, title FROM exams ORDER BY created_at DESC");
                    while ($exam = $examRes->fetch_assoc()) {
                        echo '<option value="'.$exam['id'].'">'.htmlspecialchars($exam['title']).'</option>';
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="file">Select File (CSV):</label>
                <input type="file" id="file" name="file" accept=".csv" required>
            </div>

            <button type="submit" class="btn">Upload</button>
        </form>
    </div>

    <div id="view-results" class="tab-content">
        <h3>Student Results</h3>
        <div style="text-align: right; margin-bottom: 10px;">
            <a href="export_results.php" class="btn btn-download">Download All Results (CSV)</a>
        </div>
        <?php
        // --- CORRECTED SQL QUERY (Fixes Missing Student Name) ---
        $results = $conn->query("
            SELECT a.id AS attempt_id,
                    -- FIX: Concatenate first and last name from students table
                    CONCAT(s.first_name, ' ', s.last_name) AS student_full_name,
                    e.title AS exam_title,
                    a.raw_score,         
                    a.max_score,
                    a.percentage,
                    a.submitted_at
            FROM attempts a
            JOIN exams e ON a.exam_id = e.id
            JOIN users u ON a.student_id = u.id        -- Joins attempt to user
            JOIN students s ON u.id = s.user_id        -- Joins user to student details
            WHERE a.submitted_at IS NOT NULL
            ORDER BY a.submitted_at DESC
        ");
        ?>
        <table>
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
                    // Use raw_score key
                    $score = (int)$r['raw_score'];
                    $max   = (int)$r['max_score'];
                    
                    // Transmute score only if needed (for display consistency)
                    $transmuted = transmute($score);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($r['student_full_name']) ?></td>
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
                <tr><td colspan="5">No exam results yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>