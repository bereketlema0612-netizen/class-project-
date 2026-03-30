<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST only', 'data' => null]);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_username VARCHAR(50) NOT NULL,
    class_id INT NOT NULL,
    teacher_username VARCHAR(50) NOT NULL,
    term VARCHAR(20) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    marks DECIMAL(6,2) NOT NULL DEFAULT 0,
    letter_grade VARCHAR(3) NOT NULL,
    entered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_grade (student_username, class_id, term, subject)
)");

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON', 'data' => null]);
    exit;
}

$studentInput = trim((string)($data['student_id'] ?? $data['student_username'] ?? ''));
$classId = (int)($data['class_id'] ?? 0);
$term = trim((string)($data['term'] ?? 'Term1'));
$subject = trim((string)($data['subject'] ?? 'General'));
$marks = (float)($data['marks'] ?? 0);

$hasBreakdown =
    array_key_exists('assignment_marks', $data) ||
    array_key_exists('mid_marks', $data) ||
    array_key_exists('final_marks', $data);

$assignmentMarks = 0.0;
$midMarks = 0.0;
$finalMarks = 0.0;

if ($studentInput === '' || $classId <= 0 || $subject === '') {
    echo json_encode(['success' => false, 'message' => 'student, class_id, subject required', 'data' => null]);
    exit;
}

if ($hasBreakdown) {
    $assignmentMarks = (float)($data['assignment_marks'] ?? 0);
    $midMarks = (float)($data['mid_marks'] ?? 0);
    $finalMarks = (float)($data['final_marks'] ?? 0);

    if ($assignmentMarks < 0 || $assignmentMarks > 10) {
        echo json_encode(['success' => false, 'message' => 'Assignment mark must be between 0 and 10', 'data' => null]);
        exit;
    }
    if ($midMarks < 0 || $midMarks > 30) {
        echo json_encode(['success' => false, 'message' => 'Mid exam mark must be between 0 and 30', 'data' => null]);
        exit;
    }
    if ($finalMarks < 0 || $finalMarks > 60) {
        echo json_encode(['success' => false, 'message' => 'Final exam mark must be between 0 and 60', 'data' => null]);
        exit;
    }

    $marks = $assignmentMarks + $midMarks + $finalMarks;
}

$studentUsername = $studentInput;
if (ctype_digit($studentInput)) {
    $sid = (int)$studentInput;
    $u = $conn->prepare("SELECT username FROM users WHERE id = ? AND role = 'student' LIMIT 1");
    if ($u) {
        $u->bind_param('i', $sid);
        $u->execute();
        $row = $u->get_result()->fetch_assoc();
        if ($row) $studentUsername = (string)$row['username'];
        $u->close();
    }
}

if ($marks < 0) $marks = 0;
if ($marks > 100) $marks = 100;

$letter = 'F';
if ($marks >= 90) $letter = 'A';
else if ($marks >= 80) $letter = 'B';
else if ($marks >= 70) $letter = 'C';
else if ($marks >= 60) $letter = 'D';

$teacher = (string)$_SESSION['username'];
$stmt = $conn->prepare("INSERT INTO grades (student_username, class_id, teacher_username, term, subject, marks, letter_grade) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE teacher_username = VALUES(teacher_username), marks = VALUES(marks), letter_grade = VALUES(letter_grade), entered_at = NOW()");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB error', 'data' => null]);
    exit;
}

$stmt->bind_param('sisssds', $studentUsername, $classId, $teacher, $term, $subject, $marks, $letter);
$stmt->execute();

echo json_encode([
    'success' => true,
    'message' => 'Grade saved',
    'data' => [
        'student_username' => $studentUsername,
        'class_id' => $classId,
        'subject' => $subject,
        'term' => $term,
        'assignment_marks' => $assignmentMarks,
        'mid_marks' => $midMarks,
        'final_marks' => $finalMarks,
        'marks' => $marks,
        'letter_grade' => $letter
    ]
]);
?>
