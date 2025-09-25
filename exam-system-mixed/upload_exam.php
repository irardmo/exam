<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['file']['name'])) {
        $file = $_FILES['file'];
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filename = time() . '_' . basename($file['name']);
        $target = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $target)) {
            // ✅ Get exam_id from teacher’s selection
            $exam_id = (int)$_POST['exam_id'];

            // ✅ Parse and save to DB
            parse_csv_to_db($target, $exam_id);

            echo "<script>alert('Upload successful!');window.location.href='teacher_dashboard.php';</script>";
        } else {
            echo "<script>alert('Upload failed!');window.location.href='teacher_dashboard.php';</script>";
        }
    }
}

function parse_csv_to_db($filepath, $exam_id) {
    global $conn;
    if (($handle = fopen($filepath, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($data) < 6) continue;

            $stmt = $conn->prepare("
                INSERT INTO questions (exam_id, question, option_a, option_b, option_c, option_d, correct_option)
                VALUES (?,?,?,?,?,?,?)
            ");
            $stmt->bind_param('issssss',
                $exam_id, $data[0], $data[1], $data[2], $data[3], $data[4], $data[5]
            );
            $stmt->execute();
        }
        fclose($handle);
    }
}
?>
