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

$stmt = $conn->prepare("SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active') as total_admins,
    (SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'inactive') as inactive_admins
");

if (!$stmt->execute()) {
    sendResponse(false, 'Query failed: ' . $stmt->error, null, 500);
}

$result = $stmt->get_result();
$adminStats = $result->fetch_assoc();

$stmt = $conn->prepare("SELECT u.id, u.username, u.email, ra.fname, ra.lname, u.status, ra.created_at FROM users u JOIN admins ra ON u.username = ra.username ORDER BY ra.created_at DESC");

if (!$stmt->execute()) {
    sendResponse(false, 'Query failed: ' . $stmt->error, null, 500);
}

$result = $stmt->get_result();
$admins = [];
while ($row = $result->fetch_assoc()) {
    $row['full_name'] = $row['fname'] . ' ' . $row['lname'];
    $admins[] = $row;
}

sendResponse(true, 'Registration admins retrieved successfully', [
    'statistics' => $adminStats,
    'admins' => $admins
], 200);

$stmt->close();
$conn->close();
?>
