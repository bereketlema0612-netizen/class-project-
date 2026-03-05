<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/curriculum.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$username = sanitizeInput($_GET['username'] ?? '');
if ($username === '') {
    sendResponse(false, 'Student username is required', null, 400);
}

$sql = "
    SELECT
        u.username,
        u.password,
        s.fname,
        s.mname,
        s.lname,
        s.grade_level,
        s.stream,
        COALESCE(
            MAX(ay.academic_year),
            (SELECT ss.current_academic_year FROM school_settings ss ORDER BY ss.id ASC LIMIT 1),
            'N/A'
        ) AS academic_year
    FROM users u
    JOIN students s ON s.username = u.username
    LEFT JOIN class_enrollments ce ON ce.student_username = s.username
    LEFT JOIN classes c ON c.id = ce.class_id
    LEFT JOIN academic_years ay ON ay.id = c.academic_year_id
    WHERE u.username = ? AND u.role = 'student'
    GROUP BY u.username, u.password, s.fname, s.mname, s.lname, s.grade_level, s.stream
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    sendResponse(false, 'Prepare failed: ' . $conn->error, null, 500);
}
$stmt->bind_param('s', $username);
if (!$stmt->execute()) {
    sendResponse(false, 'Query failed: ' . $stmt->error, null, 500);
}

$row = $stmt->get_result()->fetch_assoc();
if (!$row) {
    sendResponse(false, 'Student not found', null, 404);
}

$row['full_name'] = trim($row['fname'] . ' ' . ($row['mname'] ? $row['mname'] . ' ' : '') . $row['lname']);
$row['subject_count'] = curriculumSubjectCount($row['grade_level'] ?? '', $row['stream'] ?? '');

sendResponse(true, 'Registration slip details retrieved', ['details' => $row], 200);
$conn->close();
?>
