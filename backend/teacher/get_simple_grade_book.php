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
$classId = (int)($_GET['class_id'] ?? 0);
$subject = trim((string)($_GET['subject'] ?? ''));
$term = trim((string)($_GET['term'] ?? 'Term1'));

if ($classId <= 0 || $subject === '') {
    sendResponse(false, 'class_id and subject are required', null, 400);
}

$conn->query("
    CREATE TABLE IF NOT EXISTS teacher_grade_book (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        teacher_username VARCHAR(20) NOT NULL,
        class_id INT UNSIGNED NOT NULL,
        student_username VARCHAR(20) NOT NULL,
        subject VARCHAR(120) NOT NULL,
        term VARCHAR(20) NOT NULL,
        assignment_score DECIMAL(7,2) NOT NULL DEFAULT 0,
        mid_exam_score DECIMAL(7,2) NOT NULL DEFAULT 0,
        final_exam_score DECIMAL(7,2) NOT NULL DEFAULT 0,
        total_score DECIMAL(7,2) NOT NULL DEFAULT 0,
        letter_grade VARCHAR(5) NOT NULL DEFAULT 'F',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_teacher_grade_book (teacher_username, class_id, student_username, subject, term),
        KEY idx_tgb_class (class_id),
        KEY idx_tgb_student (student_username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

$stmt = $conn->prepare("
    SELECT student_username, assignment_score, mid_exam_score, final_exam_score, total_score, letter_grade
    FROM teacher_grade_book
    WHERE teacher_username = ? AND class_id = ? AND subject = ? AND term = ?
");
$stmt->bind_param("siss", $teacherUsername, $classId, $subject, $term);
$stmt->execute();
$res = $stmt->get_result();

$scores = [];
while ($row = $res->fetch_assoc()) {
    $scores[$row['student_username']] = [
        'assignment' => (float)$row['assignment_score'],
        'mid_exam' => (float)$row['mid_exam_score'],
        'final_exam' => (float)$row['final_exam_score'],
        'total' => (float)$row['total_score'],
        'letter_grade' => (string)$row['letter_grade']
    ];
}

sendResponse(true, 'Grade book loaded', [
    'scores' => $scores
], 200);

$conn->close();
?>
