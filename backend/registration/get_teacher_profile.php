<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director', 'registration_admin'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$username = sanitizeInput($_GET['username'] ?? '');
if ($username === '') {
    sendResponse(false, 'username is required', null, 400);
}

$stmt = $conn->prepare("
    SELECT
        t.username,
        u.email,
        u.status,
        t.fname,
        t.mname,
        t.lname,
        t.DOB,
        t.age,
        t.sex,
        t.department,
        t.subject,
        t.address,
        t.office_room,
        t.office_phone
    FROM teachers t
    JOIN users u ON u.username = t.username
    WHERE t.username = ?
    LIMIT 1
");
$stmt->bind_param("s", $username);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) {
    sendResponse(false, 'Teacher profile not found', null, 404);
}

sendResponse(true, 'Teacher profile retrieved', ['profile' => $row], 200);
$conn->close();
?>
