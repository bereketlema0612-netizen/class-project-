<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'director') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST only', 'data' => null]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) $data = $_POST;

$username = trim((string)($data['username'] ?? ''));
$status = trim((string)($data['status'] ?? ''));

if ($username === '' || !in_array($status, ['active', 'inactive'], true)) {
    echo json_encode(['success' => false, 'message' => 'username and valid status required', 'data' => null]);
    exit;
}

if ($username === (string)$_SESSION['username']) {
    echo json_encode(['success' => false, 'message' => 'Director cannot deactivate own account', 'data' => null]);
    exit;
}

$st = $conn->prepare("UPDATE users SET status = ? WHERE username = ?");
if (!$st) {
    echo json_encode(['success' => false, 'message' => 'DB error', 'data' => null]);
    exit;
}
$st->bind_param('ss', $status, $username);
$st->execute();

echo json_encode(['success' => true, 'message' => 'User status updated', 'data' => ['username' => $username, 'status' => $status]]);
?>
