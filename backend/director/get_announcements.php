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

$director = (string)$_SESSION['username'];
$limit = max(1, (int)($_GET['limit'] ?? 50));

$items = [];
$stmt = $conn->prepare("SELECT id, title, message, send_to, target_username, priority, created_at FROM director_announcements WHERE director_username = ? ORDER BY id DESC LIMIT ?");
if ($stmt) {
    $stmt->bind_param('si', $director, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $items[] = $row;
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'message' => 'Announcements loaded',
    'data' => ['announcements' => $items]
]);
?>
