<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    sendResponse(false, 'Unauthorized', null, 403);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$teacherUsername = $_SESSION['username'];
$classId = (int)($data['class_id'] ?? 0);
$section = strtoupper(trim((string)($data['section'] ?? '')));

if ($classId <= 0) {
    sendResponse(false, 'class_id is required', null, 400);
}

ensureAssignmentBlockColumn($conn);

$assignStmt = $conn->prepare("
    SELECT a.id, c.grade_level, c.section
    FROM assignments a
    JOIN classes c ON c.id = a.class_id
    WHERE a.class_id = ?
      AND a.teacher_username = ?
      AND a.assignment_type = 'teacher'
      AND a.is_blocked = 0
    LIMIT 1
");
$assignStmt->bind_param("is", $classId, $teacherUsername);
$assignStmt->execute();
$assignment = $assignStmt->get_result()->fetch_assoc();
if (!$assignment) {
    sendResponse(false, 'Teacher is not assigned to this class', null, 403);
}

$classSection = strtoupper(trim((string)($assignment['section'] ?? '')));
if ($section === '') {
    $section = $classSection;
}
if ($classSection !== '' && $section !== '' && $classSection !== $section) {
    sendResponse(false, 'Selected section does not match assigned class section', null, 400);
}

$tableSql = "
CREATE TABLE IF NOT EXISTS teacher_reports (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_username VARCHAR(20) NOT NULL,
    class_id INT(10) UNSIGNED NOT NULL,
    section VARCHAR(30) DEFAULT NULL,
    report_type VARCHAR(30) NOT NULL DEFAULT 'grade_summary',
    status VARCHAR(20) NOT NULL DEFAULT 'submitted',
    payload_json LONGTEXT NOT NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_teacher_reports_teacher (teacher_username),
    KEY idx_teacher_reports_class (class_id),
    CONSTRAINT fk_teacher_reports_teacher FOREIGN KEY (teacher_username) REFERENCES teachers (username) ON DELETE CASCADE,
    CONSTRAINT fk_teacher_reports_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
";
if (!$conn->query($tableSql)) {
    sendResponse(false, 'Failed to prepare reports table: ' . $conn->error, null, 500);
}

$studentsStmt = $conn->prepare("SELECT COUNT(*) AS total_students FROM class_enrollments WHERE class_id = ?");
$studentsStmt->bind_param("i", $classId);
$studentsStmt->execute();
$totalStudents = (int)($studentsStmt->get_result()->fetch_assoc()['total_students'] ?? 0);

$gradedStudentsStmt = $conn->prepare("
    SELECT COUNT(DISTINCT fg.student_username) AS graded_students
    FROM final_grades fg
    WHERE fg.class_id = ?
");
$gradedStudentsStmt->bind_param("i", $classId);
$gradedStudentsStmt->execute();
$gradedStudents = (int)($gradedStudentsStmt->get_result()->fetch_assoc()['graded_students'] ?? 0);

$avgStmt = $conn->prepare("
    SELECT AVG(fg.total_marks) AS avg_marks
    FROM final_grades fg
    WHERE fg.class_id = ?
");
$avgStmt->bind_param("i", $classId);
$avgStmt->execute();
$avgMarks = (float)($avgStmt->get_result()->fetch_assoc()['avg_marks'] ?? 0);

$topStmt = $conn->prepare("
    SELECT fg.student_username, s.fname, s.mname, s.lname, ROUND(AVG(fg.total_marks),2) AS avg_score
    FROM final_grades fg
    JOIN students s ON s.username = fg.student_username
    WHERE fg.class_id = ?
    GROUP BY fg.student_username, s.fname, s.mname, s.lname
    ORDER BY avg_score DESC
    LIMIT 5
");
$topStmt->bind_param("i", $classId);
$topStmt->execute();
$topRes = $topStmt->get_result();
$topStudents = [];
while ($row = $topRes->fetch_assoc()) {
    $topStudents[] = [
        'student_username' => $row['student_username'],
        'full_name' => trim($row['fname'] . ' ' . ($row['mname'] ? $row['mname'] . ' ' : '') . $row['lname']),
        'avg_score' => (float)$row['avg_score']
    ];
}

$pendingStudents = max(0, $totalStudents - $gradedStudents);
$reportPayload = [
    'teacher_username' => $teacherUsername,
    'class_id' => $classId,
    'grade_level' => (string)$assignment['grade_level'],
    'section' => $section,
    'total_students' => $totalStudents,
    'graded_students' => $gradedStudents,
    'pending_students' => $pendingStudents,
    'class_average' => round($avgMarks, 2),
    'top_students' => $topStudents,
    'generated_at' => date('Y-m-d H:i:s')
];
$payloadJson = json_encode($reportPayload, JSON_UNESCAPED_UNICODE);

$insertStmt = $conn->prepare("
    INSERT INTO teacher_reports
        (teacher_username, class_id, section, report_type, status, payload_json, generated_at)
    VALUES
        (?, ?, ?, 'grade_summary', 'submitted', ?, NOW())
");
$insertStmt->bind_param("siss", $teacherUsername, $classId, $section, $payloadJson);
if (!$insertStmt->execute()) {
    sendResponse(false, 'Failed to save report: ' . $insertStmt->error, null, 500);
}

$reportId = (int)$insertStmt->insert_id;
logSystemActivity($conn, $teacherUsername, 'GENERATE_REPORT', 'Generated report #' . $reportId . ' for class ' . $classId, 'success');

sendResponse(true, 'Report generated and submitted successfully', [
    'report_id' => $reportId,
    'summary' => $reportPayload
], 201);

$conn->close();
?>
