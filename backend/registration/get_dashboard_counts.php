<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'director'], true)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$totalStudents = 0;
$totalTeachers = 0;
$totalAdmins = 0;
$totalRegistrations = 0;

$r = $conn->query("SELECT COUNT(*) c FROM users WHERE role='student'");
if ($r) $totalStudents = (int)($r->fetch_assoc()['c'] ?? 0);
$r = $conn->query("SELECT COUNT(*) c FROM users WHERE role='teacher'");
if ($r) $totalTeachers = (int)($r->fetch_assoc()['c'] ?? 0);
$r = $conn->query("SELECT COUNT(*) c FROM users WHERE role='admin'");
if ($r) $totalAdmins = (int)($r->fetch_assoc()['c'] ?? 0);
$r = $conn->query("SELECT COUNT(*) c FROM users");
if ($r) $totalRegistrations = (int)($r->fetch_assoc()['c'] ?? 0);

echo json_encode([
    'success' => true,
    'message' => 'Dashboard counts loaded',
    'data' => [
        'total_students' => $totalStudents,
        'total_teachers' => $totalTeachers,
        'total_admins' => $totalAdmins,
        'total_registrations' => $totalRegistrations
    ]
]);
?>
