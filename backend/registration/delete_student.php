<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$username = sanitizeInput($data['username'] ?? '');
if ($username === '') {
    sendResponse(false, 'Student username is required', null, 400);
}

$checkStmt = $conn->prepare("SELECT username FROM users WHERE username = ? AND role = 'student' LIMIT 1");
if (!$checkStmt) {
    sendResponse(false, 'Prepare failed: ' . $conn->error, null, 500);
}
$checkStmt->bind_param("s", $username);
if (!$checkStmt->execute()) {
    sendResponse(false, 'Check failed: ' . $checkStmt->error, null, 500);
}
if ($checkStmt->get_result()->num_rows === 0) {
    sendResponse(false, 'Student not found', null, 404);
}

$conn->begin_transaction();
try {
    $deleteStmt = $conn->prepare("DELETE FROM users WHERE username = ? AND role = 'student'");
    if (!$deleteStmt) {
        throw new Exception($conn->error);
    }
    $deleteStmt->bind_param("s", $username);
    if (!$deleteStmt->execute()) {
        throw new Exception($deleteStmt->error);
    }
    if ($deleteStmt->affected_rows < 1) {
        throw new Exception('No student deleted');
    }

    logSystemActivity($conn, $_SESSION['username'], 'DELETE_STUDENT', 'Deleted student account ' . $username, 'success');
    $conn->commit();
    sendResponse(true, 'Student deleted successfully', ['username' => $username], 200);
} catch (Exception $e) {
    $conn->rollback();
    sendResponse(false, 'Delete failed: ' . $e->getMessage(), null, 500);
}

$conn->close();
?>
