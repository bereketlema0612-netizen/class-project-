<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$teacher = (string)$_SESSION['username'];
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, (int)($_GET['limit'] ?? 20));
$offset = ($page - 1) * $limit;

$announcements = [];
$total = 0;

$tbl = $conn->query("SHOW TABLES LIKE 'announcements'");
if ($tbl && $tbl->num_rows > 0) {
    $colName = $conn->query("SHOW COLUMNS FROM announcements LIKE 'attachment_name'");
    if ($colName && $colName->num_rows === 0) {
        $conn->query("ALTER TABLE announcements ADD COLUMN attachment_name VARCHAR(255) NULL AFTER class_id");
    }
    $colUrl = $conn->query("SHOW COLUMNS FROM announcements LIKE 'attachment_url'");
    if ($colUrl && $colUrl->num_rows === 0) {
        $conn->query("ALTER TABLE announcements ADD COLUMN attachment_url VARCHAR(255) NULL AFTER attachment_name");
    }

    $cnt = $conn->prepare("SELECT COUNT(*) AS c FROM announcements WHERE teacher_username = ?");
    if ($cnt) {
        $cnt->bind_param('s', $teacher);
        $cnt->execute();
        $row = $cnt->get_result()->fetch_assoc();
        $total = (int)($row['c'] ?? 0);
        $cnt->close();
    }

    $sql = "SELECT id, title, message, class_id, attachment_name, attachment_url, created_at
            FROM announcements
            WHERE teacher_username = ?
            ORDER BY id DESC
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('sii', $teacher, $limit, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $announcements[] = $r;
        }
        $stmt->close();
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Announcements loaded',
    'data' => [
        'announcements' => $announcements,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total_records' => $total]
    ]
]);
?>
