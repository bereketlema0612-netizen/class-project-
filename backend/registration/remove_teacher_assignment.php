<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

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

$classId = (int)($data['class_id'] ?? 0);
if (!$classId) {
    sendResponse(false, 'Class ID required', null, 400);
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE classes SET teacher_username = NULL WHERE id = ?");
    $stmt->bind_param("i", $classId);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $deleteStmt = $conn->prepare("DELETE FROM assignments WHERE class_id = ? AND assignment_type = 'teacher'");
    $deleteStmt->bind_param("i", $classId);
    $deleteStmt->execute();

    logSystemActivity($conn, $_SESSION['username'], 'REMOVE_TEACHER_ASSIGNMENT', 'Removed teacher from class ' . $classId, 'success');
    $conn->commit();
    sendResponse(true, 'Teacher assignment removed', ['class_id' => $classId], 200);
} catch (Exception $e) {
    $conn->rollback();
    sendResponse(false, 'Failed to remove assignment: ' . $e->getMessage(), null, 500);
}

$conn->close();
?>
