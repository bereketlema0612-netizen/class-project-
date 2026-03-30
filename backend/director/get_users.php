<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'director') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$role = strtolower(trim((string)($_GET['role'] ?? 'students')));

$conn->query("CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    fname VARCHAR(50) NOT NULL,
    mname VARCHAR(50) NULL,
    lname VARCHAR(50) NOT NULL,
    grade_level VARCHAR(20) NULL
)");
$conn->query("CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    fname VARCHAR(50) NOT NULL,
    mname VARCHAR(50) NULL,
    lname VARCHAR(50) NOT NULL,
    department VARCHAR(100) NULL,
    subject VARCHAR(100) NULL
)");
$conn->query("CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    fname VARCHAR(50) NOT NULL,
    lname VARCHAR(50) NOT NULL,
    department VARCHAR(100) NULL,
    phone VARCHAR(50) NULL
)");

function ensure_column($conn, $table, $columnDef) {
    $col = explode(' ', $columnDef)[0];
    $chk = $conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($col) . "'");
    if ($chk && $chk->num_rows === 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN $columnDef");
    }
}

ensure_column($conn, 'students', "mname VARCHAR(50) NULL");
ensure_column($conn, 'students', "grade_level VARCHAR(20) NULL");
ensure_column($conn, 'students', "stream VARCHAR(20) NULL");
ensure_column($conn, 'teachers', "mname VARCHAR(50) NULL");
ensure_column($conn, 'teachers', "department VARCHAR(100) NULL");
ensure_column($conn, 'teachers', "subject VARCHAR(100) NULL");
ensure_column($conn, 'admins', "department VARCHAR(100) NULL");
ensure_column($conn, 'admins', "phone VARCHAR(50) NULL");

$users = [];

if ($role === 'teachers') {
    $sql = "SELECT u.username, u.email, u.status, t.fname, t.lname, t.department, t.subject
            FROM users u
            LEFT JOIN teachers t ON t.username = u.username
            WHERE u.role = 'teacher'
            ORDER BY u.id DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $users[] = [
                'username' => (string)($row['username'] ?? ''),
                'name' => trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? '')),
                'email' => (string)($row['email'] ?? ''),
                'department' => (string)($row['department'] ?? ''),
                'subject' => (string)($row['subject'] ?? ''),
                'status' => (string)($row['status'] ?? 'active')
            ];
        }
    }
} else if ($role === 'admins') {
    $sql = "SELECT u.username, u.email, u.status, u.created_at, a.fname, a.lname, a.department
            FROM users u
            LEFT JOIN admins a ON a.username = u.username
            WHERE u.role = 'admin'
            ORDER BY u.id DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $users[] = [
                'username' => (string)($row['username'] ?? ''),
                'name' => trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? '')),
                'email' => (string)($row['email'] ?? ''),
                'department' => (string)($row['department'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'status' => (string)($row['status'] ?? 'active')
            ];
        }
    }
} else {
    $sql = "SELECT u.username, u.email, u.status, s.fname, s.lname, s.grade_level, s.stream
            FROM users u
            LEFT JOIN students s ON s.username = u.username
            WHERE u.role = 'student'
            ORDER BY u.id DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $users[] = [
                'username' => (string)($row['username'] ?? ''),
                'name' => trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? '')),
                'email' => (string)($row['email'] ?? ''),
                'grade_level' => (string)($row['grade_level'] ?? ''),
                'section' => (string)($row['stream'] ?? '-'),
                'status' => (string)($row['status'] ?? 'active')
            ];
        }
    }
}

echo json_encode(['success' => true, 'message' => 'Users loaded', 'data' => ['role' => $role, 'users' => $users]]);
?>
