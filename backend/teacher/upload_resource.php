<?php
require_once __DIR__ . '/common.php';
$teacher = require_teacher(true);

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

$title = trim((string)($_POST['title'] ?? $_POST['resource_title'] ?? ''));
$type = trim((string)($_POST['type'] ?? 'resource'));
$description = trim((string)($_POST['description'] ?? ''));
$dueDate = trim((string)($_POST['due_date'] ?? ''));
$classId = (int)($_POST['class_id'] ?? 0);

if ($title === '') {
    respond(false, 'Title required');
}

$file = save_uploaded_file('resource_file', __DIR__ . '/uploads/resources', 'backend/teacher/uploads/resources');
$url = (string)$file['url'];

$stmt = $conn->prepare("INSERT INTO resources
    (teacher_username, class_id, title, type, description, file_url, due_date)
    VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''))");
if (!$stmt) {
    respond(false, 'DB error');
}

$stmt->bind_param('sisssss', $teacher, $classId, $title, $type, $description, $url, $dueDate);
$stmt->execute();
$id = (int)$stmt->insert_id;
$stmt->close();

respond(true, 'Resource uploaded', [
    'id' => $id,
    'teacher_username' => $teacher,
    'class_id' => $classId,
    'title' => $title,
    'type' => $type,
    'description' => $description,
    'file_url' => $url,
    'due_date' => $dueDate
]);
?>
