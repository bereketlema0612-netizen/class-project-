<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || (($_SESSION['role'] ?? '') !== 'admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$stats = [
    'total_students' => 0,
    'total_teachers' => 0,
    'total_registrations' => 0,
    'total_approved' => 0,
    'total_admins' => 0,
    'total_announcements' => 0
];

$r = $conn->query("SELECT COUNT(*) c FROM users WHERE role='student'");
if ($r) $stats['total_students'] = (int)($r->fetch_assoc()['c'] ?? 0);
$r = $conn->query("SELECT COUNT(*) c FROM users WHERE role='teacher'");
if ($r) $stats['total_teachers'] = (int)($r->fetch_assoc()['c'] ?? 0);
$r = $conn->query("SELECT COUNT(*) c FROM users");
if ($r) $stats['total_registrations'] = (int)($r->fetch_assoc()['c'] ?? 0);
$r = $conn->query("SELECT COUNT(*) c FROM users WHERE status='active'");
if ($r) $stats['total_approved'] = (int)($r->fetch_assoc()['c'] ?? 0);
$r = $conn->query("SELECT COUNT(*) c FROM users WHERE role='admin'");
if ($r) $stats['total_admins'] = (int)($r->fetch_assoc()['c'] ?? 0);

$stats['total_announcements'] = 0;

echo json_encode(['success' => true, 'message' => 'Dashboard stats loaded', 'data' => $stats]);
?>
