<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';
require_once '../helpers/resource_submission_tables.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username'])) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$sessionUsername = (string)$_SESSION['username'];
$sessionRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
$isTeacher = ($sessionRole === 'teacher');
if (!$isTeacher) {
    $roleStmt = $conn->prepare("SELECT role FROM users WHERE username = ? LIMIT 1");
    if ($roleStmt) {
        $roleStmt->bind_param('s', $sessionUsername);
        $roleStmt->execute();
        $row = $roleStmt->get_result()->fetch_assoc();
        $isTeacher = $row && strtolower((string)($row['role'] ?? '')) === 'teacher';
        $roleStmt->close();
    }
}
if (!$isTeacher) {
    sendResponse(false, 'Unauthorized', null, 403);
}

try {
    ensureAssignmentBlockColumn($conn);
    ensure_resource_submission_schema($conn);
} catch (Exception $e) {
    sendResponse(false, $e->getMessage(), null, 500);
}

$teacherUsername = $sessionUsername;
$title = trim((string)($_POST['title'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$resourceType = strtolower(trim((string)($_POST['resource_type'] ?? 'resource')));
$dueDateRaw = trim((string)($_POST['due_date'] ?? ''));
$targetMode = strtolower(trim((string)($_POST['target_mode'] ?? 'single')));
$classId = (int)($_POST['class_id'] ?? 0);
$classIds = parse_csv_ids(trim((string)($_POST['class_ids'] ?? '')));

if ($title === '') {
    sendResponse(false, 'Title is required', null, 400);
}
if (!isset($_FILES['resource_file']) || !is_array($_FILES['resource_file'])) {
    sendResponse(false, 'Resource file is required', null, 400);
}

$allowedTypes = ['resource', 'assignment', 'project', 'worksheet', 'book', 'pdf', 'other'];
if (!in_array($resourceType, $allowedTypes, true)) {
    $resourceType = 'resource';
}

if (!in_array($targetMode, ['single', 'multiple', 'all_assigned'], true)) {
    $targetMode = 'single';
}

$dueDate = null;
if ($dueDateRaw !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $dueDateRaw);
    if (!$dt || $dt->format('Y-m-d') !== $dueDateRaw) {
        sendResponse(false, 'Invalid due date format. Use YYYY-MM-DD.', null, 400);
    }
    $dueDate = $dueDateRaw;
}

$assignedClassIds = [];
$sql = "SELECT DISTINCT class_id FROM assignments WHERE teacher_username = ? AND assignment_type = 'teacher' AND is_blocked = 0";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    sendResponse(false, 'Failed to verify assigned classes: ' . $conn->error, null, 500);
}
$stmt->bind_param('s', $teacherUsername);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $cid = (int)($row['class_id'] ?? 0);
    if ($cid > 0) $assignedClassIds[$cid] = $cid;
}
$stmt->close();
$assignedClassIds = array_values($assignedClassIds);
if (count($assignedClassIds) === 0) {
    sendResponse(false, 'No assigned classes found for this teacher', null, 400);
}

$selectedClassIds = [];
if ($targetMode === 'all_assigned') {
    $selectedClassIds = $assignedClassIds;
} elseif ($targetMode === 'multiple') {
    $selectedClassIds = $classIds;
} else {
    if ($classId > 0) {
        $selectedClassIds = [$classId];
    } elseif (count($classIds) > 0) {
        $selectedClassIds = [(int)$classIds[0]];
    }
}
if (count($selectedClassIds) === 0) {
    sendResponse(false, 'Please select at least one class', null, 400);
}

$allowedLookup = array_flip($assignedClassIds);
$finalClassIds = [];
foreach ($selectedClassIds as $cid) {
    $cid = (int)$cid;
    if ($cid > 0 && isset($allowedLookup[$cid])) {
        $finalClassIds[$cid] = $cid;
    }
}
$finalClassIds = array_values($finalClassIds);
if (count($finalClassIds) === 0) {
    sendResponse(false, 'Selected classes are not assigned to this teacher', null, 403);
}

$file = $_FILES['resource_file'];
if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    sendResponse(false, 'Failed to upload file', null, 400);
}

$size = (int)($file['size'] ?? 0);
$maxBytes = 20 * 1024 * 1024;
if ($size <= 0 || $size > $maxBytes) {
    sendResponse(false, 'File size must be between 1 byte and 20 MB', null, 400);
}

$originalName = trim((string)($file['name'] ?? ''));
if ($originalName === '') {
    sendResponse(false, 'Invalid file name', null, 400);
}
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$safeBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
if ($safeBase === '' || $safeBase === null) {
    $safeBase = 'resource';
}

$uploadDir = dirname(__DIR__) . '/uploads/resources';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
    sendResponse(false, 'Failed to create resource upload directory', null, 500);
}
$storedName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeBase . ($ext !== '' ? '.' . $ext : '');
$targetPath = $uploadDir . '/' . $storedName;
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    sendResponse(false, 'Failed to save uploaded file', null, 500);
}

$dbPath = 'uploads/resources/' . $storedName;
$targetClassCsv = implode(',', $finalClassIds);
$targetModeDb = $targetMode === 'all_assigned' ? 'all_assigned' : (count($finalClassIds) > 1 ? 'multiple' : 'single');
$mime = (string)($file['type'] ?? 'application/octet-stream');

$insertSql = "
    INSERT INTO learning_resources
        (teacher_username, title, description, resource_type, due_date, target_mode, target_class_ids, file_path, file_name, file_mime, file_size, created_at, updated_at)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
";
$insertStmt = $conn->prepare($insertSql);
if (!$insertStmt) {
    sendResponse(false, 'Failed to prepare resource insert: ' . $conn->error, null, 500);
}
$insertStmt->bind_param(
    'ssssssssssi',
    $teacherUsername,
    $title,
    $description,
    $resourceType,
    $dueDate,
    $targetModeDb,
    $targetClassCsv,
    $dbPath,
    $originalName,
    $mime,
    $size
);
if (!$insertStmt->execute()) {
    sendResponse(false, 'Failed to save resource: ' . $insertStmt->error, null, 500);
}

$resourceId = (int)$insertStmt->insert_id;
$insertStmt->close();

logSystemActivity($conn, $teacherUsername, 'UPLOAD_RESOURCE', 'Teacher uploaded resource #' . $resourceId, 'success');

sendResponse(true, 'Resource uploaded successfully', [
    'resource_id' => $resourceId,
    'target_class_ids' => $targetClassCsv,
    'file_path' => $dbPath
], 201);

