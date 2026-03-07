<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'director') {
    sendResponse(false, 'Unauthorized access', null, 403);
}

function columnExists(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND column_name = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, (int)($_GET['limit'] ?? 10));
$offset = ($page - 1) * $limit;

$hasTargetUsers = columnExists($conn, 'announcements', 'target_usernames');
$targetUsersExpr = $hasTargetUsers ? "COALESCE(a.target_usernames, '')" : "''";
$hasAttachmentPath = columnExists($conn, 'announcements', 'attachment_path');
$hasAttachmentName = columnExists($conn, 'announcements', 'attachment_name');
$attachmentPathExpr = $hasAttachmentPath ? "a.attachment_path" : "NULL";
$attachmentNameExpr = $hasAttachmentName ? "a.attachment_name" : "NULL";

$stmt = $conn->prepare("
    SELECT
        a.id, a.title, a.message, a.content, a.audience, COALESCE(a.target_mode, '') AS target_mode, {$targetUsersExpr} AS target_usernames,
        a.priority, a.created_by_username, a.created_at, {$attachmentPathExpr} AS attachment_path, {$attachmentNameExpr} AS attachment_name,
        COALESCE(s.fname, t.fname, ra.fname, d.fname) AS fname,
        COALESCE(s.lname, t.lname, ra.lname, d.lname) AS lname
    FROM announcements a
    LEFT JOIN users u ON a.created_by_username = u.username
    LEFT JOIN students s ON s.username = u.username
    LEFT JOIN teachers t ON t.username = u.username
    LEFT JOIN admins ra ON ra.username = u.username
    LEFT JOIN directors d ON d.username = u.username
    WHERE a.status = 'active'
    ORDER BY a.created_at DESC
    LIMIT ?, ?
");
if (!$stmt) {
    sendResponse(false, 'Failed to prepare announcements query: ' . $conn->error, null, 500);
}
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();

$announcements = [];
while ($row = $result->fetch_assoc()) {
    $targetLabel = (string)($row['audience'] ?? '-');
    if ($row['audience'] === 'admins') {
        $targetLabel = 'admins';
    } elseif ($row['audience'] === 'individual' && trim((string)($row['target_usernames'] ?? '')) !== '') {
        $targetUser = trim((string)$row['target_usernames']);
        $userStmt = $conn->prepare("
            SELECT username, role,
                   COALESCE(s.fname, t.fname, a.fname, d.fname) AS fname,
                   COALESCE(s.lname, t.lname, a.lname, d.lname) AS lname
            FROM users u
            LEFT JOIN students s ON s.username = u.username
            LEFT JOIN teachers t ON t.username = u.username
            LEFT JOIN admins a ON a.username = u.username
            LEFT JOIN directors d ON d.username = u.username
            WHERE u.username = ?
            LIMIT 1
        ");
        if ($userStmt) {
            $userStmt->bind_param("s", $targetUser);
            $userStmt->execute();
            $userRow = $userStmt->get_result()->fetch_assoc();
            if ($userRow) {
                $fullName = trim(((string)$userRow['fname']) . ' ' . ((string)$userRow['lname']));
                $targetLabel = ($fullName !== '' ? $fullName : $targetUser) . ' (' . (string)$userRow['role'] . ')';
            }
        }
    }
    $row['target_label'] = $targetLabel;
    $announcements[] = $row;
}

$countResult = $conn->query("SELECT COUNT(*) AS total FROM announcements WHERE status = 'active'");
$countRow = $countResult ? $countResult->fetch_assoc() : ['total' => 0];
$totalRecords = (int)($countRow['total'] ?? 0);
$totalPages = (int)ceil($totalRecords / $limit);

sendResponse(true, 'Announcements retrieved successfully', [
    'announcements' => $announcements,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_records' => $totalRecords,
        'records_per_page' => $limit
    ]
], 200);

$conn->close();
?>
