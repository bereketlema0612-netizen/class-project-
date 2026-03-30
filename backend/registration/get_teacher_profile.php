<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'director'], true)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$username = trim((string)($_GET['username'] ?? ''));
if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'username is required', 'data' => null]);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    fname VARCHAR(50) NOT NULL,
    mname VARCHAR(50) NULL,
    lname VARCHAR(50) NOT NULL,
    date_of_birth DATE NULL,
    age INT NULL,
    sex VARCHAR(20) NULL,
    address TEXT NULL,
    department VARCHAR(100) NULL,
    subject VARCHAR(100) NULL,
    office_room VARCHAR(50) NULL,
    office_phone VARCHAR(50) NULL
)");
$teacherCols = [
    "mname VARCHAR(50) NULL",
    "date_of_birth DATE NULL",
    "age INT NULL",
    "sex VARCHAR(20) NULL",
    "address TEXT NULL",
    "department VARCHAR(100) NULL",
    "subject VARCHAR(100) NULL",
    "office_room VARCHAR(50) NULL",
    "office_phone VARCHAR(50) NULL"
];
foreach ($teacherCols as $colDef) {
    $col = explode(' ', $colDef)[0];
    $chk = $conn->query("SHOW COLUMNS FROM teachers LIKE '" . $conn->real_escape_string($col) . "'");
    if ($chk && $chk->num_rows === 0) $conn->query("ALTER TABLE teachers ADD COLUMN $colDef");
}

$sql = "SELECT u.username, u.email, u.status, t.fname, t.mname, t.lname, t.date_of_birth, t.age, t.sex, t.address, t.department, t.subject, t.office_room, t.office_phone
        FROM users u
        LEFT JOIN teachers t ON t.username = u.username
        WHERE u.username = ? AND u.role = 'teacher'
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
    echo json_encode(['success' => false, 'message' => 'Teacher not found', 'data' => null]);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Teacher profile loaded', 'data' => ['teacher' => $row]]);
?>
