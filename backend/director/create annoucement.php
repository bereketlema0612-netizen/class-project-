<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'director') {
    sendResponse(false, 'Unauthorized access', null, 403);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$title = sanitizeInput($data['title'] ?? '');
$message = sanitizeInput($data['message'] ?? '');
$audience = sanitizeInput($data['audience'] ?? '');
$priority = sanitizeInput($data['priority'] ?? 'Normal');

if (!$title || !$message || !$audience) {
    sendResponse(false, 'All required fields must be filled', null, 400);
}

if (!in_array($audience, ['all', 'students', 'teachers'])) {
    sendResponse(false, 'Invalid audience', null, 400);
}

if (!in_array($priority, ['Normal', 'High', 'Urgent'])) {
    sendResponse(false, 'Invalid priority', null, 400);
}

$director_username = $_SESSION['username'];

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("INSERT INTO announcements (title, message, audience, priority, created_by_username, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("sssss", $title, $message, $audience, $priority, $director_username);
    
    if (!$stmt->execute()) {
        throw new Exception("Announcement creation failed: " . $stmt->error);
    }
    
    $announcement_id = $conn->insert_id;
    
    logSystemActivity($conn, $director_username, 'CREATE_ANNOUNCEMENT', 'Created announcement: ' . $title . ' (Audience: ' . $audience . ')', 'success');
    
    $conn->commit();
    
    sendResponse(true, 'Announcement sent successfully', [
        'announcement_id' => $announcement_id,
        'title' => $title,
        'audience' => $audience,
        'priority' => $priority
    ], 201);
    
} catch (Exception $e) {
    $conn->rollback();
    sendResponse(false, 'Announcement creation failed: ' . $e->getMessage(), null, 500);
}

$stmt->close();
$conn->close();
?>
