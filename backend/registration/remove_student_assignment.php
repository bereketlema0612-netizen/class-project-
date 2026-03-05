<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$assignmentId = (int)($data['assignment_id'] ?? 0);
if (!$assignmentId) {
    sendResponse(false, 'Assignment ID required', null, 400);
}

$stmt = $conn->prepare("DELETE FROM class_enrollments WHERE id = ?");
$stmt->bind_param("i", $assignmentId);
if (!$stmt->execute()) {
    sendResponse(false, 'Failed to remove assignment: ' . $stmt->error, null, 500);
}

logSystemActivity($conn, $_SESSION['username'], 'REMOVE_STUDENT_ASSIGNMENT', 'Removed class enrollment ID ' . $assignmentId, 'success');
sendResponse(true, 'Student assignment removed', ['assignment_id' => $assignmentId], 200);

$conn->close();
?>
