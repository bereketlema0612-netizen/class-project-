<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$subjects = [];
$classId = (int)($_GET['class_id'] ?? 0);
$teacher = (string)$_SESSION['username'];

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

if ($classId > 0) {
    $stmt = $conn->prepare("SELECT DISTINCT subject_name FROM assignments WHERE assignment_type = 'teacher' AND teacher_username = ? AND class_id = ? AND IFNULL(subject_name, '') <> '' ORDER BY subject_name ASC");
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
    $stmt = $conn->prepare("SELECT DISTINCT subject_name FROM assignments WHERE assignment_type = 'teacher' AND teacher_username = ? AND IFNULL(subject_name, '') <> '' ORDER BY subject_name ASC");
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

$tblSubjects = $conn->query("SHOW TABLES LIKE 'subjects'");
if (count($subjects) === 0 && $tblSubjects && $tblSubjects->num_rows > 0) {
    $res = $conn->query("SELECT subject_name FROM subjects ORDER BY subject_name ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $name = trim((string)($row['subject_name'] ?? ''));
            if ($name !== '') $subjects[] = $name;
        }
    }
}

if (count($subjects) === 0) {
    $subjects = ['Mathematics', 'English', 'Biology'];
}

echo json_encode([
    'success' => true,
    'message' => 'Class subjects loaded',
    'data' => [
        'subjects' => $subjects
    ]
]);
?>
