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

$data = $_POST;
if (!is_array($data) || count($data) === 0) {
    $data = read_json_body();
}

$announcementId = (int)($data['announcement_id'] ?? $data['id'] ?? 0);
$title = trim((string)($data['title'] ?? ''));
$message = trim((string)($data['message'] ?? $data['content'] ?? ''));
$classId = (int)($data['class_id'] ?? 0);
$removeFile = (int)($data['remove_file'] ?? 0);

if ($announcementId <= 0 || $title === '' || $message === '') {
    respond(false, 'Id, title and message required');
}

$check = $conn->prepare("SELECT attachment_name, attachment_url FROM announcements WHERE id = ? AND teacher_username = ? LIMIT 1");
if (!$check) {
    respond(false, 'DB error');
}
$check->bind_param('is', $announcementId, $teacher);
$check->execute();
$current = $check->get_result()->fetch_assoc();
$check->close();

if (!$current) {
    respond(false, 'Announcement not found');
}

$attachmentName = (string)($current['attachment_name'] ?? '');
$attachmentUrl = (string)($current['attachment_url'] ?? '');

if ($removeFile === 1) {
    $attachmentName = '';
    $attachmentUrl = '';
}

$file = save_uploaded_file('announcement_file', __DIR__ . '/uploads/announcements', 'backend/teacher/uploads/announcements');
if ($file['url'] !== '') {
    $attachmentName = (string)$file['name'];
    $attachmentUrl = (string)$file['url'];
}

$update = $conn->prepare("UPDATE announcements
    SET title = ?, message = ?, class_id = ?, attachment_name = NULLIF(?, ''), attachment_url = NULLIF(?, '')
    WHERE id = ? AND teacher_username = ?");
if (!$update) {
    respond(false, 'DB error');
}
$update->bind_param('ssissis', $title, $message, $classId, $attachmentName, $attachmentUrl, $announcementId, $teacher);
$update->execute();
$update->close();

respond(true, 'Announcement updated', [
    'id' => $announcementId,
    'title' => $title,
    'message' => $message,
    'class_id' => $classId,
    'attachment_name' => $attachmentName,
    'attachment_url' => $attachmentUrl
]);
?>
