<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director', 'registration_admin'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

$sql = "
    SELECT
        cs.id,
        cs.class_id,
        c.grade_level,
        c.section,
        CONCAT('Grade ', c.grade_level, ' - ', c.section) AS class_name,
        cs.subject_id,
        COALESCE(s.subject_name, cs.subject, '-') AS subject_name,
        cs.teacher_username,
        TRIM(CONCAT(COALESCE(t.fname, ''), ' ', COALESCE(t.lname, ''))) AS teacher_name,
        COALESCE(cs.day, cs.day_of_week, '') AS day,
        cs.start_time,
        cs.end_time,
        cs.room_number
    FROM class_schedules cs
    JOIN classes c ON cs.class_id = c.id
    LEFT JOIN subjects s ON cs.subject_id = s.id
    LEFT JOIN teachers t ON cs.teacher_username = t.username
    WHERE 1 = 1
";

$types = '';
$params = [];
if ($classId > 0) {
    $sql .= " AND cs.class_id = ?";
    $types = 'i';
    $params[] = $classId;
}

$sql .= "
    ORDER BY
        c.grade_level,
        c.section,
        FIELD(COALESCE(cs.day, cs.day_of_week, ''), 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
        cs.start_time
";

if ($types === '') {
    $result = $conn->query($sql);
    if (!$result) {
        sendResponse(false, 'Failed to load schedules: ' . $conn->error, null, 500);
    }
} else {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendResponse(false, 'Failed to prepare schedules query: ' . $conn->error, null, 500);
    }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        sendResponse(false, 'Failed to load schedules: ' . $stmt->error, null, 500);
    }
    $result = $stmt->get_result();
}

$items = [];
while ($row = $result->fetch_assoc()) {
    if (trim((string)($row['teacher_name'] ?? '')) === '') {
        $row['teacher_name'] = '-';
    }
    $items[] = $row;
}

sendResponse(true, 'Class schedules retrieved', [
    'schedules' => $items,
    'total_schedules' => count($items)
], 200);

$conn->close();
?>
