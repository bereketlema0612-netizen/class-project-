<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || (($_SESSION['role'] ?? '') !== 'admin')) {
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

$teachers = [];
$sql = "SELECT t.username, t.fname, t.lname, t.department, u.status
        FROM teachers t
        LEFT JOIN users u ON u.username = t.username
        ORDER BY t.fname, t.lname";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $teachers[] = [
            'username' => (string)($row['username'] ?? ''),
            'full_name' => trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? '')),
            'department' => (string)($row['department'] ?? ''),
            'status' => (string)($row['status'] ?? 'active')
        ];
    }
}

echo json_encode(['success' => true, 'message' => 'Teachers loaded', 'data' => ['teachers' => $teachers]]);
?>
