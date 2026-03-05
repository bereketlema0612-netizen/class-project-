<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$role = isset($_GET['role']) ? sanitizeInput($_GET['role']) : null;
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'pending';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

$query = "SELECT r.id, r.username, r.role, r.status, r.submitted_at, u.email,
COALESCE(s.fname, t.fname, ra.fname, d.fname) as fname,
COALESCE(s.mname, t.mname, ra.mname, d.mname) as mname,
COALESCE(s.lname, t.lname, ra.lname, d.lname) as lname
FROM registrations r
JOIN users u ON r.username = u.username
LEFT JOIN students s ON s.username = u.username
LEFT JOIN teachers t ON t.username = u.username
LEFT JOIN admins ra ON ra.username = u.username
LEFT JOIN directors d ON d.username = u.username
WHERE 1=1";

if ($status && $status !== 'all') {
    $query .= " AND r.status = '" . $conn->real_escape_string($status) . "'";
}

if ($role && $role !== 'all') {
    $query .= " AND r.role = '" . $conn->real_escape_string($role) . "'";
}

$query .= " ORDER BY r.submitted_at DESC LIMIT " . $offset . ", " . $limit;

$result = $conn->query($query);

if (!$result) {
    sendResponse(false, 'Query failed: ' . $conn->error, null, 500);
}

$registrations = [];
while ($row = $result->fetch_assoc()) {
    $row['full_name'] = $row['fname'] . ' ' . ($row['mname'] ? $row['mname'] . ' ' : '') . $row['lname'];
    $registrations[] = $row;
}

$countQuery = "SELECT COUNT(*) as total FROM registrations r WHERE 1=1";
if ($status && $status !== 'all') {
    $countQuery .= " AND r.status = '" . $conn->real_escape_string($status) . "'";
}
if ($role && $role !== 'all') {
    $countQuery .= " AND r.role = '" . $conn->real_escape_string($role) . "'";
}

$countResult = $conn->query($countQuery);
$countRow = $countResult->fetch_assoc();
$totalRecords = $countRow['total'];
$totalPages = ceil($totalRecords / $limit);

sendResponse(true, 'Registrations retrieved successfully', [
    'registrations' => $registrations,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_records' => $totalRecords,
        'records_per_page' => $limit
    ]
], 200);

$conn->close();

?>
