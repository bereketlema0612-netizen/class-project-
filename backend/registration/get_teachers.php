<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'director'], true)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$teachers = [];
$sql = "SELECT t.username, t.fname, t.lname FROM teachers t ORDER BY t.fname, t.lname";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $teachers[] = [
            'username' => (string)($row['username'] ?? ''),
            'full_name' => trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? ''))
        ];
    }
}

echo json_encode(['success' => true, 'message' => 'Teachers loaded', 'data' => ['teachers' => $teachers]]);
?>
