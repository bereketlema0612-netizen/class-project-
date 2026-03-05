<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director', 'registration_admin'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$username = sanitizeInput($data['username'] ?? '');
$email = sanitizeInput($data['email'] ?? '');
$fname = sanitizeInput($data['fname'] ?? '');
$mname = sanitizeInput($data['mname'] ?? '');
$lname = sanitizeInput($data['lname'] ?? '');
$dob = sanitizeInput($data['DOB'] ?? '');
$age = (int)($data['age'] ?? 0);
$sex = sanitizeInput($data['sex'] ?? '');
$department = sanitizeInput($data['department'] ?? '');
$subject = sanitizeInput($data['subject'] ?? '');
$address = sanitizeInput($data['address'] ?? '');
$officeRoom = sanitizeInput($data['office_room'] ?? '');
$officePhone = sanitizeInput($data['office_phone'] ?? '');
$status = strtolower(trim((string)($data['status'] ?? '')));

if ($username === '' || $email === '' || $fname === '' || $lname === '' || $dob === '' || $age <= 0 || $sex === '' || $department === '' || $subject === '' || $address === '') {
    sendResponse(false, 'Required teacher fields are missing', null, 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Invalid email format', null, 400);
}
if ($status !== '' && !in_array($status, ['active', 'inactive'], true)) {
    sendResponse(false, 'Invalid status value', null, 400);
}

$checkStmt = $conn->prepare("SELECT username FROM teachers WHERE username = ? LIMIT 1");
$checkStmt->bind_param("s", $username);
$checkStmt->execute();
if (!$checkStmt->get_result()->fetch_assoc()) {
    sendResponse(false, 'Teacher not found', null, 404);
}

$dupStmt = $conn->prepare("SELECT username FROM users WHERE email = ? AND username <> ? LIMIT 1");
$dupStmt->bind_param("ss", $email, $username);
$dupStmt->execute();
if ($dupStmt->get_result()->fetch_assoc()) {
    sendResponse(false, 'Email already in use by another user', null, 409);
}

$conn->begin_transaction();
try {
    $userSql = "UPDATE users SET email = ?" . ($status !== '' ? ", status = ?" : "") . " WHERE username = ? AND role = 'teacher'";
    $userStmt = $conn->prepare($userSql);
    if ($status !== '') {
        $userStmt->bind_param("sss", $email, $status, $username);
    } else {
        $userStmt->bind_param("ss", $email, $username);
    }
    if (!$userStmt->execute()) {
        throw new Exception($userStmt->error);
    }

    $teacherStmt = $conn->prepare("
        UPDATE teachers
        SET fname = ?, mname = ?, lname = ?, DOB = ?, age = ?, sex = ?, department = ?, subject = ?, address = ?, office_room = ?, office_phone = ?
        WHERE username = ?
    ");
    $teacherStmt->bind_param("ssssisssssss", $fname, $mname, $lname, $dob, $age, $sex, $department, $subject, $address, $officeRoom, $officePhone, $username);
    if (!$teacherStmt->execute()) {
        throw new Exception($teacherStmt->error);
    }

    logSystemActivity($conn, $_SESSION['username'], 'UPDATE_TEACHER_PROFILE', 'Updated teacher profile ' . $username, 'success');
    $conn->commit();
    sendResponse(true, 'Teacher profile updated', ['username' => $username], 200);
} catch (Exception $e) {
    $conn->rollback();
    sendResponse(false, 'Failed to update teacher profile: ' . $e->getMessage(), null, 500);
}

$conn->close();
?>
