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

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("SELECT a.id, a.title, a.message, a.audience, a.priority, a.created_by_username, a.created_at,
COALESCE(s.fname, t.fname, ra.fname, d.fname) as fname,
COALESCE(s.lname, t.lname, ra.lname, d.lname) as lname
FROM announcements a
LEFT JOIN users u ON a.created_by_username = u.username
LEFT JOIN students s ON s.username = u.username
LEFT JOIN teachers t ON t.username = u.username
LEFT JOIN admins ra ON ra.username = u.username
LEFT JOIN directors d ON d.username = u.username
WHERE a.status = 'active' ORDER BY a.created_at DESC LIMIT ?, ?");
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();

$announcements = [];
while ($row = $result->fetch_assoc()) {
    $announcements[] = $row;
}

$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM announcements WHERE status = 'active'");
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
