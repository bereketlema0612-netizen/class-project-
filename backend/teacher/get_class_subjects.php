<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';
require_once '../helpers/curriculum.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    sendResponse(false, 'Unauthorized', null, 403);
}

$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
if ($classId <= 0) {
    sendResponse(false, 'Class ID required', null, 400);
}

$teacherUsername = $_SESSION['username'];

$hasStreamCol = false;
$streamColRes = $conn->query("SHOW COLUMNS FROM classes LIKE 'stream'");
if ($streamColRes && $streamColRes->num_rows > 0) {
    $hasStreamCol = true;
}

$classSql = $hasStreamCol
    ? "SELECT id, grade_level, section, stream FROM classes WHERE id = ? LIMIT 1"
    : "SELECT id, grade_level, section, '' AS stream FROM classes WHERE id = ? LIMIT 1";
$classStmt = $conn->prepare($classSql);
$classStmt->bind_param("i", $classId);
$classStmt->execute();
$classRow = $classStmt->get_result()->fetch_assoc();
if (!$classRow) {
    sendResponse(false, 'Class not found', null, 404);
}

$gradeDigits = preg_replace('/\D+/', '', (string)$classRow['grade_level']);
$stream = normalizeStream($classRow['stream'] ?? '');
$curriculumList = curriculumSubjects($gradeDigits, $stream);

$teacherSubjects = [];

// Fallback from teacher profile subject field.
$teacherProfileStmt = $conn->prepare("SELECT subject FROM teachers WHERE username = ? LIMIT 1");
if ($teacherProfileStmt) {
    $teacherProfileStmt->bind_param("s", $teacherUsername);
    if ($teacherProfileStmt->execute()) {
        $teacherRow = $teacherProfileStmt->get_result()->fetch_assoc();
        $profileSubject = trim((string)($teacherRow['subject'] ?? ''));
        if ($profileSubject !== '') {
            $teacherSubjects[] = $profileSubject;
        }
    }
}
$teacherSubjects = array_values(array_unique(array_filter($teacherSubjects, fn($s) => trim((string)$s) !== '')));

if (!empty($curriculumList) && !empty($teacherSubjects)) {
    $allowed = array_values(array_filter($teacherSubjects, fn($s) => in_array($s, $curriculumList, true)));
    if (empty($allowed)) {
        $allowed = $teacherSubjects;
    }
} elseif (!empty($curriculumList)) {
    $allowed = $curriculumList;
} else {
    $allowed = !empty($teacherSubjects) ? $teacherSubjects : [];
}

// Final fallback: avoid empty dropdown by returning all defined subjects.
if (empty($allowed)) {
    $allSubjectsRes = $conn->query("SELECT DISTINCT subject_name FROM subjects ORDER BY subject_name ASC");
    if ($allSubjectsRes) {
        while ($row = $allSubjectsRes->fetch_assoc()) {
            $name = trim((string)($row['subject_name'] ?? ''));
            if ($name !== '') {
                $allowed[] = $name;
            }
        }
        $allowed = array_values(array_unique($allowed));
    }
}

sendResponse(true, 'Class subjects retrieved', [
    'class' => [
        'id' => (int)$classRow['id'],
        'grade_level' => $classRow['grade_level'],
        'section' => $classRow['section'],
        'stream' => $stream
    ],
    'subjects' => $allowed
], 200);

$conn->close();
?>
