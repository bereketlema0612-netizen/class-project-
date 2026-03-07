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
if (!is_array($data)) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$id = (int)($data['id'] ?? 0);
if ($id <= 0) {
    sendResponse(false, 'Announcement id is required', null, 400);
}

$stmt = $conn->prepare("UPDATE announcements SET status = 'archived', updated_at = NOW() WHERE id = ?");
if (!$stmt) {
    sendResponse(false, 'Prepare failed: ' . $conn->error, null, 500);
}
$stmt->bind_param("i", $id);
if (!$stmt->execute()) {
    sendResponse(false, 'Delete failed: ' . $stmt->error, null, 500);
}

sendResponse(true, 'Announcement deleted', ['id' => $id], 200);
$conn->close();
?>
