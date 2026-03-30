<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST only', 'data' => null]);
    exit;
}

require_once __DIR__ . '/../config/db_config.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON', 'data' => null]);
    exit;
}

$username = trim((string)($data['username'] ?? ''));
$password = (string)($data['password'] ?? '');

if ($username === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Username and password required', 'data' => null]);
    exit;
}

$stmt = $conn->prepare('SELECT id, username, email, password, role, status FROM users WHERE username = ? OR email = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB error', 'data' => null]);
    exit;
}

$stmt->bind_param('ss', $username, $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || (string)$user['status'] !== 'active') {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password', 'data' => null]);
    exit;
}

$stored = (string)$user['password'];
$ok = ($password === $stored) || password_verify($password, $stored);
if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password', 'data' => null]);
    exit;
}

session_start();
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['username'] = (string)$user['username'];
$_SESSION['role'] = (string)$user['role'];
$_SESSION['email'] = (string)$user['email'];

$u = (int)$user['id'];
$up = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
if ($up) {
    $up->bind_param('i', $u);
    $up->execute();
}

echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'data' => [
        'id' => (int)$user['id'],
        'username' => (string)$user['username'],
        'email' => (string)$user['email'],
        'role' => (string)$user['role'],
        'fullName' => (string)$user['username'],
        'session_id' => session_id()
    ]
]);
?>

