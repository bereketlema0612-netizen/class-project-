<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    sendResponse(false, 'Unauthorized', null, 403);
}

$studentUsername = $_SESSION['username'];

$stmt = $conn->prepare("SELECT username FROM students WHERE username = ?");
$stmt->bind_param("s", $studentUsername);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    sendResponse(false, 'Student not found', null, 404);
}

$stmt = $conn->prepare("
    SELECT 
        a.id, a.name, a.description, a.assignment_date, a.due_date,
        s.subject_name,
        t.fname, t.lname
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    LEFT JOIN teachers t ON a.teacher_username = t.username
    WHERE a.class_id IN (
        SELECT class_id FROM class_enrollments WHERE student_username = ?
    )
    ORDER BY a.due_date ASC
");
$stmt->bind_param("s", $studentUsername);
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (!$assignments) {
    sendResponse(true, 'No assignments yet', ['assignments' => [], 'count' => 0], 200);
}

foreach ($assignments as &$assignment) {
    $assignment['teacher_name'] = $assignment['fname'] . ' ' . $assignment['lname'];
    $assignment['days_until_due'] = max(0, (strtotime($assignment['due_date']) - time()) / 86400);
    unset($assignment['fname'], $assignment['lname']);
}

usort($assignments, function($a, $b) {
    return $a['days_until_due'] - $b['days_until_due'];
});

sendResponse(true, 'Assignments retrieved', [
    'assignments' => $assignments,
    'total_assignments' => count($assignments)
], 200);

$stmt->close();
$conn->close();
?>
