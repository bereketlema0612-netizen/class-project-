<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    sendResponse(false, 'Unauthorized', null, 403);
}

$studentUsername = $_SESSION['username'];

$stmt = $conn->prepare("
    SELECT 
        u.id, u.username, u.email,
        s.username as student_username, s.fname, s.mname, s.lname,
        s.DOB, s.age, s.sex, s.grade_level, s.address, 
        s.parent_name, s.parent_phone
    FROM users u
    JOIN students s ON u.username = s.username
    WHERE u.username = ?
");
$stmt->bind_param("s", $studentUsername);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    sendResponse(false, 'Student profile not found', null, 404);
}

$profile = [
    'user_id' => $student['id'],
    'student_id' => $student['student_username'],
    'username' => $student['username'],
    'first_name' => $student['fname'],
    'middle_name' => $student['mname'],
    'last_name' => $student['lname'],
    'full_name' => $student['fname'] . ' ' . ($student['mname'] ? $student['mname'] . ' ' : '') . $student['lname'],
    'email' => $student['email'],
    'date_of_birth' => $student['DOB'],
    'age' => $student['age'],
    'sex' => $student['sex'],
    'grade_level' => $student['grade_level'],
    'address' => $student['address'],
    'parent_name' => $student['parent_name'],
    'parent_phone' => $student['parent_phone']
];

sendResponse(true, 'Student profile retrieved', ['profile' => $profile], 200);

$stmt->close();
$conn->close();
?>
