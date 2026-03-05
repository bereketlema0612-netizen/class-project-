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

function tableExists(mysqli $conn, string $table): bool {
    $safeTable = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $res && $res->num_rows > 0;
}

function columnExists(mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $res && $res->num_rows > 0;
}

$stmt = $conn->prepare("SELECT username FROM students WHERE username = ?");
if (!$stmt) {
    sendResponse(false, 'Failed to prepare student lookup query: ' . $conn->error, null, 500);
}
$stmt->bind_param("s", $studentUsername);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    sendResponse(false, 'Student not found', null, 404);
}

if (!tableExists($conn, 'class_enrollments') || !tableExists($conn, 'classes')) {
    sendResponse(true, 'No enrollments found', ['enrollments' => [], 'total_enrollments' => 0], 200);
}

$classNameExpr = columnExists($conn, 'classes', 'class_name')
    ? 'c.class_name'
    : (columnExists($conn, 'classes', 'name') ? 'c.name' : "CONCAT('Grade ', c.grade_level, ' - ', c.section)");

$stmt = $conn->prepare("
    SELECT
        ce.id,
        ce.enrollment_date,
        c.id as class_id,
        {$classNameExpr} AS class_name,
        c.grade_level,
        c.section,
        t.fname,
        t.lname
    FROM class_enrollments ce
    JOIN classes c ON ce.class_id = c.id
    LEFT JOIN teachers t ON c.teacher_username = t.username
    WHERE ce.student_username = ?
");
if (!$stmt) {
    sendResponse(false, 'Failed to prepare enrollments query: ' . $conn->error, null, 500);
}
$stmt->bind_param("s", $studentUsername);
$stmt->execute();
$enrollments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (!$enrollments) {
    sendResponse(true, 'No enrollments found', ['enrollments' => [], 'total_enrollments' => 0], 200);
}

$hasSchedules = tableExists($conn, 'class_schedules');
$hasSubjects = tableExists($conn, 'subjects');
$dayExpr = columnExists($conn, 'class_schedules', 'day')
    ? 'cs.day'
    : (columnExists($conn, 'class_schedules', 'day_of_week') ? 'cs.day_of_week' : "''");

foreach ($enrollments as &$enrollment) {
    $fname = (string)($enrollment['fname'] ?? '');
    $lname = (string)($enrollment['lname'] ?? '');
    $enrollment['teacher_name'] = trim($fname . ' ' . $lname);
    if ($enrollment['teacher_name'] === '') {
        $enrollment['teacher_name'] = '-';
    }

    $classId = (int)$enrollment['class_id'];
    $enrollment['schedule'] = [];

    if ($hasSchedules) {
        $subjectJoin = $hasSubjects ? 'LEFT JOIN subjects s ON cs.subject_id = s.id' : '';
        $subjectExpr = $hasSubjects
            ? "COALESCE(s.subject_name, cs.subject, '-')"
            : "COALESCE(cs.subject, '-')";

        $scheduleStmt = $conn->prepare("
            SELECT
                cs.id,
                {$dayExpr} AS day,
                cs.start_time,
                cs.end_time,
                cs.room_number,
                {$subjectExpr} AS subject_name,
                TRIM(CONCAT(COALESCE(ts.fname, ''), ' ', COALESCE(ts.lname, ''))) AS session_teacher_name
            FROM class_schedules cs
            {$subjectJoin}
            LEFT JOIN teachers ts ON cs.teacher_username = ts.username
            WHERE cs.class_id = ?
            ORDER BY cs.start_time
        ");
        if ($scheduleStmt) {
            $scheduleStmt->bind_param("i", $classId);
            $scheduleStmt->execute();
            $enrollment['schedule'] = $scheduleStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $scheduleStmt->close();
        }
    }

    unset($enrollment['fname'], $enrollment['lname']);
}
unset($enrollment);

sendResponse(true, 'Enrollments retrieved', [
    'enrollments' => $enrollments,
    'total_enrollments' => count($enrollments)
], 200);

if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}
$conn->close();
?>
