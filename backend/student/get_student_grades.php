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

$grades = [];
$usedTable = '';
if (tableExists($conn, 'grades')) {
    $stmt = $conn->prepare("
        SELECT
            g.id, g.term, g.marks, g.letter_grade,
            COALESCE(s.subject_name, g.subject) AS subject_name,
            t.fname, t.lname,
            COALESCE(ay.academic_year, '-') AS academic_year
        FROM grades g
        LEFT JOIN subjects s ON g.subject_id = s.id
        LEFT JOIN teachers t ON g.teacher_username = t.username
        LEFT JOIN academic_years ay ON g.academic_year_id = ay.id
        WHERE g.student_username = ?
        ORDER BY ay.academic_year DESC, g.term DESC
    ");
    if ($stmt) {
        $stmt->bind_param("s", $studentUsername);
        $stmt->execute();
        $grades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $usedTable = 'grades';
    }
}

if (empty($grades) && tableExists($conn, 'final_grades')) {
    $stmt = $conn->prepare("
        SELECT
            fg.id, fg.term, fg.total_marks AS marks, fg.letter_grade,
            s.subject_name AS subject_name,
            t.fname, t.lname,
            COALESCE(ay.academic_year, '-') AS academic_year
        FROM final_grades fg
        LEFT JOIN subjects s ON fg.subject_id = s.id
        LEFT JOIN teachers t ON fg.teacher_username = t.username
        LEFT JOIN academic_years ay ON fg.academic_year_id = ay.id
        WHERE fg.student_username = ?
        ORDER BY ay.academic_year DESC, fg.term DESC
    ");
    if ($stmt) {
        $stmt->bind_param("s", $studentUsername);
        $stmt->execute();
        $grades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $usedTable = 'final_grades';
    }
}

if (count($grades) === 0) {
    sendResponse(true, 'No grades found yet', ['grades' => [], 'average_marks' => 0, 'total_grades' => 0], 200);
}

$average = 0.0;
if ($usedTable === 'grades') {
    $stmt = $conn->prepare("SELECT AVG(marks) as average FROM grades WHERE student_username = ?");
    if ($stmt) {
        $stmt->bind_param("s", $studentUsername);
        $stmt->execute();
        $average = (float)($stmt->get_result()->fetch_assoc()['average'] ?? 0);
    }
} elseif ($usedTable === 'final_grades') {
    $stmt = $conn->prepare("SELECT AVG(total_marks) as average FROM final_grades WHERE student_username = ?");
    if ($stmt) {
        $stmt->bind_param("s", $studentUsername);
        $stmt->execute();
        $average = (float)($stmt->get_result()->fetch_assoc()['average'] ?? 0);
    }
}

foreach ($grades as &$grade) {
    $fname = (string)($grade['fname'] ?? '');
    $lname = (string)($grade['lname'] ?? '');
    $grade['teacher_name'] = trim($fname . ' ' . $lname);
    if ($grade['teacher_name'] === '') {
        $grade['teacher_name'] = '-';
    }
    unset($grade['fname'], $grade['lname']);
}
unset($grade);

sendResponse(true, 'Grades retrieved', [
    'grades' => $grades,
    'average_marks' => round($average, 2),
    'total_grades' => count($grades)
], 200);

if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}
$conn->close();
?>
