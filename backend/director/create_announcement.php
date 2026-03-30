<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'director') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST only', 'data' => null]);
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

$payload = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) $payload = [];
} else {
    $payload = $_POST;
}

$title = trim((string)($payload['title'] ?? ''));
$message = trim((string)($payload['message'] ?? ''));
$sendTo = trim((string)($payload['send_to'] ?? $payload['sendTo'] ?? 'all'));
$targetUsername = trim((string)($payload['target_username'] ?? $payload['targetUsername'] ?? ''));
$priority = trim((string)($payload['priority'] ?? 'normal'));
$attachmentName = '';

if ($title === '' || $message === '') {
    echo json_encode(['success' => false, 'message' => 'Title and message required', 'data' => null]);
    exit;
}

if ($sendTo === '') $sendTo = 'all';
if ($priority === '') $priority = 'normal';

$director = (string)$_SESSION['username'];
$stmt = $conn->prepare("INSERT INTO director_announcements (director_username, title, message, send_to, target_username, priority, attachment_name) VALUES (?, ?, ?, ?, NULLIF(?, ''), ?, NULLIF(?, ''))");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB error', 'data' => null]);
    exit;
}

$stmt->bind_param('sssssss', $director, $title, $message, $sendTo, $targetUsername, $priority, $attachmentName);
$stmt->execute();
$id = (int)$stmt->insert_id;

echo json_encode([
    'success' => true,
    'message' => 'Announcement created',
    'data' => [
        'id' => $id,
        'director_username' => $director,
        'title' => $title,
        'message' => $message,
        'send_to' => $sendTo,
        'target_username' => $targetUsername,
        'priority' => $priority,
        'created_at' => date('Y-m-d H:i:s')
    ]
]);
?>
