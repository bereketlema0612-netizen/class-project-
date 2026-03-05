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
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : null;

$query = "SELECT sl.id, sl.username, sl.action, sl.description, sl.status, sl.timestamp,
COALESCE(s.fname, t.fname, ra.fname, d.fname) as fname,
COALESCE(s.lname, t.lname, ra.lname, d.lname) as lname
FROM system_logs sl
LEFT JOIN users u ON sl.username = u.username
LEFT JOIN students s ON s.username = u.username
LEFT JOIN teachers t ON t.username = u.username
LEFT JOIN admins ra ON ra.username = u.username
LEFT JOIN directors d ON d.username = u.username
WHERE 1=1";

if ($action) {
    $query .= " AND sl.action = '" . $conn->real_escape_string($action) . "'";
}

$query .= " ORDER BY sl.timestamp DESC LIMIT " . $offset . ", " . $limit;

$result = $conn->query($query);

if (!$result) {
    sendResponse(false, 'Query failed: ' . $conn->error, null, 500);
}

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

$countQuery = "SELECT COUNT(*) as total FROM system_logs sl WHERE 1=1";
if ($action) {
    $countQuery .= " AND sl.action = '" . $conn->real_escape_string($action) . "'";
}

$countResult = $conn->query($countQuery);
$countRow = $countResult->fetch_assoc();
$totalRecords = $countRow['total'];
$totalPages = ceil($totalRecords / $limit);

sendResponse(true, 'System logs retrieved successfully', [
    'logs' => $logs,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_records' => $totalRecords,
        'records_per_page' => $limit
    ]
], 200);

$conn->close();
?>
