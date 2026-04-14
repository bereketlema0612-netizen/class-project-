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
if ($announcementId <= 0) {
    respond(false, 'Id required');
}

$del = $conn->prepare("DELETE FROM announcements WHERE id = ? AND teacher_username = ?");
if (!$del) {
    respond(false, 'DB error');
}
$del->bind_param('is', $announcementId, $teacher);
$del->execute();
$affected = $del->affected_rows;
$del->close();

if ($affected <= 0) {
    respond(false, 'Announcement not found');
}

respond(true, 'Announcement deleted', ['id' => $announcementId]);
?>
