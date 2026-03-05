<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';
require_once '../helpers/resource_submission_tables.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$resourceId = (int)($_POST['resource_id'] ?? 0);
$notes = trim((string)($_POST['notes'] ?? ''));
if ($resourceId <= 0) {
    sendResponse(false, 'resource_id is required', null, 400);
}
if (!isset($_FILES['submission_file']) || !is_array($_FILES['submission_file'])) {
    sendResponse(false, 'Submission file is required', null, 400);
}

try {
    ensure_resource_submission_schema($conn);
} catch (Exception $e) {
    sendResponse(false, $e->getMessage(), null, 500);
}

$resourceStmt = $conn->prepare("
    SELECT id, teacher_username, target_class_ids, resource_type
    FROM learning_resources
    WHERE id = ?
    LIMIT 1
");
if (!$resourceStmt) {
    sendResponse(false, 'Failed to prepare resource query: ' . $conn->error, null, 500);
}
$resourceStmt->bind_param('i', $resourceId);
$resourceStmt->execute();
$resource = $resourceStmt->get_result()->fetch_assoc();
$resourceStmt->close();
if (!$resource) {
    sendResponse(false, 'Resource not found', null, 404);
}

$classStmt = $conn->prepare("SELECT class_id FROM class_enrollments WHERE student_username = ?");
if (!$classStmt) {
    sendResponse(false, 'Failed to verify student enrollment: ' . $conn->error, null, 500);
}
$classStmt->bind_param('s', $studentUsername);
$classStmt->execute();
$classRes = $classStmt->get_result();
$studentClassIds = [];
while ($row = $classRes->fetch_assoc()) {
    $cid = (int)($row['class_id'] ?? 0);
    if ($cid > 0) $studentClassIds[$cid] = $cid;
}
$classStmt->close();
$studentClassIds = array_values($studentClassIds);

if (!class_csv_visible_to_student((string)($resource['target_class_ids'] ?? ''), $studentClassIds)) {
    sendResponse(false, 'This resource is not assigned to your class', null, 403);
}

$resourceClassId = null;
$targets = parse_csv_ids((string)($resource['target_class_ids'] ?? ''));
if (count($targets) > 0) {
    $lookup = array_flip($studentClassIds);
    foreach ($targets as $cid) {
        if (isset($lookup[(int)$cid])) {
            $resourceClassId = (int)$cid;
            break;
        }
    }
}
if ($resourceClassId === null) {
    $resourceClassId = (count($studentClassIds) > 0) ? (int)$studentClassIds[0] : 0;
}

$file = $_FILES['submission_file'];
if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    sendResponse(false, 'Failed to upload submission file', null, 400);
}
$size = (int)($file['size'] ?? 0);
$maxBytes = 20 * 1024 * 1024;
if ($size <= 0 || $size > $maxBytes) {
    sendResponse(false, 'Submission file size must be between 1 byte and 20 MB', null, 400);
}
$originalName = trim((string)($file['name'] ?? ''));
if ($originalName === '') {
    sendResponse(false, 'Invalid file name', null, 400);
}
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$safeBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
if ($safeBase === '' || $safeBase === null) {
    $safeBase = 'submission';
}

$uploadDir = dirname(__DIR__) . '/uploads/submissions';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
    sendResponse(false, 'Failed to create submissions upload directory', null, 500);
}
$storedName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeBase . ($ext !== '' ? '.' . $ext : '');
$targetPath = $uploadDir . '/' . $storedName;
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    sendResponse(false, 'Failed to save submission file', null, 500);
}

$dbPath = 'uploads/submissions/' . $storedName;
$mime = (string)($file['type'] ?? 'application/octet-stream');
$teacherUsername = (string)$resource['teacher_username'];

// One active submission row per student/resource. Re-submit updates previous.
$upsert = $conn->prepare("
    INSERT INTO student_resource_submissions
        (resource_id, student_username, teacher_username, class_id, notes, file_path, file_name, file_mime, file_size, status, submitted_at, updated_at, seen_at)
    VALUES
        (?, ?, ?, NULLIF(?, 0), ?, ?, ?, ?, ?, 'submitted', NOW(), NOW(), NULL)
    ON DUPLICATE KEY UPDATE
        teacher_username = VALUES(teacher_username),
        class_id = VALUES(class_id),
        notes = VALUES(notes),
        file_path = VALUES(file_path),
        file_name = VALUES(file_name),
        file_mime = VALUES(file_mime),
        file_size = VALUES(file_size),
        status = 'submitted',
        seen_at = NULL,
        submitted_at = NOW(),
        updated_at = NOW()
");
if (!$upsert) {
    sendResponse(false, 'Failed to prepare submission insert: ' . $conn->error, null, 500);
}
$upsert->bind_param(
    'ississssi',
    $resourceId,
    $studentUsername,
    $teacherUsername,
    $resourceClassId,
    $notes,
    $dbPath,
    $originalName,
    $mime,
    $size
);
if (!$upsert->execute()) {
    sendResponse(false, 'Failed to save submission: ' . $upsert->error, null, 500);
}
$submissionId = (int)$conn->insert_id;
$upsert->close();

logSystemActivity($conn, $studentUsername, 'SUBMIT_ASSIGNMENT', 'Student submitted file for resource #' . $resourceId, 'success');

sendResponse(true, 'Submission uploaded successfully', [
    'submission_id' => $submissionId,
    'resource_id' => $resourceId,
    'file_path' => $dbPath
], 200);
