<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';
require_once '../helpers/resource_submission_tables.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'teacher') {
    sendResponse(false, 'Unauthorized', null, 403);
}

$teacherUsername = (string)$_SESSION['username'];
$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
if (!is_array($json)) {
    sendResponse(false, 'Invalid JSON payload', null, 400);
}
$submissionId = (int)($json['submission_id'] ?? 0);
if ($submissionId <= 0) {
    sendResponse(false, 'submission_id is required', null, 400);
}

try {
    ensure_resource_submission_schema($conn);
} catch (Exception $e) {
    sendResponse(false, $e->getMessage(), null, 500);
}

$stmt = $conn->prepare("SELECT id FROM student_resource_submissions WHERE id = ? AND teacher_username = ? LIMIT 1");
if (!$stmt) {
    sendResponse(false, 'Failed to prepare lookup: ' . $conn->error, null, 500);
}
$stmt->bind_param('is', $submissionId, $teacherUsername);
$stmt->execute();
$found = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$found) {
    sendResponse(false, 'Submission not found', null, 404);
}

$update = $conn->prepare("
    UPDATE student_resource_submissions
    SET status = CASE WHEN status = 'graded' THEN 'graded' ELSE 'seen' END,
        seen_at = NOW(),
        updated_at = NOW()
    WHERE id = ? AND teacher_username = ?
");
if (!$update) {
    sendResponse(false, 'Failed to prepare update: ' . $conn->error, null, 500);
}
$update->bind_param('is', $submissionId, $teacherUsername);
if (!$update->execute()) {
    sendResponse(false, 'Failed to update submission status: ' . $update->error, null, 500);
}
$update->close();

sendResponse(true, 'Submission marked as seen', ['submission_id' => $submissionId], 200);

