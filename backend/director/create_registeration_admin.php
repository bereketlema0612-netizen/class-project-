<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'director') {
    sendResponse(false, 'Unauthorized access', null, 403);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$fname = sanitizeInput($data['fname'] ?? '');
$mname = sanitizeInput($data['mname'] ?? '');
$lname = sanitizeInput($data['lname'] ?? '');
$email = sanitizeInput($data['email'] ?? '');
$requestedRole = strtolower(sanitizeInput($data['role'] ?? 'admin'));
$inputPassword = $data['password'] ?? '';

if (!$fname || !$lname || !$email) {
    sendResponse(false, 'All required fields must be filled', null, 400);
}

if (!validateEmail($email)) {
    sendResponse(false, 'Invalid email format', null, 400);
}

if (checkEmailExists($email, $conn)) {
    sendResponse(false, 'Email already registered', null, 409);
}

$dbRole = $requestedRole === 'director' ? 'director' : 'admin';
$username = generateRoleUsername($conn, $dbRole);
$temporaryPassword = $inputPassword ?: generateTemporaryPassword();

if ($inputPassword) {
    $passwordValidation = validatePassword($inputPassword);
    if (!$passwordValidation['valid']) {
        sendResponse(false, $passwordValidation['message'], null, 400);
    }
}

$passwordHash = hashPassword($temporaryPassword);
$director_username = $_SESSION['username'];

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ssss", $username, $email, $passwordHash, $dbRole);
    
    if (!$stmt->execute()) {
        throw new Exception("User creation failed: " . $stmt->error);
    }
    
    
    if ($dbRole === 'admin') {
        $stmt = $conn->prepare("INSERT INTO admins (username, fname, mname, lname, created_at) VALUES (?, ?, ?, ?, NOW())");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssss", $username, $fname, $mname, $lname);
        
        if (!$stmt->execute()) {
            throw new Exception("Admin record creation failed: " . $stmt->error);
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO directors (username, fname, mname, lname, created_at) VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ssss", $username, $fname, $mname, $lname);
        if (!$stmt->execute()) {
            throw new Exception("Director record creation failed: " . $stmt->error);
        }
    }
    
    logSystemActivity($conn, $director_username, 'CREATE_ADMIN', 'Created new admin: ' . $fname . ' ' . $lname, 'success');
    
    $conn->commit();
    
    sendResponse(true, 'Registration admin created successfully', [
        'username' => $username,
        'email' => $email,
        'temporary_password' => $temporaryPassword
    ], 201);
    
} catch (Exception $e) {
    $conn->rollback();
    sendResponse(false, 'Admin creation failed: ' . $e->getMessage(), null, 500);
}

$stmt->close();
$conn->close();
?>
