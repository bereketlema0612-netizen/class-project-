<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';
require_once '../helpers/resource_submission_tables.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$teacherUsername = (string)$_SESSION['username'];
$role = strtolower((string)$_SESSION['role']);
if ($role !== 'teacher') {
    sendResponse(false, 'Unauthorized', null, 403);
}

try {
    ensure_resource_submission_schema($conn);
} catch (Exception $e) {
    sendResponse(false, $e->getMessage(), null, 500);
}

$classId = (int)($_GET['class_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

$where = "WHERE lr.teacher_username = ?";
$params = [$teacherUsername];
$types = 's';

if ($classId > 0) {
    $where .= " AND FIND_IN_SET(?, lr.target_class_ids)";
    $types .= 'i';
    $params[] = $classId;
}

$sql = "
    SELECT
        lr.id, lr.title, lr.description, lr.resource_type, lr.due_date, lr.target_mode, lr.target_class_ids,
        lr.file_path, lr.file_name, lr.file_mime, lr.file_size, lr.created_at, lr.updated_at,
        t.fname AS teacher_fname, t.lname AS teacher_lname
    FROM learning_resources lr
    LEFT JOIN teachers t ON t.username = lr.teacher_username
    {$where}
    ORDER BY lr.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    sendResponse(false, 'Failed to prepare resources query: ' . $conn->error, null, 500);
}
$typesWithPage = $types . 'ii';
$params[] = $limit;
$params[] = $offset;
$stmt->bind_param($typesWithPage, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$resources = [];
while ($row = $res->fetch_assoc()) {
    $row['teacher_name'] = trim(((string)($row['teacher_fname'] ?? '')) . ' ' . ((string)($row['teacher_lname'] ?? '')));
    unset($row['teacher_fname'], $row['teacher_lname']);
    $resources[] = $row;
}
$stmt->close();

$countSql = "SELECT COUNT(*) AS total FROM learning_resources lr {$where}";
$countStmt = $conn->prepare($countSql);
if (!$countStmt) {
    sendResponse(false, 'Failed to prepare resource count query: ' . $conn->error, null, 500);
}
if ($classId > 0) {
    $countStmt->bind_param('si', $teacherUsername, $classId);
} else {
    $countStmt->bind_param('s', $teacherUsername);
}
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

sendResponse(true, 'Resources retrieved successfully', [
    'resources' => $resources,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $limit > 0 ? (int)ceil($total / $limit) : 1,
        'total_records' => $total,
        'records_per_page' => $limit
    ]
], 200);

