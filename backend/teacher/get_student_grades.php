<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    sendResponse(false, 'Unauthorized', null, 403);
}

$studentId = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

if ($studentId === '') {
    sendResponse(false, 'Student ID required', null, 400);
}

$studentUsername = $studentId;
if (ctype_digit($studentId)) {
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ? AND role = 'student'");
    $id = (int)$studentId;
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) {
        sendResponse(false, 'Student not found', null, 404);
    }
    $studentUsername = $user['username'];
}

$query = "
    SELECT g.id, g.term, g.marks, g.letter_grade, g.subject, g.entered_at, t.fname, t.lname,
           CONCAT('Grade ', c.grade_level, ' - ', c.section) as class_name
    FROM grades g
    JOIN classes c ON g.class_id = c.id
    LEFT JOIN teachers t ON g.teacher_username = t.username
    WHERE g.student_username = ?
";
if ($classId) {
    $query .= " AND g.class_id = ?";
}
$query .= " ORDER BY g.term DESC, g.entered_at DESC";

$stmt = $conn->prepare($query);
if ($classId) {
    $stmt->bind_param("si", $studentUsername, $classId);
} else {
    $stmt->bind_param("s", $studentUsername);
}
$stmt->execute();
$result = $stmt->get_result();

$grades = [];
while ($row = $result->fetch_assoc()) {
    $grades[] = $row;
}

$averageMarks = 0;
if (count($grades) > 0) {
    $total = 0;
    foreach ($grades as $grade) {
        $total += (float)$grade['marks'];
    }
    $averageMarks = round($total / count($grades), 2);
}

sendResponse(true, 'Student grades retrieved successfully', [
    'grades' => $grades,
    'total_grades' => count($grades),
    'average_marks' => $averageMarks
], 200);

$stmt->close();
$conn->close();
?>
