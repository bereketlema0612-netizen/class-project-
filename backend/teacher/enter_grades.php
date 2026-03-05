<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';
require_once '../helpers/curriculum.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    sendResponse(false, 'Unauthorized', null, 403);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$studentInput = trim((string)($data['student_id'] ?? ''));
$classId = (int)($data['class_id'] ?? 0);
$term = sanitizeInput($data['term'] ?? '');
$marks = (float)($data['marks'] ?? 0);
$subject = sanitizeInput($data['subject'] ?? '');
$gradingScaleId = (int)($data['grading_scale_id'] ?? 0);

if ($studentInput === '' || $classId <= 0 || $term === '' || $subject === '' || $marks < 0 || $marks > 100) {
    sendResponse(false, 'All fields required and marks must be between 0-100', null, 400);
}

$validTerms = ['Term1', 'Term2', 'Term3', 'Term4', 'Term5'];
if (!in_array($term, $validTerms, true)) {
    sendResponse(false, 'Invalid term', null, 400);
}

$teacherUsername = $_SESSION['username'];
ensureAssignmentBlockColumn($conn);

$studentUsername = $studentInput;
if (ctype_digit($studentInput)) {
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ? AND role = 'student'");
    $studentId = (int)$studentInput;
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    if (!$student) {
        sendResponse(false, 'Student not found', null, 404);
    }
    $studentUsername = $student['username'];
}

$stmt = $conn->prepare("SELECT id FROM assignments WHERE class_id = ? AND teacher_username = ? AND assignment_type = 'teacher' AND is_blocked = 0");
$stmt->bind_param("is", $classId, $teacherUsername);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    sendResponse(false, 'Teacher not assigned to this class', null, 403);
}

$stmt = $conn->prepare("SELECT id FROM class_enrollments WHERE student_username = ? AND class_id = ?");
$stmt->bind_param("si", $studentUsername, $classId);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    sendResponse(false, 'Student not enrolled in this class', null, 403);
}

$stmt = $conn->prepare("SELECT grade_level, stream, academic_year_id FROM classes WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $classId);
$stmt->execute();
$classRow = $stmt->get_result()->fetch_assoc();
if (!$classRow) {
    sendResponse(false, 'Class not found', null, 404);
}
$gradeDigits = preg_replace('/\D+/', '', (string)$classRow['grade_level']);
$stream = normalizeStream($classRow['stream'] ?? '');
$allowedSubjects = curriculumSubjects($gradeDigits, $stream);
if (!empty($allowedSubjects) && !in_array($subject, $allowedSubjects, true)) {
    sendResponse(false, 'Subject is not allowed for this class stream curriculum', null, 400);
}
$academicYearId = isset($classRow['academic_year_id']) ? (int)$classRow['academic_year_id'] : null;

$stmt = $conn->prepare("SELECT grade FROM grading_scales WHERE id = ?");
$stmt->bind_param("i", $gradingScaleId);
$stmt->execute();
$scale = $stmt->get_result()->fetch_assoc();
if (!$scale) {
    sendResponse(false, 'Grading scale not found', null, 404);
}
$letterGrade = $scale['grade'];

$subjectId = null;
$stmt = $conn->prepare("SELECT id FROM subjects WHERE subject_name = ? LIMIT 1");
$stmt->bind_param("s", $subject);
$stmt->execute();
$subjectRow = $stmt->get_result()->fetch_assoc();
if ($subjectRow) {
    $subjectId = (int)$subjectRow['id'];
} else {
    $subjectCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $subject), 0, 6));
    $stmt = $conn->prepare("INSERT INTO subjects (subject_name, subject_code) VALUES (?, ?)");
    $stmt->bind_param("ss", $subject, $subjectCode);
    if (!$stmt->execute()) {
        sendResponse(false, 'Failed to create subject', null, 500);
    }
    $subjectId = (int)$stmt->insert_id;
}

$stmt = $conn->prepare("SELECT id FROM grades WHERE student_username = ? AND class_id = ? AND term = ? AND subject_id = ?");
$stmt->bind_param("sisi", $studentUsername, $classId, $term, $subjectId);
$stmt->execute();
$existing = $stmt->get_result()->num_rows > 0;

$conn->begin_transaction();
try {
    if ($existing) {
        $stmt = $conn->prepare("
            UPDATE grades
            SET marks = ?, letter_grade = ?, grading_scale_id = ?, subject = ?, entered_at = NOW()
            WHERE student_username = ? AND class_id = ? AND term = ? AND subject_id = ?
        ");
        $stmt->bind_param("dsissisi", $marks, $letterGrade, $gradingScaleId, $subject, $studentUsername, $classId, $term, $subjectId);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO grades (student_username, class_id, teacher_username, term, marks, letter_grade, grading_scale_id, subject, subject_id, academic_year_id, entered_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("sissdsisii", $studentUsername, $classId, $teacherUsername, $term, $marks, $letterGrade, $gradingScaleId, $subject, $subjectId, $academicYearId);
    }

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $conn->commit();
    logSystemActivity($conn, $teacherUsername, 'GRADE_ENTRY', 'Teacher entered grades for student ' . $studentUsername . ' in class ' . $classId . ' subject ' . $subject, 'success');

    sendResponse(true, 'Grade saved successfully', [
        'student_username' => $studentUsername,
        'class_id' => $classId,
        'term' => $term,
        'subject' => $subject,
        'marks' => $marks,
        'letter_grade' => $letterGrade
    ], 200);
} catch (Exception $e) {
    $conn->rollback();
    sendResponse(false, 'Grade entry failed: ' . $e->getMessage(), null, 500);
}

$conn->close();
?>
