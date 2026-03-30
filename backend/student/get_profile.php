<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    fname VARCHAR(50) NOT NULL,
    mname VARCHAR(50) NULL,
    lname VARCHAR(50) NOT NULL,
    date_of_birth DATE NULL,
    age INT NULL,
    sex VARCHAR(20) NULL,
    grade_level VARCHAR(20) NULL,
    stream VARCHAR(20) NULL,
    address TEXT NULL,
    parent_name VARCHAR(120) NULL,
    parent_phone VARCHAR(50) NULL
)");

$studentCols = [
    "mname VARCHAR(50) NULL",
    "date_of_birth DATE NULL",
    "age INT NULL",
    "sex VARCHAR(20) NULL",
    "grade_level VARCHAR(20) NULL",
    "stream VARCHAR(20) NULL",
    "address TEXT NULL",
    "parent_name VARCHAR(120) NULL",
    "parent_phone VARCHAR(50) NULL"
];
foreach ($studentCols as $colDef) {
    $colName = explode(' ', $colDef)[0];
    $chk = $conn->query("SHOW COLUMNS FROM students LIKE '" . $conn->real_escape_string($colName) . "'");
    if ($chk && $chk->num_rows === 0) {
        $conn->query("ALTER TABLE students ADD COLUMN $colDef");
    }
}

$username = (string)$_SESSION['username'];
$sql = "SELECT u.username, u.email, s.fname, s.mname, s.lname, s.date_of_birth, s.age, s.sex, s.grade_level, s.stream, s.address, s.parent_name, s.parent_phone
        FROM users u
        LEFT JOIN students s ON s.username = u.username
        WHERE u.username = ? AND u.role = 'student'
        LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB error', 'data' => null]);
    exit;
}
$stmt->bind_param('s', $username);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Student not found', 'data' => null]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Student profile loaded',
    'data' => ['student' => $row]
]);
?>
