<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/curriculum.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$fname = sanitizeInput($data['fname'] ?? '');
$mname = sanitizeInput($data['mname'] ?? '');
$lname = sanitizeInput($data['lname'] ?? '');
$email = sanitizeInput($data['email'] ?? '');
$dateOfBirth = $data['dateOfBirth'] ?? '';
$age = (int)($data['age'] ?? 0);
$sex = sanitizeInput($data['sex'] ?? '');
$gradeLevel = sanitizeInput($data['gradeLevel'] ?? '');
$streamInput = sanitizeInput($data['stream'] ?? '');
$address = sanitizeInput($data['address'] ?? '');
$parentName = sanitizeInput($data['parentName'] ?? '');
$parentPhone = sanitizeInput($data['parentPhone'] ?? '');

if (!$fname || !$lname || !$email || !$dateOfBirth || !$age || !$sex || !$gradeLevel || !$address || !$parentName || !$parentPhone) {
    sendResponse(false, 'All required fields must be filled', null, 400);
}

if (!validateEmail($email)) {
    sendResponse(false, 'Invalid email format', null, 400);
}

if (checkEmailExists($email, $conn)) {
    sendResponse(false, 'Email already registered', null, 409);
}

if ($age < 10 || $age > 25) {
    sendResponse(false, 'Student age must be between 10 and 25', null, 400);
}

if (!preg_match('/^\+?[1-9]\d{1,14}$/', str_replace([' ', '-', '(', ')'], '', $parentPhone))) {
    sendResponse(false, 'Invalid phone number format', null, 400);
}

$gradeDigits = preg_replace('/\D+/', '', (string)$gradeLevel);
if ($gradeDigits === '') {
    sendResponse(false, 'Invalid grade level', null, 400);
}
[$streamValid, $streamOrMessage] = validateStreamForGrade($gradeDigits, $streamInput);
if (!$streamValid) {
    sendResponse(false, $streamOrMessage, null, 400);
}
$stream = $streamOrMessage;

$username = generateRoleUsername($conn, 'student');
$temporaryPassword = generateTemporaryPassword();
$passwordHash = hashPassword($temporaryPassword);

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status, approved_at, created_at) VALUES (?, ?, ?, 'student', 'active', NOW(), NOW())");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("sss", $username, $email, $passwordHash);
    
    if (!$stmt->execute()) {
        throw new Exception("User creation failed: " . $stmt->error);
    }
    
    $stmt = $conn->prepare("INSERT INTO students (username, fname, mname, lname, DOB, age, sex, grade_level, stream, address, parent_name, parent_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("sssssissssss", $username, $fname, $mname, $lname, $dateOfBirth, $age, $sex, $gradeDigits, $stream, $address, $parentName, $parentPhone);
    
    if (!$stmt->execute()) {
        throw new Exception("Student record creation failed: " . $stmt->error);
    }
    
    $stmt = $conn->prepare("INSERT INTO registrations (username, role, status, submitted_at, submitted_by_admin, approved_at) VALUES (?, 'student', 'approved', NOW(), 0, NOW())");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $username);
    
    if (!$stmt->execute()) {
        throw new Exception("Registration record creation failed: " . $stmt->error);
    }
    
    $conn->commit();
    
    sendResponse(true, 'Student registration completed successfully', [
        'username' => $username,
        'email' => $email,
        'temporary_password' => $temporaryPassword,
        'message' => 'Student account created and activated.'
    ], 201);
    
} catch (Exception $e) {
    $conn->rollback();
    sendResponse(false, 'Registration failed: ' . $e->getMessage(), null, 500);
}

$stmt->close();
$conn->close();

?>
