<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'director'], true)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST only', 'data' => null]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON', 'data' => null]);
    exit;
}

$teacher = trim((string)($data['teacher_username'] ?? ''));
$classId = (int)($data['class_id'] ?? 0);
$subjectName = trim((string)($data['subject_name'] ?? ''));

if ($teacher === '' || $classId <= 0 || $subjectName === '') {
    echo json_encode(['success' => false, 'message' => 'teacher_username, class_id and subject_name required', 'data' => null]);
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

$del = $conn->prepare("DELETE FROM assignments WHERE class_id = ? AND teacher_username = ? AND assignment_type = 'teacher' AND IFNULL(subject_name, '') = ?");
if ($del) {
    $del->bind_param('iss', $classId, $teacher, $subjectName);
    $del->execute();
    $del->close();
}

$stmt = $conn->prepare("INSERT INTO assignments (class_id, teacher_username, subject_name, assignment_type) VALUES (?, ?, ?, 'teacher')");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB error', 'data' => null]);
    exit;
}

$stmt->bind_param('iss', $classId, $teacher, $subjectName);
$stmt->execute();

echo json_encode([
    'success' => true,
    'message' => 'Teacher assigned',
    'data' => ['teacher_username' => $teacher, 'class_id' => $classId, 'subject_name' => $subjectName]
]);
?>
