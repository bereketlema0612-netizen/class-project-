<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$username = (string)$_SESSION['username'];

$sql = "SELECT
            u.username,
            u.email,
            t.fname,
            t.lname,
            '' AS department,
            '' AS office_phone,
            '' AS office_room,
            '' AS subject,
            t.username AS employee_id_generated
        FROM users u
        LEFT JOIN teachers t ON t.username = u.username
        WHERE u.username = ?
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

echo json_encode([
    'success' => true,
    'message' => 'Teacher profile loaded',
    'data' => ['teacher' => $row]
]);
?>
