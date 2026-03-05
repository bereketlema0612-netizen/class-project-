<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'director') {
    sendResponse(false, 'Unauthorized access', null, 403);
}

$stmt = $conn->prepare("SELECT * FROM school_settings WHERE id = 1");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    sendResponse(false, 'School settings not found', null, 404);
}

$settings = $result->fetch_assoc();

sendResponse(true, 'School settings retrieved successfully', ['settings' => $settings], 200);

$stmt->close();
$conn->close();
?>
