<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    sendResponse(false, 'Unauthorized', null, 403);
}

$hasContent = false;
$colRes = $conn->query("SHOW COLUMNS FROM announcements LIKE 'content'");
if ($colRes && $colRes->num_rows > 0) {
    $hasContent = true;
}
$contentExpr = $hasContent ? 'COALESCE(a.content, a.message)' : 'a.message';
$hasTargetUsers = false;
$colRes = $conn->query("SHOW COLUMNS FROM announcements LIKE 'target_usernames'");
if ($colRes && $colRes->num_rows > 0) {
    $hasTargetUsers = true;
}
$targetUsersExpr = $hasTargetUsers ? "COALESCE(a.target_usernames, '')" : "''";
$hasAttachmentPath = false;
$hasAttachmentName = false;
$hasAttachmentMime = false;
$hasAttachmentSize = false;
$colRes = $conn->query("SHOW COLUMNS FROM announcements LIKE 'attachment_path'");
if ($colRes && $colRes->num_rows > 0) {
    $hasAttachmentPath = true;
}
$colRes = $conn->query("SHOW COLUMNS FROM announcements LIKE 'attachment_name'");
if ($colRes && $colRes->num_rows > 0) {
    $hasAttachmentName = true;
}
$colRes = $conn->query("SHOW COLUMNS FROM announcements LIKE 'attachment_mime'");
if ($colRes && $colRes->num_rows > 0) {
    $hasAttachmentMime = true;
}
$colRes = $conn->query("SHOW COLUMNS FROM announcements LIKE 'attachment_size'");
if ($colRes && $colRes->num_rows > 0) {
    $hasAttachmentSize = true;
}
$attachmentPathExpr = $hasAttachmentPath ? 'a.attachment_path' : 'NULL';
$attachmentNameExpr = $hasAttachmentName ? 'a.attachment_name' : 'NULL';
$attachmentMimeExpr = $hasAttachmentMime ? 'a.attachment_mime' : 'NULL';
$attachmentSizeExpr = $hasAttachmentSize ? 'a.attachment_size' : 'NULL';

$conditions = ["a.audience = 'teachers'", "a.audience = 'all'", "a.created_by_username = ?"];
$types = 's';
$params = [$_SESSION['username']];
if ($hasTargetUsers) {
    $conditions[] = "(a.audience = 'individual' AND FIND_IN_SET(?, COALESCE(a.target_usernames, '')))";
    $types .= 's';
    $params[] = $_SESSION['username'];
}
$types .= 'ii';
$params[] = $limit;
$params[] = $offset;

$whereSql = implode(' OR ', $conditions);
$sql = "SELECT a.id, a.title, a.message, {$contentExpr} as content, a.priority, a.audience, {$targetUsersExpr} AS target_usernames, a.created_at, {$attachmentPathExpr} AS attachment_path, {$attachmentNameExpr} AS attachment_name, {$attachmentMimeExpr} AS attachment_mime, {$attachmentSizeExpr} AS attachment_size FROM announcements a WHERE ({$whereSql}) ORDER BY a.created_at DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    sendResponse(false, 'Failed to prepare announcements query: ' . $conn->error, null, 500);
}

$bindParams = [$types];
foreach ($params as $key => $value) {
    $bindParams[] = &$params[$key];
}
call_user_func_array([$stmt, 'bind_param'], $bindParams);

$stmt->execute();
$result = $stmt->get_result();

$announcements = [];
while ($row = $result->fetch_assoc()) {
    $announcements[] = $row;
}

$countConditions = ["audience = 'teachers'", "audience = 'all'", "created_by_username = ?"];
$countTypes = 's';
$countParams = [$_SESSION['username']];
if ($hasTargetUsers) {
    $countConditions[] = "(audience = 'individual' AND FIND_IN_SET(?, COALESCE(target_usernames, '')))";
    $countTypes .= 's';
    $countParams[] = $_SESSION['username'];
}
$countSql = "SELECT COUNT(*) as total FROM announcements WHERE " . implode(' OR ', $countConditions);
$countStmt = $conn->prepare($countSql);
if (!$countStmt) {
    sendResponse(false, 'Failed to prepare announcements count query: ' . $conn->error, null, 500);
}
$countBindParams = [$countTypes];
foreach ($countParams as $key => $value) {
    $countBindParams[] = &$countParams[$key];
}
call_user_func_array([$countStmt, 'bind_param'], $countBindParams);
$countStmt->execute();
$countResult = $countStmt->get_result();
$countRow = $countResult->fetch_assoc();
$totalRecords = $countRow['total'];
$totalPages = ceil($totalRecords / $limit);

sendResponse(true, 'Announcements retrieved successfully', [
    'announcements' => $announcements,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_records' => $totalRecords,
        'records_per_page' => $limit
    ]
], 200);

$stmt->close();
$countStmt->close();
$conn->close();

?>
