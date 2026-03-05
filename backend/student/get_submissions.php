<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';
require_once '../helpers/resource_submission_tables.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    sendResponse(false, 'Unauthorized', null, 403);
}

$studentUsername = (string)($_SESSION['username'] ?? '');
if ($studentUsername === '') {
    sendResponse(false, 'Unauthorized', null, 403);
}

try {
    ensure_resource_submission_schema($conn);
} catch (Exception $e) {
    sendResponse(false, $e->getMessage(), null, 500);
}

$stmt = $conn->prepare("
    SELECT
        srs.id, srs.resource_id, srs.notes, srs.file_path, srs.file_name, srs.file_mime, srs.file_size,
        srs.status, srs.submitted_at, srs.updated_at, srs.seen_at,
        lr.title AS resource_title, lr.resource_type, lr.due_date,
        t.fname AS teacher_fname, t.lname AS teacher_lname
    FROM student_resource_submissions srs
    JOIN learning_resources lr ON lr.id = srs.resource_id
    LEFT JOIN teachers t ON t.username = srs.teacher_username
    WHERE srs.student_username = ?
    ORDER BY srs.submitted_at DESC
");
if (!$stmt) {
    sendResponse(false, 'Failed to prepare submissions query: ' . $conn->error, null, 500);
}
$stmt->bind_param('s', $studentUsername);
$stmt->execute();
$res = $stmt->get_result();
$submissions = [];
while ($row = $res->fetch_assoc()) {
    $row['teacher_name'] = trim(((string)($row['teacher_fname'] ?? '')) . ' ' . ((string)($row['teacher_lname'] ?? '')));
    unset($row['teacher_fname'], $row['teacher_lname']);
    $submissions[] = $row;
}
$stmt->close();

sendResponse(true, 'Submissions retrieved successfully', [
    'submissions' => $submissions,
    'count' => count($submissions)
], 200);

