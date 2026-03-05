<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';
require_once '../helpers/curriculum.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

ensureAssignmentBlockColumn($conn);

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$teacherUsername = sanitizeInput($data['teacher_username'] ?? '');
$gradeLevelInput = sanitizeInput($data['grade_level'] ?? '');
$section = strtoupper(trim(sanitizeInput($data['section'] ?? '')));
$subject = sanitizeInput($data['subject'] ?? '');
$department = sanitizeInput($data['department'] ?? '');
$streamInput = sanitizeInput($data['stream'] ?? '');

if ($teacherUsername === '' || $gradeLevelInput === '' || $section === '' || $subject === '' || $department === '') {
    sendResponse(false, 'Teacher, grade level, section, subject, and department are required', null, 400);
}

$gradeDigits = preg_replace('/\D+/', '', $gradeLevelInput);
if ($gradeDigits === '') {
    sendResponse(false, 'Invalid grade level', null, 400);
}
if (!preg_match('/^[A-Z0-9_-]{1,30}$/', $section)) {
    sendResponse(false, 'Invalid section format', null, 400);
}
[$streamValid, $streamOrMessage] = validateStreamForGrade($gradeDigits, $streamInput);
if (!$streamValid) {
    sendResponse(false, $streamOrMessage, null, 400);
}
$stream = $streamOrMessage;

$teacherStmt = $conn->prepare("SELECT username FROM teachers WHERE username = ?");
$teacherStmt->bind_param("s", $teacherUsername);
$teacherStmt->execute();
if ($teacherStmt->get_result()->num_rows !== 1) {
    sendResponse(false, 'Teacher not found', null, 404);
}

$classStmt = $conn->prepare("
    SELECT id
    FROM classes
    WHERE section = ?
      AND (grade_level = ? OR grade_level = CONCAT('Grade ', ?))
      AND (stream = ? OR (stream IS NULL AND ? = ''))
    ORDER BY id DESC
    LIMIT 1
");
$classStmt->bind_param("sssss", $section, $gradeDigits, $gradeDigits, $stream, $stream);
$classStmt->execute();
$classRow = $classStmt->get_result()->fetch_assoc();
if (!$classRow) {
    sendResponse(false, 'No class found for the selected grade, section, and stream', null, 404);
}
$classId = (int)$classRow['id'];

$subjectId = null;
$subjectStmt = $conn->prepare("SELECT id FROM subjects WHERE subject_name = ? LIMIT 1");
$subjectStmt->bind_param("s", $subject);
$subjectStmt->execute();
$subjectRow = $subjectStmt->get_result()->fetch_assoc();
if ($subjectRow) {
    $subjectId = (int)$subjectRow['id'];
} else {
    $subjectCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $subject), 0, 6));
    $insertSubjectStmt = $conn->prepare("INSERT INTO subjects (subject_name, subject_code) VALUES (?, ?)");
    if (!$insertSubjectStmt) {
        sendResponse(false, 'Failed to prepare subject insert: ' . $conn->error, null, 500);
    }
    $insertSubjectStmt->bind_param("ss", $subject, $subjectCode);
    if (!$insertSubjectStmt->execute()) {
        sendResponse(false, 'Failed to create subject: ' . $insertSubjectStmt->error, null, 500);
    }
    $subjectId = (int)$insertSubjectStmt->insert_id;
}

$conn->begin_transaction();
try {
    $existingStmt = $conn->prepare("
        SELECT id
        FROM assignments
        WHERE class_id = ? AND teacher_username = ? AND assignment_type = 'teacher' AND subject_id = ?
        LIMIT 1
    ");
    $existingStmt->bind_param("isi", $classId, $teacherUsername, $subjectId);
    $existingStmt->execute();
    $existingRow = $existingStmt->get_result()->fetch_assoc();

    if (!$existingRow) {
        $insertAssignmentStmt = $conn->prepare("
            INSERT INTO assignments (class_id, teacher_username, assignment_type, is_blocked, subject_id, title, name, description, assignment_date, due_date, created_at)
            VALUES (?, ?, 'teacher', 0, ?, 'Teacher-Class Assignment', 'Teacher-Class Assignment', 'Teacher assigned to class', CURDATE(), NULL, NOW())
        ");
        $insertAssignmentStmt->bind_param("isi", $classId, $teacherUsername, $subjectId);
        if (!$insertAssignmentStmt->execute()) {
            throw new Exception($insertAssignmentStmt->error);
        }
    } else {
        $unblockStmt = $conn->prepare("
            UPDATE assignments
            SET is_blocked = 0
            WHERE class_id = ? AND teacher_username = ? AND assignment_type = 'teacher' AND subject_id = ?
        ");
        $unblockStmt->bind_param("isi", $classId, $teacherUsername, $subjectId);
        if (!$unblockStmt->execute()) {
            throw new Exception($unblockStmt->error);
        }
    }

    $updateClassStmt = $conn->prepare("UPDATE classes SET teacher_username = COALESCE(teacher_username, ?) WHERE id = ?");
    $updateClassStmt->bind_param("si", $teacherUsername, $classId);
    $updateClassStmt->execute();

    logSystemActivity($conn, $_SESSION['username'], 'ASSIGN_TEACHER', 'Assigned teacher ' . $teacherUsername . ' to class ' . $classId . ' for subject ' . $subject, 'success');
    $conn->commit();

    sendResponse(true, 'Teacher assigned successfully', [
        'teacher_username' => $teacherUsername,
        'class_id' => $classId,
        'subject' => $subject,
        'department' => $department
    ], 200);
} catch (Exception $e) {
    $conn->rollback();
    sendResponse(false, 'Assignment failed: ' . $e->getMessage(), null, 500);
}

$conn->close();
?>
