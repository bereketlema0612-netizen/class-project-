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

$studentUsername = sanitizeInput($data['student_username'] ?? '');
$toGradeInput = sanitizeInput($data['to_grade'] ?? '');
$newSection = sanitizeInput($data['new_section'] ?? '');
$remarks = sanitizeInput($data['remarks'] ?? '');
$promotedDate = $data['promoted_date'] ?? date('Y-m-d');

if ($studentUsername === '' || $toGradeInput === '') {
    sendResponse(false, 'Student and target grade are required', null, 400);
}

$studentStmt = $conn->prepare("SELECT grade_level FROM students WHERE username = ?");
$studentStmt->bind_param("s", $studentUsername);
$studentStmt->execute();
$studentRow = $studentStmt->get_result()->fetch_assoc();
if (!$studentRow) {
    sendResponse(false, 'Student not found', null, 404);
}

$fromGrade = $studentRow['grade_level'];
$toGradeDigits = preg_replace('/\D+/', '', $toGradeInput);
$toGrade = $toGradeDigits !== '' ? $toGradeDigits : $toGradeInput;

$conn->begin_transaction();
try {
    $promotedBy = $_SESSION['username'];
    $insertStmt = $conn->prepare("
        INSERT INTO promotions (student_username, from_grade, to_grade, promoted_date, promoted_by_username, remarks, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $insertStmt->bind_param("ssssss", $studentUsername, $fromGrade, $toGrade, $promotedDate, $promotedBy, $remarks);
    if (!$insertStmt->execute()) {
        throw new Exception($insertStmt->error);
    }

    $updateStudentStmt = $conn->prepare("UPDATE students SET grade_level = ? WHERE username = ?");
    $updateStudentStmt->bind_param("ss", $toGrade, $studentUsername);
    if (!$updateStudentStmt->execute()) {
        throw new Exception($updateStudentStmt->error);
    }

    if ($newSection !== '') {
        $classStmt = $conn->prepare("
            SELECT id
            FROM classes
            WHERE section = ?
              AND (grade_level = ? OR grade_level = CONCAT('Grade ', ?))
            ORDER BY id DESC
            LIMIT 1
        ");
        $classStmt->bind_param("sss", $newSection, $toGrade, $toGrade);
        $classStmt->execute();
        $classRow = $classStmt->get_result()->fetch_assoc();
        if ($classRow) {
            $classId = (int)$classRow['id'];
            $assignStmt = $conn->prepare("
                INSERT INTO class_enrollments (student_username, class_id, enrollment_date)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE enrollment_date = VALUES(enrollment_date)
            ");
            $assignStmt->bind_param("sis", $studentUsername, $classId, $promotedDate);
            $assignStmt->execute();
        }
    }

    logSystemActivity($conn, $promotedBy, 'PROMOTE_STUDENT', 'Promoted student ' . $studentUsername . ' from ' . $fromGrade . ' to ' . $toGrade, 'success');
    $conn->commit();

    sendResponse(true, 'Student promoted successfully', [
        'student_username' => $studentUsername,
        'from_grade' => $fromGrade,
        'to_grade' => $toGrade,
        'promoted_date' => $promotedDate
    ], 201);
} catch (Exception $e) {
    $conn->rollback();
    sendResponse(false, 'Promotion failed: ' . $e->getMessage(), null, 500);
}

$conn->close();
?>
