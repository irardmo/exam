<?php
// export_results.php (Final Secure and Robust Version)

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
session_start();

// Security Check
require_login();
require_role('teacher');

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="exam_results_' . date('Ymd_His') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// --- CRITICAL FIX: Ensure $output is a valid resource ---
// Initialize $output to null
$output = null; 

// Attempt to open the output stream (php://output is standard output)
if (($output = fopen('php://output', 'w')) === false) {
    // If opening failed, stop execution
    die("Error: Could not open output stream for CSV generation. Check PHP configuration.");
}
// --------------------------------------------------------

// Write the CSV header row
fputcsv($output, [
    'Last Name', 
    'First Name', 
    'M.I.', 
    'Course',
    'Year & Section',
    'Exam Title', 
    'Raw Score', 
    'Max Score', 
    'Transmuted Grade', 
    'Submitted At'
]);

// Fetch Data: Ordered alphabetically by Last Name
// NEW JOIN: attempts -> users -> students (Correct for normalized schema)
$sql = "
    SELECT 
        st.first_name,
        st.middle_initial,
        st.last_name,
        st.course,
        st.year_section,
        e.title AS exam_title,
        a.raw_score,
        a.max_score,
        a.transmuted,
        a.submitted_at
    FROM attempts a
    JOIN users u ON a.student_id = u.id
    JOIN students st ON u.id = st.user_id 
    JOIN exams e ON a.exam_id = e.id
    WHERE a.submitted_at IS NOT NULL
    ORDER BY st.last_name ASC, st.first_name ASC
";

// Using $conn->query for simple SELECT is acceptable here, 
// as no user input is involved in constructing the query string.
$results = $conn->query($sql);

if ($results) {
    while ($row = $results->fetch_assoc()) {
        
        // Use stored transmuted score or calculate if missing (robust)
        $transmuted = $row['transmuted'] ?? transmute((int)$row['raw_score']);

        // Write data row
        fputcsv($output, [
            $row['last_name'],
            $row['first_name'],
            $row['middle_initial'],
            $row['course'],
            $row['year_section'],
            $row['exam_title'],
            $row['raw_score'],
            $row['max_score'],
            number_format((float)$transmuted, 2),
            $row['submitted_at']
        ]);
    }
}

// Close the file stream (Only if it was successfully opened)
if ($output !== null) {
    fclose($output);
}
exit();