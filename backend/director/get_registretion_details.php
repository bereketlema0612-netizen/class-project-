<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'director') {
    sendResponse(false, 'Unauthorized access', null, 403);
}

$registration_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$registration_id) {
    sendResponse(false, 'Registration ID required', null, 400);
}

$stmt = $conn->prepare("
    SELECT r.id, r.username, r.role, r.status, r.submitted_at, r.approved_at, u.email, u.username,
           COALESCE(s.fname, t.fname, ra.fname, d.fname) as fname,
           COALESCE(s.mname, t.mname, ra.mname, d.mname) as mname,
           COALESCE(s.lname, t.lname, ra.lname, d.lname) as lname
    FROM registrations r
    JOIN users u ON r.username = u.username
    LEFT JOIN students s ON s.username = u.username
    LEFT JOIN teachers t ON t.username = u.username
    LEFT JOIN admins ra ON ra.username = u.username
    LEFT JOIN directors d ON d.username = u.username
    WHERE r.id = ?
");
$stmt->bind_param("i", $registration_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    sendResponse(false, 'Registration not found', null, 404);
}

$registration = $result->fetch_assoc();
$username = $registration['username'];
$role = $registration['role'];

$registration['full_name'] = $registration['fname'] . ' ' . ($registration['mname'] ? $registration['mname'] . ' ' : '') . $registration['lname'];

if ($role === 'student') {
    $stmt = $conn->prepare("SELECT s.username as student_id_generated, s.DOB, s.age, s.sex, s.grade_level, s.address, s.parent_name, s.parent_phone FROM students s WHERE s.username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $studentResult = $stmt->get_result();
    if ($studentResult->num_rows > 0) {
        $registration['student_details'] = $studentResult->fetch_assoc();
    }
} elseif ($role === 'teacher') {
    $stmt = $conn->prepare("SELECT t.username as employee_id_generated, t.DOB, t.age, t.sex, t.department, t.subject, t.address, t.office_room, t.office_phone FROM teachers t WHERE t.username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $teacherResult = $stmt->get_result();
    if ($teacherResult->num_rows > 0) {
        $registration['teacher_details'] = $teacherResult->fetch_assoc();
    }
}

sendResponse(true, 'Registration details retrieved successfully', ['registration' => $registration], 200);

$stmt->close();
$conn->close();
?>
