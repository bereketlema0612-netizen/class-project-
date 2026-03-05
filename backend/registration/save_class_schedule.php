<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director', 'registration_admin'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$id = isset($data['id']) ? (int)$data['id'] : 0;
$classId = (int)($data['class_id'] ?? 0);
$subjectId = (int)($data['subject_id'] ?? 0);
$teacherUsernameRaw = trim((string)($data['teacher_username'] ?? ''));
$teacherUsername = $teacherUsernameRaw !== '' ? $teacherUsernameRaw : null;
$day = trim((string)($data['day'] ?? ''));
$startTime = trim((string)($data['start_time'] ?? ''));
$endTime = trim((string)($data['end_time'] ?? ''));
$roomNumber = trim((string)($data['room_number'] ?? ''));

if ($classId <= 0 || $subjectId <= 0 || $day === '' || $startTime === '' || $endTime === '') {
    sendResponse(false, 'Class, subject, day, start time, and end time are required', null, 400);
}

$validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
if (!in_array($day, $validDays, true)) {
    sendResponse(false, 'Invalid day value', null, 400);
}

if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $endTime)) {
    sendResponse(false, 'Invalid time format. Use HH:MM or HH:MM:SS', null, 400);
}

$startNorm = strlen($startTime) === 5 ? $startTime . ':00' : $startTime;
$endNorm = strlen($endTime) === 5 ? $endTime . ':00' : $endTime;
if (strtotime($startNorm) >= strtotime($endNorm)) {
    sendResponse(false, 'End time must be after start time', null, 400);
}

$classStmt = $conn->prepare("SELECT id FROM classes WHERE id = ?");
if (!$classStmt) {
    sendResponse(false, 'Failed to prepare class lookup: ' . $conn->error, null, 500);
}
$classStmt->bind_param("i", $classId);
$classStmt->execute();
if (!$classStmt->get_result()->fetch_assoc()) {
    sendResponse(false, 'Class not found', null, 404);
}

$subjectName = '';
$subjectStmt = $conn->prepare("SELECT subject_name FROM subjects WHERE id = ?");
if (!$subjectStmt) {
    sendResponse(false, 'Failed to prepare subject lookup: ' . $conn->error, null, 500);
}
$subjectStmt->bind_param("i", $subjectId);
$subjectStmt->execute();
$subjectRow = $subjectStmt->get_result()->fetch_assoc();
if (!$subjectRow) {
    sendResponse(false, 'Subject not found', null, 404);
}
$subjectName = (string)$subjectRow['subject_name'];

if ($teacherUsername !== null) {
    $teacherStmt = $conn->prepare("SELECT username FROM teachers WHERE username = ?");
    if (!$teacherStmt) {
        sendResponse(false, 'Failed to prepare teacher lookup: ' . $conn->error, null, 500);
    }
    $teacherStmt->bind_param("s", $teacherUsername);
    $teacherStmt->execute();
    if (!$teacherStmt->get_result()->fetch_assoc()) {
        sendResponse(false, 'Teacher not found', null, 404);
    }
}

$conflictSql = "
    SELECT id
    FROM class_schedules
    WHERE class_id = ?
      AND COALESCE(day, day_of_week) = ?
      AND (? < end_time AND ? > start_time)
";
if ($id > 0) {
    $conflictSql .= " AND id <> ?";
}
$conflictStmt = $conn->prepare($conflictSql);
if (!$conflictStmt) {
    sendResponse(false, 'Failed to prepare class conflict check: ' . $conn->error, null, 500);
}
if ($id > 0) {
    $conflictStmt->bind_param("isssi", $classId, $day, $startNorm, $endNorm, $id);
} else {
    $conflictStmt->bind_param("isss", $classId, $day, $startNorm, $endNorm);
}
$conflictStmt->execute();
if ($conflictStmt->get_result()->fetch_assoc()) {
    sendResponse(false, 'Class has a conflicting schedule at this time', null, 409);
}

if ($teacherUsername !== null) {
    $teacherConflictSql = "
        SELECT id
        FROM class_schedules
        WHERE teacher_username = ?
          AND COALESCE(day, day_of_week) = ?
          AND (? < end_time AND ? > start_time)
    ";
    if ($id > 0) {
        $teacherConflictSql .= " AND id <> ?";
    }
    $teacherConflictStmt = $conn->prepare($teacherConflictSql);
    if (!$teacherConflictStmt) {
        sendResponse(false, 'Failed to prepare teacher conflict check: ' . $conn->error, null, 500);
    }
    if ($id > 0) {
        $teacherConflictStmt->bind_param("ssssi", $teacherUsername, $day, $startNorm, $endNorm, $id);
    } else {
        $teacherConflictStmt->bind_param("ssss", $teacherUsername, $day, $startNorm, $endNorm);
    }
    $teacherConflictStmt->execute();
    if ($teacherConflictStmt->get_result()->fetch_assoc()) {
        sendResponse(false, 'Teacher already has another class at this time', null, 409);
    }
}

if ($id > 0) {
    $stmt = $conn->prepare("
        UPDATE class_schedules
        SET class_id = ?, subject_id = ?, teacher_username = ?, day = ?, day_of_week = ?, start_time = ?, end_time = ?, room_number = ?, subject = ?
        WHERE id = ?
    ");
    if (!$stmt) {
        sendResponse(false, 'Failed to prepare schedule update: ' . $conn->error, null, 500);
    }
    $stmt->bind_param("iisssssssi", $classId, $subjectId, $teacherUsername, $day, $day, $startNorm, $endNorm, $roomNumber, $subjectName, $id);
    if (!$stmt->execute()) {
        sendResponse(false, 'Failed to update schedule: ' . $stmt->error, null, 500);
    }
    sendResponse(true, 'Class schedule updated successfully', ['schedule_id' => $id], 200);
}

$stmt = $conn->prepare("
    INSERT INTO class_schedules (class_id, subject_id, teacher_username, day, day_of_week, start_time, end_time, room_number, subject, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
if (!$stmt) {
    sendResponse(false, 'Failed to prepare schedule insert: ' . $conn->error, null, 500);
}
$stmt->bind_param("iisssssss", $classId, $subjectId, $teacherUsername, $day, $day, $startNorm, $endNorm, $roomNumber, $subjectName);
if (!$stmt->execute()) {
    sendResponse(false, 'Failed to create schedule: ' . $stmt->error, null, 500);
}

sendResponse(true, 'Class schedule created successfully', ['schedule_id' => (int)$stmt->insert_id], 201);

$conn->close();
?>
