<?php
// Simple DB connection (beginner-friendly)
$host = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'bensa_school';

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error,
        'data' => null
    ]);
    exit;
}

$conn->set_charset('utf8mb4');
?>

