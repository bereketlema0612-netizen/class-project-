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
    attachment_name VARCHAR(255) NULL,
    attachment_url VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

$colName = $conn->query("SHOW COLUMNS FROM announcements LIKE 'attachment_name'");
if ($colName && $colName->num_rows === 0) {
    $conn->query("ALTER TABLE announcements ADD COLUMN attachment_name VARCHAR(255) NULL AFTER class_id");
}
$colUrl = $conn->query("SHOW COLUMNS FROM announcements LIKE 'attachment_url'");
if ($colUrl && $colUrl->num_rows === 0) {
    $conn->query("ALTER TABLE announcements ADD COLUMN attachment_url VARCHAR(255) NULL AFTER attachment_name");
}

$title = '';
$message = '';
$classId = 0;
$attachmentName = '';
$attachmentUrl = '';

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

$fileField = null;
if (isset($_FILES['announcement_file']) && is_array($_FILES['announcement_file'])) $fileField = $_FILES['announcement_file'];
if ($fileField === null && isset($_FILES['attachment']) && is_array($_FILES['attachment'])) $fileField = $_FILES['attachment'];

if ($fileField && (int)($fileField['error'] ?? 1) === 0) {
    $uploadDir = __DIR__ . '/uploads/announcements';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);

    $original = basename((string)($fileField['name'] ?? ''));
    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $original);
    $newName = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . $safe;
    $target = $uploadDir . '/' . $newName;

    if (!@move_uploaded_file((string)$fileField['tmp_name'], $target)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file', 'data' => null]);
        exit;
    }

    $attachmentName = $original;
    $attachmentUrl = 'backend/teacher/uploads/announcements/' . $newName;
}

$teacher = (string)$_SESSION['username'];
$stmt = $conn->prepare("INSERT INTO announcements (teacher_username, title, message, class_id, attachment_name, attachment_url) VALUES (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''))");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB error', 'data' => null]);
    exit;
}

$stmt->bind_param('sssiss', $teacher, $title, $message, $classId, $attachmentName, $attachmentUrl);
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
        'attachment_name' => $attachmentName,
        'attachment_url' => $attachmentUrl,
        'created_at' => date('Y-m-d H:i:s')
    ]
]);
?>
