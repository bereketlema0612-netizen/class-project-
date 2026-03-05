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

function hasColumn(mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    if (!$res) {
        return false;
    }
    return $res->num_rows > 0;
}

function tableExists(mysqli $conn, string $table): bool {
    $safeTable = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    if (!$res) {
        return false;
    }
    return $res->num_rows > 0;
}

function mustPrepare(mysqli $conn, string $sql, string $context): mysqli_stmt {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendResponse(false, 'Failed to prepare ' . $context . ': ' . $conn->error, null, 500);
    }
    return $stmt;
}

$stmt = mustPrepare($conn, "SELECT username, email FROM users WHERE username = ? AND role = 'teacher'", 'teacher lookup');
$stmt->bind_param('s', $teacherUsername);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) {
    sendResponse(false, 'Teacher not found', null, 404);
}

$stmt = mustPrepare($conn, "
    SELECT t.username as employee_id_generated, t.fname, t.mname, t.lname, u.email,
           t.department, t.subject, t.DOB, t.age, t.sex, t.address, t.office_room, t.office_phone
    FROM teachers t
    JOIN users u ON t.username = u.username
    WHERE t.username = ?
", 'teacher profile lookup');
$stmt->bind_param('s', $teacherUsername);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();

$streamExpr = hasColumn($conn, 'classes', 'stream') ? 'c.stream' : 'NULL AS stream';
$hasAssignmentSubjectCol = hasColumn($conn, 'assignments', 'subject');
$assignmentSubjectExpr = $hasAssignmentSubjectCol ? 'a.subject' : "''";
$stmt = mustPrepare($conn, "
    SELECT
           a.class_id,
           a.teacher_username,
           c.grade_level,
           c.section,
           {$streamExpr},
           CONCAT('Grade ', c.grade_level, ' - ', c.section) AS name,
           GROUP_CONCAT(DISTINCT COALESCE(s.subject_name, {$assignmentSubjectExpr}) ORDER BY COALESCE(s.subject_name, {$assignmentSubjectExpr}) SEPARATOR ', ') AS assigned_subjects
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    LEFT JOIN subjects s ON s.id = a.subject_id
    WHERE a.teacher_username = ? AND a.assignment_type = 'teacher' AND a.is_blocked = 0
    GROUP BY a.class_id, a.teacher_username, c.grade_level, c.section, c.stream
    ORDER BY c.grade_level, c.section
", 'assigned classes lookup');
$stmt->bind_param('s', $teacherUsername);
$stmt->execute();
$assignmentResult = $stmt->get_result();
$classes = [];
while ($row = $assignmentResult->fetch_assoc()) {
    $classes[] = $row;
}

$stmt = mustPrepare($conn, "
    SELECT COUNT(DISTINCT student_username) as total_students
    FROM class_enrollments
    WHERE class_id IN (SELECT class_id FROM assignments WHERE teacher_username = ? AND assignment_type = 'teacher' AND is_blocked = 0)
", 'student statistics');
$stmt->bind_param('s', $teacherUsername);
$stmt->execute();
$studentCount = $stmt->get_result()->fetch_assoc()['total_students'] ?? 0;

$gradeCount = 0;
if (tableExists($conn, 'grades')) {
    $stmt = mustPrepare($conn, "
        SELECT COUNT(*) as total_grades
        FROM grades
        WHERE class_id IN (SELECT class_id FROM assignments WHERE teacher_username = ? AND assignment_type = 'teacher' AND is_blocked = 0)
    ", 'grades statistics');
    $stmt->bind_param('s', $teacherUsername);
    $stmt->execute();
    $gradeCount = $stmt->get_result()->fetch_assoc()['total_grades'] ?? 0;
}

$stmt = mustPrepare($conn, "
    SELECT COUNT(*) as total_schedules
    FROM class_schedules
    WHERE class_id IN (SELECT class_id FROM assignments WHERE teacher_username = ? AND assignment_type = 'teacher' AND is_blocked = 0)
", 'schedules statistics');
$stmt->bind_param('s', $teacherUsername);
$stmt->execute();
$scheduleCount = $stmt->get_result()->fetch_assoc()['total_schedules'] ?? 0;

$annContentExpr = hasColumn($conn, 'announcements', 'content') ? 'COALESCE(content, message)' : 'message';
$stmt = mustPrepare($conn, "
    SELECT id, title, message, {$annContentExpr} as content, priority, audience, created_at
    FROM announcements
    WHERE audience IN ('teachers', 'all') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC
    LIMIT 5
", 'announcements lookup');
$stmt->execute();
$announcementResult = $stmt->get_result();
$announcements = [];
while ($row = $announcementResult->fetch_assoc()) {
    $announcements[] = $row;
}

sendResponse(true, 'Teacher dashboard data retrieved successfully', [
    'teacher' => $teacher,
    'statistics' => [
        'total_classes' => count($classes),
        'total_students' => (int)$studentCount,
        'total_grades_entered' => (int)$gradeCount,
        'total_schedules' => (int)$scheduleCount
    ],
    'assigned_classes' => $classes,
    'recent_announcements' => $announcements
], 200);

$conn->close();
?>
