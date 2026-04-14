<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || (($_SESSION['role'] ?? '') !== 'admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    fname VARCHAR(50) NOT NULL,
    mname VARCHAR(50) NULL,
    lname VARCHAR(50) NOT NULL,
    grade_level VARCHAR(20) NULL
)");

$grade = trim((string)($_GET['grade'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));

$sql = "SELECT s.username, s.fname, s.lname, s.grade_level, u.status
        FROM students s
        LEFT JOIN users u ON u.username = s.username
        WHERE 1=1";
$types = '';
$params = [];
if ($grade !== '') {
    $sql .= " AND s.grade_level = ?";
    $types .= 's';
    $params[] = $grade;
}
if ($search !== '') {
    $sql .= " AND (s.username LIKE ? OR s.fname LIKE ? OR s.lname LIKE ?)";
    $types .= 'sss';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$sql .= " ORDER BY s.id DESC";

$items = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'username' => (string)($row['username'] ?? ''),
            'full_name' => trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? '')),
            'grade_level' => (string)($row['grade_level'] ?? ''),
            'status' => (string)($row['status'] ?? 'active')
        ];
    }
    $stmt->close();
}

echo json_encode(['success' => true, 'message' => 'Student records loaded', 'data' => ['students' => $items]]);
?>
