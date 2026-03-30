<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'director'], true)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$limit = max(1, (int)($_GET['limit'] ?? 8));
$items = [];

$stmt = $conn->prepare("SELECT username, role, email, status, created_at FROM users ORDER BY id DESC LIMIT ?");
if ($stmt) {
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'username' => (string)($row['username'] ?? ''),
            'role' => (string)($row['role'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? '')
        ];
    }
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'message' => 'Recent registrations loaded',
    'data' => ['registrations' => $items]
]);
?>
