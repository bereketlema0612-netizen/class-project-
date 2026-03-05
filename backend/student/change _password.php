<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    sendResponse(false, 'Unauthorized', null, 403);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$oldPassword = $data['old_password'] ?? '';
$newPassword = $data['new_password'] ?? '';
$confirmPassword = $data['confirm_password'] ?? '';

if (!$oldPassword || !$newPassword || !$confirmPassword) {
    sendResponse(false, 'All password fields are required', null, 400);
}

if ($newPassword !== $confirmPassword) {
    sendResponse(false, 'New passwords do not match', null, 400);
}

$passwordValidation = validatePassword($newPassword);
if (!$passwordValidation['valid']) {
    sendResponse(false, $passwordValidation['message'], null, 400);
}

$username = $_SESSION['username'];

$stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result) {
    sendResponse(false, 'User not found', null, 404);
}

if (!verifyPassword($oldPassword, $result['password'])) {
    sendResponse(false, 'Current password is incorrect', null, 401);
}

$newPasswordHash = hashPassword($newPassword);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
$stmt->bind_param("ss", $newPasswordHash, $username);

if (!$stmt->execute()) {
    sendResponse(false, 'Failed to change password', null, 500);
}

logSystemActivity($conn, $username, 'PASSWORD_CHANGE', 'Student changed password', 'success');

sendResponse(true, 'Password changed successfully', ['message' => 'Your password has been updated'], 200);

$stmt->close();
$conn->close();
?>
