<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

function send_json($success, $message, $data = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(false, 'POST only');
}

$input = json_decode(file_get_contents('php://input'), true);
$username = trim((string)($input['username'] ?? ''));
$password = (string)($input['password'] ?? '');

if ($username === '' || $password === '') {
    send_json(false, 'Username and password are required');
}

$stmt = $conn->prepare('SELECT id, username, email, password, role FROM users WHERE username = ? LIMIT 1');
if (!$stmt) {
    send_json(false, 'Database error');
}

$stmt->bind_param('s', $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    send_json(false, 'Invalid username or password');
}

$storedPassword = (string)$user['password'];
$isValid = ($password === $storedPassword) || password_verify($password, $storedPassword);

if (!$isValid) {
    send_json(false, 'Invalid username or password');
}

session_start();
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['username'] = (string)$user['username'];
$_SESSION['role'] = (string)$user['role'];

$uid = (int)$user['id'];
$update = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
if ($update) {
    $update->bind_param('i', $uid);
    $update->execute();
}

send_json(true, 'Login successful', [
    'id' => (int)$user['id'],
    'username' => (string)$user['username'],
    'email' => (string)$user['email'],
    'role' => (string)$user['role']
]);
?>
