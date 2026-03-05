<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
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

$stmt = $conn->prepare("SELECT c.id, CONCAT('Grade ', c.grade_level, ' - ', c.section) AS name, c.grade_level, c.section FROM classes c WHERE c.id = ?");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$classResult = $stmt->get_result();

if ($classResult->num_rows !== 1) {
    sendResponse(false, 'Class not found', null, 404);
}

$classData = $classResult->fetch_assoc();

$stmt = $conn->prepare("SELECT cs.id, cs.day_of_week, cs.start_time, cs.end_time, cs.room_number, cs.subject FROM class_schedules cs WHERE cs.class_id = ? ORDER BY FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), cs.start_time");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$scheduleResult = $stmt->get_result();

$schedule = [];
while ($row = $scheduleResult->fetch_assoc()) {
    $schedule[] = $row;
}

$responseData = [
    'class' => $classData,
    'schedule' => $schedule,
    'total_sessions' => count($schedule)
];

sendResponse(true, 'Class schedule retrieved successfully', $responseData, 200);

$stmt->close();
$conn->close();

?>
