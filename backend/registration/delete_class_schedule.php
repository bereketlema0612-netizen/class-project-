<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director', 'registration_admin'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$scheduleId = (int)($data['schedule_id'] ?? 0);
if ($scheduleId <= 0) {
    sendResponse(false, 'Schedule ID is required', null, 400);
}

$stmt = $conn->prepare("DELETE FROM class_schedules WHERE id = ?");
if (!$stmt) {
    sendResponse(false, 'Failed to prepare schedule delete: ' . $conn->error, null, 500);
}
$stmt->bind_param("i", $scheduleId);
if (!$stmt->execute()) {
    sendResponse(false, 'Failed to delete schedule: ' . $stmt->error, null, 500);
}

if ($stmt->affected_rows <= 0) {
    sendResponse(false, 'Schedule not found', null, 404);
}

sendResponse(true, 'Class schedule deleted successfully', ['schedule_id' => $scheduleId], 200);

$conn->close();
?>
