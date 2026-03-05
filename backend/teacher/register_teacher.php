<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../helpers/functions.php';

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
$department = sanitizeInput($data['department'] ?? '');
$subject = sanitizeInput($data['subject'] ?? '');
$address = sanitizeInput($data['address'] ?? '');
$officeRoom = sanitizeInput($data['officeRoom'] ?? '');
$officePhone = sanitizeInput($data['officePhone'] ?? '');

if (!$fname || !$lname || !$email || !$dateOfBirth || !$age || !$sex || !$department || !$subject || !$address) {
    sendResponse(false, 'All required fields must be filled', null, 400);
}

if (!validateEmail($email)) {
    sendResponse(false, 'Invalid email format', null, 400);
}

if (checkEmailExists($email, $conn)) {
    sendResponse(false, 'Email already registered', null, 409);
}

if ($age < 21) {
    sendResponse(false, 'Teacher must be at least 21 years old', null, 400);
}

if ($officePhone && !preg_match('/^\+?[1-9]\d{1,14}$/', str_replace([' ', '-', '(', ')'], '', $officePhone))) {
    sendResponse(false, 'Invalid phone number format', null, 400);
}

$username = generateRoleUsername($conn, 'teacher');
$temporaryPassword = generateTemporaryPassword();
$passwordHash = hashPassword($temporaryPassword);

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status, approved_at, created_at) VALUES (?, ?, ?, 'teacher', 'active', NOW(), NOW())");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("sss", $username, $email, $passwordHash);
    
    if (!$stmt->execute()) {
        throw new Exception("User creation failed: " . $stmt->error);
    }
    
    $stmt = $conn->prepare("INSERT INTO teachers (username, fname, mname, lname, DOB, age, sex, department, subject, address, office_room, office_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("sssssissssss", $username, $fname, $mname, $lname, $dateOfBirth, $age, $sex, $department, $subject, $address, $officeRoom, $officePhone);
    
    if (!$stmt->execute()) {
        throw new Exception("Teacher record creation failed: " . $stmt->error);
    }
    
    $stmt = $conn->prepare("INSERT INTO registrations (username, role, status, submitted_at, submitted_by_admin, approved_at) VALUES (?, 'teacher', 'approved', NOW(), 0, NOW())");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $username);
    
    if (!$stmt->execute()) {
        throw new Exception("Registration record creation failed: " . $stmt->error);
    }
    
    $conn->commit();
    
    sendResponse(true, 'Teacher registration completed successfully', [
        'username' => $username,
        'email' => $email,
        'temporary_password' => $temporaryPassword,
        'message' => 'Teacher account created and activated.'
    ], 201);
    
} catch (Exception $e) {
    $conn->rollback();
    sendResponse(false, 'Registration failed: ' . $e->getMessage(), null, 500);
}

$stmt->close();
$conn->close();

?>
