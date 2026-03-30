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

$conn->query("CREATE TABLE IF NOT EXISTS resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_username VARCHAR(50) NOT NULL,
    class_id INT NULL,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) NULL,
    description TEXT NULL,
    file_url VARCHAR(255) NULL,
    due_date DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

$teacher = (string)$_SESSION['username'];
$title = trim((string)($_POST['title'] ?? $_POST['resource_title'] ?? ''));
$type = trim((string)($_POST['type'] ?? 'resource'));
$description = trim((string)($_POST['description'] ?? ''));
$dueDate = trim((string)($_POST['due_date'] ?? ''));
$classId = (int)($_POST['class_id'] ?? 0);

if ($title === '') {
    echo json_encode(['success' => false, 'message' => 'Title required', 'data' => null]);
    exit;
}

$fileUrl = '';
if (isset($_FILES['resource_file']) && is_array($_FILES['resource_file']) && ($_FILES['resource_file']['error'] ?? 1) === 0) {
    $uploadDir = __DIR__ . '/uploads/resources';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $name = basename((string)$_FILES['resource_file']['name']);
    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    $target = $uploadDir . '/' . time() . '_' . $safeName;

    if (@move_uploaded_file($_FILES['resource_file']['tmp_name'], $target)) {
        $fileUrl = 'backend/teacher/uploads/resources/' . basename($target);
    }
}

$stmt = $conn->prepare("INSERT INTO resources (teacher_username, class_id, title, type, description, file_url, due_date) VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''))");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB error', 'data' => null]);
    exit;
}

$stmt->bind_param('sisssss', $teacher, $classId, $title, $type, $description, $fileUrl, $dueDate);
$stmt->execute();
$id = (int)$stmt->insert_id;

echo json_encode([
    'success' => true,
    'message' => 'Resource uploaded',
    'data' => [
        'id' => $id,
        'teacher_username' => $teacher,
        'class_id' => $classId,
        'title' => $title,
        'type' => $type,
        'description' => $description,
        'file_url' => $fileUrl,
        'due_date' => $dueDate
    ]
]);
?>
