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

$teacherUsername = $_SESSION['username'];
ensureAssignmentBlockColumn($conn);

$sql = "
    SELECT DISTINCT
        cs.id,
        cs.class_id,
        c.grade_level,
        c.section,
        CONCAT('Grade ', c.grade_level, ' - ', c.section) AS class_name,
        COALESCE(s.subject_name, cs.subject, '-') AS subject_name,
        COALESCE(cs.day, cs.day_of_week, '') AS day,
        cs.start_time,
        cs.end_time,
        cs.room_number
    FROM class_schedules cs
    JOIN classes c ON cs.class_id = c.id
    LEFT JOIN subjects s ON cs.subject_id = s.id
    JOIN assignments a
      ON a.class_id = cs.class_id
     AND a.teacher_username = ?
     AND a.assignment_type = 'teacher'
     AND a.is_blocked = 0
     AND (
            (a.subject_id IS NOT NULL AND cs.subject_id = a.subject_id)
            OR
            (a.subject_id IS NULL AND (cs.subject_id IS NULL OR cs.subject_id = 0))
         )
    WHERE (cs.teacher_username = ? OR cs.teacher_username IS NULL OR cs.teacher_username = '')
    ORDER BY
        FIELD(COALESCE(cs.day, cs.day_of_week, ''), 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
        cs.start_time,
        c.grade_level,
        c.section
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    sendResponse(false, 'Failed to prepare teacher schedule query: ' . $conn->error, null, 500);
}
$stmt->bind_param("ss", $teacherUsername, $teacherUsername);
if (!$stmt->execute()) {
    sendResponse(false, 'Failed to load teacher schedule: ' . $stmt->error, null, 500);
}
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

sendResponse(true, 'Teacher schedule retrieved', [
    'schedule' => $items,
    'total_sessions' => count($items)
], 200);

$conn->close();
?>
