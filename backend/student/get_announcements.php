<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS class_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_username VARCHAR(50) NOT NULL,
    class_id INT NOT NULL,
    enrollment_date DATE NULL
)");
$conn->query("CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_username VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    class_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
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

$username = (string)$_SESSION['username'];
$classIds = [];
$stEnroll = $conn->prepare("SELECT class_id FROM class_enrollments WHERE student_username = ?");
if ($stEnroll) {
    $stEnroll->bind_param('s', $username);
    $stEnroll->execute();
    $resE = $stEnroll->get_result();
    while ($r = $resE->fetch_assoc()) {
        $cid = (int)($r['class_id'] ?? 0);
        if ($cid > 0) $classIds[] = $cid;
    }
    $stEnroll->close();
}

$items = [];

if (count($classIds) > 0) {
    $in = implode(',', array_map('intval', $classIds));
    $resA = $conn->query("SELECT id, title, message, created_at FROM announcements WHERE class_id IN ($in) OR class_id = 0 OR class_id IS NULL ORDER BY id DESC LIMIT 50");
} else {
    $resA = $conn->query("SELECT id, title, message, created_at FROM announcements WHERE class_id = 0 OR class_id IS NULL ORDER BY id DESC LIMIT 50");
}
if ($resA) {
    while ($row = $resA->fetch_assoc()) {
        $items[] = [
            'source' => 'teacher',
            'title' => (string)($row['title'] ?? ''),
            'message' => (string)($row['message'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? '')
        ];
    }
}

$resD = $conn->prepare("SELECT title, message, created_at FROM director_announcements WHERE send_to IN ('all','students') OR (send_to='individual_student' AND target_username = ?) ORDER BY id DESC LIMIT 50");
if ($resD) {
    $resD->bind_param('s', $username);
    $resD->execute();
    $rd = $resD->get_result();
    while ($row = $rd->fetch_assoc()) {
        $items[] = [
            'source' => 'director',
            'title' => (string)($row['title'] ?? ''),
            'message' => (string)($row['message'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? '')
        ];
    }
    $resD->close();
}

usort($items, function ($a, $b) {
    return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
});

echo json_encode([
    'success' => true,
    'message' => 'Announcements loaded',
    'data' => [
        'announcements' => array_slice($items, 0, 50),
        'total' => count($items)
    ]
]);
?>
