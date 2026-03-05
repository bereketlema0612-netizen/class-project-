<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/curriculum.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$username = sanitizeInput($data['username'] ?? '');
$fname = sanitizeInput($data['fname'] ?? '');
$mname = sanitizeInput($data['mname'] ?? '');
$lname = sanitizeInput($data['lname'] ?? '');
$email = sanitizeInput($data['email'] ?? '');
$dob = sanitizeInput($data['DOB'] ?? '');
$age = (int)($data['age'] ?? 0);
$sex = sanitizeInput($data['sex'] ?? '');
$gradeLevelInput = sanitizeInput($data['grade_level'] ?? '');
$streamInput = sanitizeInput($data['stream'] ?? '');
$address = sanitizeInput($data['address'] ?? '');
$parentName = sanitizeInput($data['parent_name'] ?? '');
$parentPhone = sanitizeInput($data['parent_phone'] ?? '');

if ($username === '' || $fname === '' || $lname === '' || $email === '' || $dob === '' || $age <= 0 || $sex === '' || $gradeLevelInput === '' || $address === '' || $parentName === '' || $parentPhone === '') {
    sendResponse(false, 'All required fields must be filled', null, 400);
}
if (!validateEmail($email)) {
    sendResponse(false, 'Invalid email format', null, 400);
}
$gradeDigits = preg_replace('/\D+/', '', $gradeLevelInput);
if ($gradeDigits === '') {
    sendResponse(false, 'Invalid grade level', null, 400);
}
[$streamValid, $streamOrMessage] = validateStreamForGrade($gradeDigits, $streamInput);
if (!$streamValid) {
    sendResponse(false, $streamOrMessage, null, 400);
}
$stream = $streamOrMessage;

$dupStmt = $conn->prepare("SELECT username FROM users WHERE email = ? AND username <> ? LIMIT 1");
$dupStmt->bind_param("ss", $email, $username);
$dupStmt->execute();
if ($dupStmt->get_result()->num_rows > 0) {
    sendResponse(false, 'Email already registered by another user', null, 409);
}

$conn->begin_transaction();
try {
    $userStmt = $conn->prepare("UPDATE users SET email = ? WHERE username = ? AND role = 'student'");
    $userStmt->bind_param("ss", $email, $username);
    if (!$userStmt->execute()) {
        throw new Exception($userStmt->error);
    }

    $studentStmt = $conn->prepare("
        UPDATE students
        SET fname = ?, mname = ?, lname = ?, DOB = ?, age = ?, sex = ?, grade_level = ?, stream = NULLIF(?, ''), address = ?, parent_name = ?, parent_phone = ?
        WHERE username = ?
    ");
    $studentStmt->bind_param("ssssisssssss", $fname, $mname, $lname, $dob, $age, $sex, $gradeDigits, $stream, $address, $parentName, $parentPhone, $username);
    if (!$studentStmt->execute()) {
        throw new Exception($studentStmt->error);
    }

    logSystemActivity($conn, $_SESSION['username'], 'UPDATE_STUDENT', 'Updated student profile ' . $username, 'success');
    $conn->commit();
    sendResponse(true, 'Student profile updated successfully', ['username' => $username], 200);
} catch (Exception $e) {
    $conn->rollback();
    sendResponse(false, 'Update failed: ' . $e->getMessage(), null, 500);
}

$conn->close();
?>
