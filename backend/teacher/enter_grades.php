<?php
require_once __DIR__ . '/common.php';
$teacher = require_teacher(true);

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

$data = read_json_body();
$student = trim((string)($data['student_username'] ?? $data['student_id'] ?? ''));
$classId = (int)($data['class_id'] ?? 0);
$term = trim((string)($data['term'] ?? 'Term1'));
$subject = trim((string)($data['subject'] ?? 'General'));

$ass = (float)($data['assignment_marks'] ?? 0);
$mid = (float)($data['mid_marks'] ?? 0);
$fin = (float)($data['final_marks'] ?? 0);
$marks = (float)($data['marks'] ?? ($ass + $mid + $fin));

if ($student === '' || $classId <= 0 || $subject === '') {
    respond(false, 'student, class_id, subject required');
}

if ($ass < 0 || $ass > 10 || $mid < 0 || $mid > 30 || $fin < 0 || $fin > 60) {
    respond(false, 'Invalid score range');
}

if ($marks < 0) $marks = 0;
if ($marks > 100) $marks = 100;

$letter = 'F';
if ($marks >= 90) $letter = 'A';
else if ($marks >= 80) $letter = 'B';
else if ($marks >= 70) $letter = 'C';
else if ($marks >= 60) $letter = 'D';

$stmt = $conn->prepare("INSERT INTO grades
    (student_username, class_id, teacher_username, term, subject, marks, letter_grade)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
    teacher_username = VALUES(teacher_username),
    marks = VALUES(marks),
    letter_grade = VALUES(letter_grade),
    entered_at = NOW()");

if (!$stmt) {
    respond(false, 'DB error');
}

$stmt->bind_param('sisssds', $student, $classId, $teacher, $term, $subject, $marks, $letter);
$stmt->execute();
$stmt->close();

respond(true, 'Grade saved', [
    'student_username' => $student,
    'class_id' => $classId,
    'term' => $term,
    'subject' => $subject,
    'assignment_marks' => $ass,
    'mid_marks' => $mid,
    'final_marks' => $fin,
    'marks' => $marks,
    'letter_grade' => $letter
]);
?>
