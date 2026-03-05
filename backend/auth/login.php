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

$username = sanitizeInput($data['username'] ?? '');
$password = $data['password'] ?? '';

if (!$username || !$password) {
    sendResponse(false, 'Username and password required', null, 400);
}

$passwordColumn = 'password';
$colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'password'");
if (!$colCheck || $colCheck->num_rows === 0) {
    $hashCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'password_hash'");
    if ($hashCheck && $hashCheck->num_rows > 0) {
        $passwordColumn = 'password_hash';
    }
}

$sql = "SELECT id, username, email, {$passwordColumn} AS password_value, role, status FROM users WHERE username = ? OR email = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    sendResponse(false, 'Login query prepare failed: ' . $conn->error, null, 500);
}
$stmt->bind_param("ss", $username, $username);
if (!$stmt->execute()) {
    sendResponse(false, 'Login query failed: ' . $stmt->error, null, 500);
}
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    sendResponse(false, 'Invalid credentials', null, 401);
}

$user = $result->fetch_assoc();

if ($user['status'] !== 'active') {
    sendResponse(false, 'Account is not active. Status: ' . $user['status'], null, 403);
}

if (!verifyPassword($password, $user['password_value'])) {
    sendResponse(false, 'Invalid credentials', null, 401);
}

session_start();
// Normalize registration_admin to admin for both UI and backend endpoint role checks.
$responseRole = $user['role'] === 'registration_admin' ? 'admin' : $user['role'];
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $responseRole;
$_SESSION['email'] = $user['email'];

$stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
}

logSystemActivity($conn, $user['username'], 'LOGIN', 'User logged in', 'success');

$hasAdminsTable = false;
$tableCheck = $conn->query("SHOW TABLES LIKE 'admins'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $hasAdminsTable = true;
}

$nameSql = $hasAdminsTable
    ? "SELECT
            COALESCE(s.fname, t.fname, ra.fname, d.fname) AS fname,
            COALESCE(s.lname, t.lname, ra.lname, d.lname) AS lname
       FROM users u
       LEFT JOIN students s ON s.username = u.username
       LEFT JOIN teachers t ON t.username = u.username
       LEFT JOIN admins ra ON ra.username = u.username
       LEFT JOIN directors d ON d.username = u.username
       WHERE u.id = ?"
    : "SELECT
            COALESCE(s.fname, t.fname, d.fname) AS fname,
            COALESCE(s.lname, t.lname, d.lname) AS lname
       FROM users u
       LEFT JOIN students s ON s.username = u.username
       LEFT JOIN teachers t ON t.username = u.username
       LEFT JOIN directors d ON d.username = u.username
       WHERE u.id = ?";

$name = ['fname' => '', 'lname' => ''];
$nameStmt = $conn->prepare($nameSql);
if ($nameStmt) {
    $nameStmt->bind_param("i", $user['id']);
    if ($nameStmt->execute()) {
        $fetched = $nameStmt->get_result()->fetch_assoc();
        if (is_array($fetched)) {
            $name = $fetched;
        }
    }
}

$userData = [
    'id' => $user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'role' => $responseRole,
    'fullName' => trim(($name['fname'] ?? '') . ' ' . ($name['lname'] ?? '')) ?: $user['username'],
    'session_id' => session_id()
];

sendResponse(true, 'Login successful', $userData, 200);

$stmt->close();
$conn->close();

?>
