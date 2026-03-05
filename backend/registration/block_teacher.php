<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username'])) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$sessionUsername = (string)$_SESSION['username'];
$sessionRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
$sessionRole = str_replace(['-', ' '], '_', $sessionRole);
$isAllowedRole = in_array($sessionRole, ['admin', 'director', 'registration_admin'], true);

if (!$isAllowedRole) {
    $isAdminTableUser = false;
    $tblAdmins = $conn->query("SHOW TABLES LIKE 'admins'");
    $tblDirectors = $conn->query("SHOW TABLES LIKE 'directors'");
    if (($tblAdmins && $tblAdmins->num_rows > 0) || ($tblDirectors && $tblDirectors->num_rows > 0)) {
        $authSqlParts = [];
        if ($tblAdmins && $tblAdmins->num_rows > 0) {
            $authSqlParts[] = "SELECT username FROM admins WHERE username = ?";
        }
        if ($tblDirectors && $tblDirectors->num_rows > 0) {
            $authSqlParts[] = "SELECT username FROM directors WHERE username = ?";
        }
        $authSql = implode(" UNION ", $authSqlParts) . " LIMIT 1";
        $authStmt = $conn->prepare($authSql);
        if ($authStmt) {
            if (count($authSqlParts) === 2) {
                $authStmt->bind_param("ss", $sessionUsername, $sessionUsername);
            } else {
                $authStmt->bind_param("s", $sessionUsername);
            }
            $authStmt->execute();
            $isAdminTableUser = (bool)$authStmt->get_result()->fetch_assoc();
        }
    }

    if (!$isAdminTableUser) {
        sendResponse(false, 'Unauthorized', null, 403);
    }
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$teacherUsername = sanitizeInput($data['teacher_username'] ?? '');
$classId = (int)($data['class_id'] ?? 0);
$action = strtolower(trim((string)($data['action'] ?? 'block')));
if ($teacherUsername === '') {
    sendResponse(false, 'teacher_username is required', null, 400);
}
if ($classId <= 0) {
    sendResponse(false, 'class_id is required', null, 400);
}
if (!in_array($action, ['block', 'unblock'], true)) {
    sendResponse(false, 'action must be block or unblock', null, 400);
}

ensureAssignmentBlockColumn($conn);

$checkStmt = $conn->prepare("
    SELECT id
    FROM assignments
    WHERE class_id = ? AND teacher_username = ? AND assignment_type = 'teacher'
    LIMIT 1
");
$checkStmt->bind_param("is", $classId, $teacherUsername);
$checkStmt->execute();
if (!$checkStmt->get_result()->fetch_assoc()) {
    sendResponse(false, 'Teacher assignment not found for class', null, 404);
}

$isBlocked = $action === 'block' ? 1 : 0;
$stmt = $conn->prepare("
    UPDATE assignments
    SET is_blocked = ?
    WHERE class_id = ? AND teacher_username = ? AND assignment_type = 'teacher'
");
$stmt->bind_param("iis", $isBlocked, $classId, $teacherUsername);
if (!$stmt->execute()) {
    sendResponse(false, 'Failed to update assignment block status: ' . $stmt->error, null, 500);
}

logSystemActivity($conn, $_SESSION['username'], 'TEACHER_ASSIGNMENT_BLOCK_UPDATE', strtoupper($action) . ' teacher ' . $teacherUsername . ' for class ' . $classId, 'success');
sendResponse(true, 'Teacher assignment block status updated', [
    'teacher_username' => $teacherUsername,
    'class_id' => $classId,
    'is_blocked' => $isBlocked
], 200);

$conn->close();
?>
