<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'director'], true)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    fname VARCHAR(50) NOT NULL,
    mname VARCHAR(50) NULL,
    lname VARCHAR(50) NOT NULL,
    department VARCHAR(100) NULL
)");
$teacherCols = ["mname VARCHAR(50) NULL", "department VARCHAR(100) NULL"];
foreach ($teacherCols as $colDef) {
    $col = explode(' ', $colDef)[0];
    $chk = $conn->query("SHOW COLUMNS FROM teachers LIKE '" . $conn->real_escape_string($col) . "'");
    if ($chk && $chk->num_rows === 0) $conn->query("ALTER TABLE teachers ADD COLUMN $colDef");
}

$teachers = [];
$sql = "SELECT t.username, t.fname, t.lname, t.department FROM teachers t ORDER BY t.fname, t.lname";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $teachers[] = [
            'username' => (string)($row['username'] ?? ''),
            'full_name' => trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? '')),
            'department' => (string)($row['department'] ?? '')
        ];
    }
}

echo json_encode(['success' => true, 'message' => 'Teachers loaded', 'data' => ['teachers' => $teachers]]);
?>
