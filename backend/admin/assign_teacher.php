<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || (($_SESSION['role'] ?? '') !== 'admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST only', 'data' => null]);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS assigned_teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_username VARCHAR(50) NOT NULL,
    class_id INT NOT NULL,
    subject_name VARCHAR(100) NOT NULL
)");

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) $data = $_POST;

$teacher = trim((string)($data['teacher_username'] ?? ''));
$classId = (int)($data['class_id'] ?? 0);
$subject = trim((string)($data['subject_name'] ?? ''));

if ($teacher === '' || $classId === 0 || $subject === '') {
    echo json_encode(['success' => false, 'message' => 'teacher, class and subject required', 'data' => null]);
    exit;
}

$st = $conn->prepare("INSERT INTO assigned_teachers (teacher_username, class_id, subject_name) VALUES (?, ?, ?)");
if (!$st) {
    echo json_encode(['success' => false, 'message' => 'DB error', 'data' => null]);
    exit;
}
$st->bind_param('sis', $teacher, $classId, $subject);
$st->execute();

echo json_encode(['success' => true, 'message' => 'Assigned', 'data' => null]);
?>
