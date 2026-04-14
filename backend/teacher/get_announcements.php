<?php
require_once __DIR__ . '/common.php';
$teacher = require_teacher(false);

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, (int)($_GET['limit'] ?? 20));
$offset = ($page - 1) * $limit;

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

$total = 0;
$c = $conn->prepare("SELECT COUNT(*) AS c FROM announcements WHERE teacher_username = ?");
if ($c) {
    $c->bind_param('s', $teacher);
    $c->execute();
    $row = $c->get_result()->fetch_assoc();
    $total = (int)($row['c'] ?? 0);
    $c->close();
}

$items = [];
$stmt = $conn->prepare("SELECT id, title, message, class_id, attachment_name, attachment_url, created_at
    FROM announcements
    WHERE teacher_username = ?
    ORDER BY id DESC
    LIMIT ? OFFSET ?");

if ($stmt) {
    $stmt->bind_param('sii', $teacher, $limit, $offset);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
}

respond(true, 'Announcements loaded', [
    'announcements' => $items,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total_records' => $total
    ]
]);
?>
