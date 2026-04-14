<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || (($_SESSION['role'] ?? '') !== 'admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    grade_level VARCHAR(20) NULL,
    section VARCHAR(20) NULL
)");

$check = $conn->query("SELECT COUNT(*) AS c FROM classes");
$count = $check ? (int)($check->fetch_assoc()['c'] ?? 0) : 0;
if ($count === 0) {
    $conn->query("INSERT INTO classes (name, grade_level, section) VALUES ('Grade 9 - A', '9', 'A')");
}

$classes = [];
$res = $conn->query("SELECT id, name, grade_level, section FROM classes ORDER BY id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $classes[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'grade_level' => (string)($row['grade_level'] ?? ''),
            'section' => (string)($row['section'] ?? '')
        ];
    }
}

echo json_encode(['success' => true, 'message' => 'Classes loaded', 'data' => ['classes' => $classes]]);
?>
