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

$username = sanitizeInput($data['username'] ?? '');
$action = sanitizeInput($data['action'] ?? '');

if (!$username || !$action) {
    sendResponse(false, 'Username and action required', null, 400);
}

if (!in_array($action, ['activate', 'deactivate'])) {
    sendResponse(false, 'Invalid action', null, 400);
}

$director_username = $_SESSION['username'];

$newStatus = ($action === 'activate') ? 'active' : 'inactive';

$stmt = $conn->prepare("UPDATE users SET status = ? WHERE username = ?");
$stmt->bind_param("ss", $newStatus, $username);

if (!$stmt->execute()) {
    sendResponse(false, 'Action failed: ' . $stmt->error, null, 500);
}

logSystemActivity($conn, $director_username, 'USER_' . strtoupper($action), 'Changed user status to ' . $newStatus, 'success');

sendResponse(true, 'User status updated successfully', [
    'username' => $username,
    'new_status' => $newStatus
], 200);

$stmt->close();
$conn->close();
?>
