<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/curriculum.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$gradeInput = sanitizeInput($_GET['grade'] ?? '');
$gradeDigits = preg_replace('/\D+/', '', $gradeInput);
$stream = normalizeStream(sanitizeInput($_GET['stream'] ?? ''));

$sql = "SELECT id, subject_name, subject_code FROM subjects";
$types = '';
$params = [];
$curriculumList = [];
if ($gradeDigits !== '') {
    $curriculumList = curriculumSubjects($gradeDigits, $stream);
    if (!empty($curriculumList)) {
        $placeholders = implode(',', array_fill(0, count($curriculumList), '?'));
        $sql .= " WHERE subject_name IN ($placeholders)";
        $types = str_repeat('s', count($curriculumList));
        $params = $curriculumList;
    } else {
        $sql .= " WHERE 1=0";
    }
}
$sql .= " ORDER BY subject_name ASC";

$subjects = [];
if ($types === '') {
    $result = $conn->query($sql);
    if (!$result) {
        sendResponse(false, 'Failed to load subjects: ' . $conn->error, null, 500);
    }
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
} else {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendResponse(false, 'Failed to prepare subjects query: ' . $conn->error, null, 500);
    }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        sendResponse(false, 'Failed to load subjects: ' . $stmt->error, null, 500);
    }
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
}

sendResponse(true, 'Subjects retrieved', [
    'subjects' => $subjects,
    'curriculum_subjects' => $curriculumList
], 200);
$conn->close();
?>
