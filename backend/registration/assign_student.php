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

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$studentUsername = sanitizeInput($data['student_username'] ?? '');
$gradeLevelInput = sanitizeInput($data['grade_level'] ?? '');
$section = strtoupper(trim(sanitizeInput($data['section'] ?? '')));
$enrollmentDate = $data['enrollment_date'] ?? date('Y-m-d');

if ($studentUsername === '' || $gradeLevelInput === '' || $section === '') {
    sendResponse(false, 'Student, grade level, and section are required', null, 400);
}

$gradeDigits = preg_replace('/\D+/', '', $gradeLevelInput);
if ($gradeDigits === '') {
    sendResponse(false, 'Invalid grade level', null, 400);
}
// Determine stream from payload or student profile, based on grade rules.
$streamInput = sanitizeInput($data['stream'] ?? '');
$studentInfoStmt = $conn->prepare("SELECT stream FROM students WHERE username = ?");
$studentInfoStmt->bind_param("s", $studentUsername);
$studentInfoStmt->execute();
$studentInfo = $studentInfoStmt->get_result()->fetch_assoc();
$effectiveStream = $streamInput !== '' ? normalizeStream($streamInput) : normalizeStream($studentInfo['stream'] ?? '');
[$streamValid, $streamOrMessage] = validateStreamForGrade($gradeDigits, $effectiveStream);
if (!$streamValid) {
    sendResponse(false, $streamOrMessage, null, 400);
}
$effectiveStream = $streamOrMessage;
if (!preg_match('/^[A-Z0-9_-]{1,30}$/', $section)) {
    sendResponse(false, 'Invalid section format', null, 400);
}

$studentStmt = $conn->prepare("SELECT username FROM students WHERE username = ?");
$studentStmt->bind_param("s", $studentUsername);
$studentStmt->execute();
if ($studentStmt->get_result()->num_rows !== 1) {
    sendResponse(false, 'Student not found', null, 404);
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
$classStmt->bind_param("sssss", $section, $gradeDigits, $gradeDigits, $effectiveStream, $effectiveStream);
$classStmt->execute();
$classRow = $classStmt->get_result()->fetch_assoc();
$classId = 0;
if ($classRow) {
    $classId = (int)$classRow['id'];
} else {
    // Create the class automatically if it doesn't exist for this grade/section.
    $className = 'Grade ' . $gradeDigits . ' - ' . $section;
    $insertClassStmt = $conn->prepare("
        INSERT INTO classes (name, class_name, grade_level, section, stream, teacher_username, academic_year_id, created_at)
        VALUES (?, ?, ?, ?, NULLIF(?, ''), NULL, NULL, NOW())
    ");
    if (!$insertClassStmt) {
        sendResponse(false, 'Failed to prepare class creation: ' . $conn->error, null, 500);
    }
    $insertClassStmt->bind_param("sssss", $className, $className, $gradeDigits, $section, $effectiveStream);
    if (!$insertClassStmt->execute()) {
        sendResponse(false, 'Failed to create class for selected grade/section: ' . $insertClassStmt->error, null, 500);
    }
    $classId = (int)$insertClassStmt->insert_id;
}

$conn->begin_transaction();
try {
    $deleteStmt = $conn->prepare("
        DELETE ce
        FROM class_enrollments ce
        JOIN classes c ON ce.class_id = c.id
        WHERE ce.student_username = ?
          AND (c.grade_level = ? OR c.grade_level = CONCAT('Grade ', ?))
          AND (c.stream = ? OR (c.stream IS NULL AND ? = ''))
    ");
    $deleteStmt->bind_param("sssss", $studentUsername, $gradeDigits, $gradeDigits, $effectiveStream, $effectiveStream);
    $deleteStmt->execute();

    $insertStmt = $conn->prepare("
        INSERT INTO class_enrollments (student_username, class_id, enrollment_date)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE enrollment_date = VALUES(enrollment_date)
    ");
    $insertStmt->bind_param("sis", $studentUsername, $classId, $enrollmentDate);
    if (!$insertStmt->execute()) {
        throw new Exception($insertStmt->error);
    }

    logSystemActivity($conn, $_SESSION['username'], 'ASSIGN_STUDENT', 'Assigned student ' . $studentUsername . ' to class ' . $classId, 'success');
    $conn->commit();

    sendResponse(true, 'Student assigned successfully', [
        'student_username' => $studentUsername,
        'class_id' => $classId,
        'enrollment_date' => $enrollmentDate
    ], 200);
} catch (Exception $e) {
    $conn->rollback();
    sendResponse(false, 'Assignment failed: ' . $e->getMessage(), null, 500);
}

$conn->close();
?>
