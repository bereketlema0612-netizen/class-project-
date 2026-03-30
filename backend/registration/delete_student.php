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

$username = trim((string)($data['username'] ?? ''));
if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'username is required', 'data' => null]);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS students (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE)");
$conn->query("CREATE TABLE IF NOT EXISTS class_enrollments (id INT AUTO_INCREMENT PRIMARY KEY, student_username VARCHAR(50) NOT NULL, class_id INT NOT NULL)");
$conn->query("CREATE TABLE IF NOT EXISTS grades (id INT AUTO_INCREMENT PRIMARY KEY, student_username VARCHAR(50) NOT NULL)");

$safe = $conn->real_escape_string($username);
$conn->query("DELETE FROM students WHERE username = '$safe'");
$conn->query("DELETE FROM class_enrollments WHERE student_username = '$safe'");
$conn->query("DELETE FROM grades WHERE student_username = '$safe'");
$u = $conn->prepare("DELETE FROM users WHERE username = ? AND role = 'student'");
if ($u) {
    $u->bind_param('s', $username);
    $u->execute();
}

echo json_encode(['success' => true, 'message' => 'Student deleted', 'data' => ['username' => $username]]);
?>
