<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'director') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS director_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    director_username VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    send_to VARCHAR(40) NOT NULL DEFAULT 'all',
    target_username VARCHAR(50) NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'normal',
    attachment_name VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

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
$r = $conn->query("SELECT COUNT(*) c FROM director_announcements");
if ($r) $stats['total_announcements'] = (int)($r->fetch_assoc()['c'] ?? 0);

echo json_encode(['success' => true, 'message' => 'Dashboard stats loaded', 'data' => $stats]);
?>
