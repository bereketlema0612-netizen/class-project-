<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST only', 'data' => null]);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_username VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    class_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

$title = '';
$message = '';
$classId = 0;

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $payload = json_decode(file_get_contents('php://input'), true);
    $title = trim((string)($payload['title'] ?? ''));
    $message = trim((string)($payload['message'] ?? $payload['content'] ?? ''));
    $classId = (int)($payload['class_id'] ?? 0);
} else {
    $title = trim((string)($_POST['title'] ?? ''));
    $message = trim((string)($_POST['message'] ?? $_POST['content'] ?? ''));
    $classId = (int)($_POST['class_id'] ?? 0);
}

if ($title === '' || $message === '') {
    echo json_encode(['success' => false, 'message' => 'Title and message required', 'data' => null]);
    exit;
}

$teacher = (string)$_SESSION['username'];
$stmt = $conn->prepare("INSERT INTO announcements (teacher_username, title, message, class_id) VALUES (?, ?, ?, ?)");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB error', 'data' => null]);
    exit;
}

$stmt->bind_param('sssi', $teacher, $title, $message, $classId);
$stmt->execute();
$id = (int)$stmt->insert_id;

echo json_encode([
    'success' => true,
    'message' => 'Announcement created',
    'data' => [
        'id' => $id,
        'teacher_username' => $teacher,
        'title' => $title,
        'message' => $message,
        'class_id' => $classId,
        'created_at' => date('Y-m-d H:i:s')
    ]
]);
?>
