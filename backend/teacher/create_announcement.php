<?php
require_once __DIR__ . '/common.php';
$teacher = require_teacher(true);

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

$title = trim((string)($_POST['title'] ?? ''));
$message = trim((string)($_POST['message'] ?? $_POST['content'] ?? ''));
$classId = (int)($_POST['class_id'] ?? 0);

if ($title === '' || $message === '') {
    respond(false, 'Title and message required');
}

$file = save_uploaded_file('announcement_file', __DIR__ . '/uploads/announcements', 'backend/teacher/uploads/announcements');
if ($file['url'] === '' && isset($_FILES['attachment'])) {
    $file = save_uploaded_file('attachment', __DIR__ . '/uploads/announcements', 'backend/teacher/uploads/announcements');
}

$stmt = $conn->prepare("INSERT INTO announcements
    (teacher_username, title, message, class_id, attachment_name, attachment_url)
    VALUES (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''))");

if (!$stmt) {
    respond(false, 'DB error');
}

$an = (string)$file['name'];
$au = (string)$file['url'];
$stmt->bind_param('sssiss', $teacher, $title, $message, $classId, $an, $au);
$stmt->execute();
$id = (int)$stmt->insert_id;
$stmt->close();

respond(true, 'Announcement created', [
    'id' => $id,
    'teacher_username' => $teacher,
    'title' => $title,
    'message' => $message,
    'class_id' => $classId,
    'attachment_name' => $an,
    'attachment_url' => $au,
    'created_at' => date('Y-m-d H:i:s')
]);
?>
