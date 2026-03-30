<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'director'], true)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    teacher_username VARCHAR(50) NOT NULL,
    subject_name VARCHAR(100) NULL,
    assignment_type VARCHAR(20) NOT NULL DEFAULT 'teacher',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

$hasSubject = $conn->query("SHOW COLUMNS FROM assignments LIKE 'subject_name'");
if ($hasSubject && $hasSubject->num_rows === 0) {
    $conn->query("ALTER TABLE assignments ADD COLUMN subject_name VARCHAR(100) NULL AFTER teacher_username");
}

$conn->query("CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    grade_level VARCHAR(20) NULL,
    section VARCHAR(20) NULL
)");

$items = [];
$sql = "SELECT a.teacher_username, a.class_id, a.subject_name, c.name AS class_name
        FROM assignments a
        LEFT JOIN classes c ON c.id = a.class_id
        WHERE a.assignment_type = 'teacher' AND IFNULL(a.subject_name, '') <> ''
        ORDER BY a.id DESC";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'teacher_username' => (string)($row['teacher_username'] ?? ''),
            'class_id' => (int)($row['class_id'] ?? 0),
            'class_name' => (string)($row['class_name'] ?? ''),
            'subject_name' => (string)($row['subject_name'] ?? '')
        ];
    }
}

echo json_encode(['success' => true, 'message' => 'Assigned teachers loaded', 'data' => ['assigned_teachers' => $items]]);
?>
