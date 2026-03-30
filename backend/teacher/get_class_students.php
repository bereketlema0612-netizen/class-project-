<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    sendResponse(false, 'Unauthorized', null, 403);
}
if (!$class_id) {
    sendResponse(false, 'Class ID required', null, 400);
}

$stmt = $conn->prepare("SELECT c.id, CONCAT('Grade ', c.grade_level, ' - ', c.section) AS name, c.grade_level, c.section FROM classes c WHERE c.id = ?");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$classResult = $stmt->get_result();

if ($classResult->num_rows !== 1) {
    sendResponse(false, 'Class not found', null, 404);
}

$classData = $classResult->fetch_assoc();

$stmt = $conn->prepare("SELECT ce.id, ce.student_username, ce.enrollment_date, s.username as student_id_generated, s.fname, s.mname, s.lname, u.email, s.DOB, s.sex, s.address FROM class_enrollments ce JOIN students s ON ce.student_username = s.username JOIN users u ON s.username = u.username WHERE ce.class_id = ? ORDER BY s.fname, s.lname");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$studentResult = $stmt->get_result();

$students = [];
while ($row = $studentResult->fetch_assoc()) {
    $row['full_name'] = $row['fname'] . ' ' . ($row['mname'] ? $row['mname'] . ' ' : '') . $row['lname'];
    $students[] = $row;
}

$responseData = [
    'class' => $classData,
    'students' => $students,
    'total_students' => count($students)
];

sendResponse(true, 'Class students retrieved successfully', $responseData, 200);

$stmt->close();
$conn->close();

?>
