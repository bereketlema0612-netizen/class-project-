<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$sql = "
    SELECT ce.id, ce.student_username, ce.enrollment_date,
           c.id AS class_id, c.grade_level, c.section, c.stream,
           s.fname, s.mname, s.lname
    FROM class_enrollments ce
    JOIN students s ON ce.student_username = s.username
    JOIN classes c ON ce.class_id = c.id
    ORDER BY ce.enrollment_date DESC, s.fname, s.lname
";
$result = $conn->query($sql);
if (!$result) {
    sendResponse(false, 'Failed to load assigned students: ' . $conn->error, null, 500);
}

$items = [];
while ($row = $result->fetch_assoc()) {
    $row['full_name'] = trim($row['fname'] . ' ' . ($row['mname'] ? $row['mname'] . ' ' : '') . $row['lname']);
    $items[] = $row;
}

sendResponse(true, 'Assigned students retrieved', ['assigned_students' => $items], 200);
$conn->close();
?>
