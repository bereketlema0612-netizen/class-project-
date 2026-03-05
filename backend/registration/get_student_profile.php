<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../helpers/functions.php';

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

$stmt = $conn->prepare("
    SELECT
        u.username, u.email, u.status,
        s.fname, s.mname, s.lname, s.DOB, s.age, s.sex, s.grade_level, s.stream, s.address, s.parent_name, s.parent_phone,
        c.section, c.stream AS class_stream
    FROM users u
    JOIN students s ON s.username = u.username
    LEFT JOIN class_enrollments ce ON ce.student_username = s.username
    LEFT JOIN classes c ON c.id = ce.class_id
    WHERE u.username = ? AND u.role = 'student'
    ORDER BY ce.id DESC
    LIMIT 1
");
if (!$stmt) {
    sendResponse(false, 'Prepare failed: ' . $conn->error, null, 500);
}
$stmt->bind_param("s", $username);
if (!$stmt->execute()) {
    sendResponse(false, 'Query failed: ' . $stmt->error, null, 500);
}

$profile = $stmt->get_result()->fetch_assoc();
if (!$profile) {
    sendResponse(false, 'Student not found', null, 404);
}
$profile['full_name'] = trim($profile['fname'] . ' ' . ($profile['mname'] ? $profile['mname'] . ' ' : '') . $profile['lname']);

sendResponse(true, 'Student profile retrieved', ['profile' => $profile], 200);
$conn->close();
?>
