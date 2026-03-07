<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    sendResponse(false, 'Unauthorized', null, 403);
}

$studentUsername = (string)($_SESSION['username'] ?? '');
if ($studentUsername === '') {
    sendResponse(false, 'Unauthorized', null, 403);
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;
$hasContent = false;
$colRes = $conn->query("SHOW COLUMNS FROM announcements LIKE 'content'");
if ($colRes && $colRes->num_rows > 0) {
    $hasContent = true;
}
$contentExpr = $hasContent ? "COALESCE(a.content, a.message)" : "a.message";
$hasTargetClassIds = false;
$colRes = $conn->query("SHOW COLUMNS FROM announcements LIKE 'target_class_ids'");
if ($colRes && $colRes->num_rows > 0) {
    $hasTargetClassIds = true;
}
$targetClassExpr = $hasTargetClassIds ? "COALESCE(a.target_class_ids, '')" : "''";
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
$attachmentPathExpr = $hasAttachmentPath ? "a.attachment_path" : "NULL";
$attachmentNameExpr = $hasAttachmentName ? "a.attachment_name" : "NULL";
$attachmentMimeExpr = $hasAttachmentMime ? "a.attachment_mime" : "NULL";
$attachmentSizeExpr = $hasAttachmentSize ? "a.attachment_size" : "NULL";

$studentClassIds = [];
$classStmt = $conn->prepare("SELECT class_id FROM class_enrollments WHERE student_username = ?");
if ($classStmt) {
    $classStmt->bind_param("s", $studentUsername);
    $classStmt->execute();
    $classRes = $classStmt->get_result();
    while ($cr = $classRes->fetch_assoc()) {
        $cid = (int)($cr['class_id'] ?? 0);
        if ($cid > 0) {
            $studentClassIds[] = (string)$cid;
        }
    }
    $classStmt->close();
}

$stmt = $conn->prepare("
    SELECT 
        a.id, a.title, a.message, {$contentExpr} as content, a.audience, a.priority, a.created_at, {$targetClassExpr} as target_class_ids, {$targetUsersExpr} as target_usernames,
        {$attachmentPathExpr} AS attachment_path, {$attachmentNameExpr} AS attachment_name, {$attachmentMimeExpr} AS attachment_mime, {$attachmentSizeExpr} AS attachment_size
    FROM announcements a
    WHERE a.audience IN ('students', 'all', 'individual')
    ORDER BY a.created_at DESC
    LIMIT ? OFFSET ?
");
if (!$stmt) {
    sendResponse(false, 'Failed to prepare announcements query: ' . $conn->error, null, 500);
}
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$rawAnnouncements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$announcements = [];
foreach ($rawAnnouncements as $row) {
    $targetUsers = trim((string)($row['target_usernames'] ?? ''));
    if (strtolower((string)($row['audience'] ?? '')) === 'individual') {
        $userTargets = array_filter(array_map('trim', explode(',', $targetUsers)), static fn($v) => $v !== '');
        if (!in_array($studentUsername, $userTargets, true)) {
            continue;
        }
    }
    $targetCsv = trim((string)($row['target_class_ids'] ?? ''));
    if ($targetCsv === '') {
        $announcements[] = $row;
        continue;
    }
    $targets = array_filter(array_map('trim', explode(',', $targetCsv)), static fn($v) => $v !== '');
    $visible = count(array_intersect($targets, $studentClassIds)) > 0;
    if ($visible) {
        $announcements[] = $row;
    }
}

$totalRecords = count($announcements);
$totalPages = $limit > 0 ? (int)ceil($totalRecords / $limit) : 1;

sendResponse(true, 'Announcements retrieved', [
    'announcements' => $announcements,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_records' => $totalRecords,
        'records_per_page' => $limit
    ]
], 200);

$stmt->close();
$conn->close();
?>
