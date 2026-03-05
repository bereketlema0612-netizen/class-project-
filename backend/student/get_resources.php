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

$classIds = [];
$classStmt = $conn->prepare("SELECT class_id FROM class_enrollments WHERE student_username = ?");
if ($classStmt) {
    $classStmt->bind_param('s', $studentUsername);
    $classStmt->execute();
    $res = $classStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $cid = (int)($row['class_id'] ?? 0);
        if ($cid > 0) $classIds[$cid] = $cid;
    }
    $classStmt->close();
}
$classIds = array_values($classIds);

$sql = "
    SELECT
        lr.id, lr.teacher_username, lr.title, lr.description, lr.resource_type, lr.due_date,
        lr.target_mode, lr.target_class_ids, lr.file_path, lr.file_name, lr.file_mime, lr.file_size, lr.created_at,
        t.fname AS teacher_fname, t.lname AS teacher_lname
    FROM learning_resources lr
    LEFT JOIN teachers t ON t.username = lr.teacher_username
    ORDER BY lr.created_at DESC
";

$res = $conn->query($sql);
if (!$res) {
    sendResponse(false, 'Failed to retrieve resources: ' . $conn->error, null, 500);
}

$resources = [];
while ($row = $res->fetch_assoc()) {
    if (!class_csv_visible_to_student((string)($row['target_class_ids'] ?? ''), $classIds)) {
        continue;
    }
    $row['teacher_name'] = trim(((string)($row['teacher_fname'] ?? '')) . ' ' . ((string)($row['teacher_lname'] ?? '')));
    unset($row['teacher_fname'], $row['teacher_lname']);
    $resources[] = $row;
}

sendResponse(true, 'Resources retrieved successfully', [
    'resources' => $resources,
    'count' => count($resources)
], 200);

