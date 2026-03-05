<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    sendResponse(false, 'Unauthorized', null, 403);
}
$teacherUsername = $_SESSION['username'];

$stmt = $conn->prepare("
    SELECT u.id as user_id, t.username as employee_id_generated, t.fname, t.mname, t.lname,
           u.email, u.username, t.department, t.subject, t.DOB, t.age, t.sex,
           t.address, t.office_room, t.office_phone
    FROM users u
    JOIN teachers t ON t.username = u.username
    WHERE u.username = ?
");
if (!$stmt) {
    sendResponse(false, 'Failed to prepare teacher profile query: ' . $conn->error, null, 500);
}
$stmt->bind_param("s", $teacherUsername);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    sendResponse(false, 'Teacher not found', null, 404);
}

sendResponse(true, 'Teacher profile retrieved successfully', ['teacher' => $result->fetch_assoc()], 200);

$stmt->close();
$conn->close();
?>
