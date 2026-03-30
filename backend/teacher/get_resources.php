<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$teacher = (string)$_SESSION['username'];
$classId = (int)($_GET['class_id'] ?? 0);

$resources = [];
$tbl = $conn->query("SHOW TABLES LIKE 'resources'");
if ($tbl && $tbl->num_rows > 0) {
    if ($classId > 0) {
        $stmt = $conn->prepare("SELECT id, class_id, title, type, description, file_url, due_date, created_at FROM resources WHERE teacher_username = ? AND class_id = ? ORDER BY id DESC");
        if ($stmt) {
            $stmt->bind_param('si', $teacher, $classId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) $resources[] = $row;
            $stmt->close();
        }
    } else {
        $stmt = $conn->prepare("SELECT id, class_id, title, type, description, file_url, due_date, created_at FROM resources WHERE teacher_username = ? ORDER BY id DESC");
        if ($stmt) {
            $stmt->bind_param('s', $teacher);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) $resources[] = $row;
            $stmt->close();
        }
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Resources loaded',
    'data' => [
        'resources' => $resources,
        'total_resources' => count($resources)
    ]
]);
?>
