<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$term = isset($_GET['term']) ? sanitizeInput($_GET['term']) : '';
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    sendResponse(false, 'Unauthorized', null, 403);
}
if (!$class_id) {
    sendResponse(false, 'Class ID required', null, 400);
}
$teacher_username = $_SESSION['username'];
ensureAssignmentBlockColumn($conn);

$stmt = $conn->prepare("SELECT id FROM assignments WHERE class_id = ? AND teacher_username = ? AND assignment_type = 'teacher' AND is_blocked = 0");
$stmt->bind_param("is", $class_id, $teacher_username);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    sendResponse(false, 'Teacher not assigned to this class', null, 403);
}

$query = "SELECT g.id, g.student_username, g.term, g.marks, g.letter_grade, g.subject, g.entered_at, s.fname, s.mname, s.lname, s.username as student_id_generated FROM grades g JOIN students s ON g.student_username = s.username WHERE g.class_id = ?";

if ($term) {
    $query .= " AND g.term = ?";
}

$query .= " ORDER BY s.fname, s.lname";

if ($term) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $class_id, $term);
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $class_id);
}

$stmt->execute();
$result = $stmt->get_result();

$grades = [];
while ($row = $result->fetch_assoc()) {
    $row['full_name'] = $row['fname'] . ' ' . ($row['mname'] ? $row['mname'] . ' ' : '') . $row['lname'];
    $grades[] = $row;
}

$classStmt = $conn->prepare("SELECT CONCAT('Grade ', grade_level, ' - ', section) AS name FROM classes WHERE id = ?");
$classStmt->bind_param("i", $class_id);
$classStmt->execute();
$classResult = $classStmt->get_result();
$classData = $classResult->fetch_assoc();

sendResponse(true, 'Class grades retrieved successfully', [
    'class_name' => $classData['name'],
    'term' => $term ?: 'All Terms',
    'grades' => $grades,
    'total_grades' => count($grades)
], 200);

$stmt->close();
$classStmt->close();
$conn->close();

?>
