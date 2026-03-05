<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'director') {
    sendResponse(false, 'Unauthorized access', null, 403);
}

$director_username = $_SESSION['username'];

$stmt = $conn->prepare("SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active') as total_students,
    (SELECT COUNT(*) FROM users WHERE role = 'teacher' AND status = 'active') as total_teachers,
    (SELECT COUNT(*) FROM registrations) as total_registrations,
    (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
    (SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active') as active_admins,
    (SELECT COUNT(*) FROM announcements WHERE status = 'active') as active_announcements
");
if (!$stmt) {
    sendResponse(false, 'Query prepare failed: ' . $conn->error, null, 500);
}

if (!$stmt->execute()) {
    sendResponse(false, 'Query failed: ' . $stmt->error, null, 500);
}

$result = $stmt->get_result();
$stats = $result->fetch_assoc();

$stmt = $conn->prepare("SELECT u.status, COUNT(*) as count FROM users u GROUP BY u.status");
if (!$stmt) {
    sendResponse(false, 'Query prepare failed: ' . $conn->error, null, 500);
}
if (!$stmt->execute()) {
    sendResponse(false, 'Query failed: ' . $stmt->error, null, 500);
}

$statusResult = $stmt->get_result();
$statusBreakdown = [];
while ($row = $statusResult->fetch_assoc()) {
    $statusBreakdown[$row['status']] = (int)$row['count'];
}

$stmt = $conn->prepare("SELECT s.grade_level, COUNT(*) as count FROM students s JOIN users u ON s.username = u.username WHERE u.status = 'active' GROUP BY s.grade_level ORDER BY s.grade_level");
if (!$stmt) {
    sendResponse(false, 'Query prepare failed: ' . $conn->error, null, 500);
}
if (!$stmt->execute()) {
    sendResponse(false, 'Query failed: ' . $stmt->error, null, 500);
}

$gradeResult = $stmt->get_result();
$gradeDistribution = [];
while ($row = $gradeResult->fetch_assoc()) {
    $gradeDistribution[] = $row;
}

$hasAdminsTable = false;
$tableCheck = $conn->query("SHOW TABLES LIKE 'admins'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $hasAdminsTable = true;
}

$logsSql = $hasAdminsTable
    ? "SELECT sl.action, sl.description, sl.status, sl.timestamp,
              COALESCE(s.fname, t.fname, ra.fname, d.fname) as fname,
              COALESCE(s.lname, t.lname, ra.lname, d.lname) as lname
       FROM system_logs sl
       LEFT JOIN users u ON sl.username = u.username
       LEFT JOIN students s ON s.username = u.username
       LEFT JOIN teachers t ON t.username = u.username
       LEFT JOIN admins ra ON ra.username = u.username
       LEFT JOIN directors d ON d.username = u.username
       ORDER BY sl.timestamp DESC LIMIT 10"
    : "SELECT sl.action, sl.description, sl.status, sl.timestamp,
              COALESCE(s.fname, t.fname, d.fname) as fname,
              COALESCE(s.lname, t.lname, d.lname) as lname
       FROM system_logs sl
       LEFT JOIN users u ON sl.username = u.username
       LEFT JOIN students s ON s.username = u.username
       LEFT JOIN teachers t ON t.username = u.username
       LEFT JOIN directors d ON d.username = u.username
       ORDER BY sl.timestamp DESC LIMIT 10";

$stmt = $conn->prepare($logsSql);
if (!$stmt) {
    sendResponse(false, 'Query prepare failed: ' . $conn->error, null, 500);
}
if (!$stmt->execute()) {
    sendResponse(false, 'Query failed: ' . $stmt->error, null, 500);
}

$logResult = $stmt->get_result();
$recentActivity = [];
while ($row = $logResult->fetch_assoc()) {
    $recentActivity[] = $row;
}

$stmt = $conn->prepare("SELECT id, title, audience, priority, created_at FROM announcements WHERE status = 'active' ORDER BY created_at DESC LIMIT 5");
if (!$stmt) {
    sendResponse(false, 'Query prepare failed: ' . $conn->error, null, 500);
}
if (!$stmt->execute()) {
    sendResponse(false, 'Query failed: ' . $stmt->error, null, 500);
}

$announcementResult = $stmt->get_result();
$recentAnnouncements = [];
while ($row = $announcementResult->fetch_assoc()) {
    $recentAnnouncements[] = $row;
}

logSystemActivity($conn, $director_username, 'VIEW_DASHBOARD', 'Viewed dashboard overview', 'success');

sendResponse(true, 'Dashboard data retrieved successfully', [
    'statistics' => $stats,
    'status_breakdown' => $statusBreakdown,
    'grade_distribution' => $gradeDistribution,
    'recent_activity' => $recentActivity,
    'recent_announcements' => $recentAnnouncements
], 200);

$stmt->close();
$conn->close();
?>
