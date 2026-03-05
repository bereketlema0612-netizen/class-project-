<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username'])) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$sessionUsername = (string)$_SESSION['username'];
$sessionRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
$sessionRole = str_replace(['-', ' '], '_', $sessionRole);
$isAllowedRole = in_array($sessionRole, ['admin', 'director', 'registration_admin'], true);
if (!$isAllowedRole) {
    $isAdminTableUser = false;
    $tblAdmins = $conn->query("SHOW TABLES LIKE 'admins'");
    $tblDirectors = $conn->query("SHOW TABLES LIKE 'directors'");
    if (($tblAdmins && $tblAdmins->num_rows > 0) || ($tblDirectors && $tblDirectors->num_rows > 0)) {
        $authSqlParts = [];
        if ($tblAdmins && $tblAdmins->num_rows > 0) {
            $authSqlParts[] = "SELECT username FROM admins WHERE username = ?";
        }
        if ($tblDirectors && $tblDirectors->num_rows > 0) {
            $authSqlParts[] = "SELECT username FROM directors WHERE username = ?";
        }
        $authSql = implode(" UNION ", $authSqlParts) . " LIMIT 1";
        $authStmt = $conn->prepare($authSql);
        if ($authStmt) {
            if (count($authSqlParts) === 2) {
                $authStmt->bind_param("ss", $sessionUsername, $sessionUsername);
            } else {
                $authStmt->bind_param("s", $sessionUsername);
            }
            $authStmt->execute();
            $isAdminTableUser = (bool)$authStmt->get_result()->fetch_assoc();
        }
    }
    if (!$isAdminTableUser) {
        sendResponse(false, 'Unauthorized', null, 403);
    }
}

$statsSql = "SELECT
    (SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active') AS total_students,
    (SELECT COUNT(*) FROM users WHERE role = 'teacher' AND status = 'active') AS total_teachers,
    (SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active') AS total_admins,
    (SELECT COUNT(*) FROM registrations WHERE status = 'pending') AS total_pending,
    (SELECT COUNT(*) FROM registrations) AS total_registrations";

$statsResult = $conn->query($statsSql);
if (!$statsResult) {
    sendResponse(false, 'Failed to load dashboard stats: ' . $conn->error, null, 500);
}
$statistics = $statsResult->fetch_assoc();

$recentSql = "
    SELECT r.id, r.username, r.role, r.status, r.submitted_at, u.email,
           COALESCE(s.fname, t.fname, a.fname, d.fname) AS fname,
           COALESCE(s.mname, t.mname, a.mname, d.mname) AS mname,
           COALESCE(s.lname, t.lname, a.lname, d.lname) AS lname
    FROM registrations r
    JOIN users u ON r.username = u.username
    LEFT JOIN students s ON s.username = u.username
    LEFT JOIN teachers t ON t.username = u.username
    LEFT JOIN admins a ON a.username = u.username
    LEFT JOIN directors d ON d.username = u.username
    ORDER BY r.submitted_at DESC
    LIMIT 5
";
$recentResult = $conn->query($recentSql);
if (!$recentResult) {
    sendResponse(false, 'Failed to load recent registrations: ' . $conn->error, null, 500);
}

$recent = [];
while ($row = $recentResult->fetch_assoc()) {
    $row['full_name'] = trim($row['fname'] . ' ' . ($row['mname'] ? $row['mname'] . ' ' : '') . $row['lname']);
    $recent[] = $row;
}

$statusSql = "SELECT status, COUNT(*) AS count FROM registrations GROUP BY status";
$statusResult = $conn->query($statusSql);
if (!$statusResult) {
    sendResponse(false, 'Failed to load status summary: ' . $conn->error, null, 500);
}

$statusSummary = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
while ($row = $statusResult->fetch_assoc()) {
    $statusSummary[$row['status']] = (int)$row['count'];
}

sendResponse(true, 'Dashboard data retrieved', [
    'statistics' => $statistics,
    'recent_registrations' => $recent,
    'status_summary' => $statusSummary
], 200);

$conn->close();
?>
