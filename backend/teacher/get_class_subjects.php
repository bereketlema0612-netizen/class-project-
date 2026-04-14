<?php
require_once __DIR__ . '/common.php';
$teacher = require_teacher(false);

$classId = (int)($_GET['class_id'] ?? 0);
$subjects = [];

$conn->query("CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    teacher_username VARCHAR(50) NOT NULL,
    subject_name VARCHAR(100) NULL,
    assignment_type VARCHAR(20) NOT NULL DEFAULT 'teacher',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

$has = $conn->query("SHOW COLUMNS FROM assignments LIKE 'subject_name'");
if ($has && $has->num_rows === 0) {
    $conn->query("ALTER TABLE assignments ADD COLUMN subject_name VARCHAR(100) NULL AFTER teacher_username");
}

if ($classId > 0) {
    $stmt = $conn->prepare("SELECT DISTINCT subject_name FROM assignments
        WHERE assignment_type = 'teacher' AND teacher_username = ? AND class_id = ? AND IFNULL(subject_name,'') <> ''
        ORDER BY subject_name");
    if ($stmt) {
        $stmt->bind_param('si', $teacher, $classId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $name = trim((string)($row['subject_name'] ?? ''));
            if ($name !== '') $subjects[] = $name;
        }
        $stmt->close();
    }
}

if (count($subjects) === 0) {
    $stmt = $conn->prepare("SELECT DISTINCT subject_name FROM assignments
        WHERE assignment_type = 'teacher' AND teacher_username = ? AND IFNULL(subject_name,'') <> ''
        ORDER BY subject_name");
    if ($stmt) {
        $stmt->bind_param('s', $teacher);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $name = trim((string)($row['subject_name'] ?? ''));
            if ($name !== '') $subjects[] = $name;
        }
        $stmt->close();
    }
}

if (count($subjects) === 0) {
    $subjects = ['Mathematics', 'English', 'Biology'];
}

respond(true, 'Class subjects loaded', ['subjects' => $subjects]);
?>
