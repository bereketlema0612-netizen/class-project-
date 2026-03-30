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

$teacherUsername = $_SESSION['username'];
$data = json_decode(file_get_contents('php://input'), true);

$classId = (int)($data['class_id'] ?? 0);
$subject = trim((string)($data['subject'] ?? ''));
$term = trim((string)($data['term'] ?? 'Term1'));
$rows = $data['rows'] ?? [];

if ($classId <= 0 || $subject === '' || !is_array($rows) || count($rows) === 0) {
    sendResponse(false, 'class_id, subject, and rows are required', null, 400);
}

function gradeLetterFromTotal(float $total): string {
    if ($total >= 90) return 'A+';
    if ($total >= 80) return 'A';
    if ($total >= 70) return 'B';
    if ($total >= 60) return 'C';
    if ($total >= 50) return 'D';
    return 'F';
}

$conn->begin_transaction();
try {
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

    $yearStmt = $conn->prepare("SELECT academic_year_id FROM classes WHERE id = ? LIMIT 1");
    $yearStmt->bind_param("i", $classId);
    $yearStmt->execute();
    $yearRow = $yearStmt->get_result()->fetch_assoc();
    $academicYearId = isset($yearRow['academic_year_id']) ? (int)$yearRow['academic_year_id'] : 0;
    if ($academicYearId <= 0) {
        $academicYearId = 1;
    }

    $subjectId = 0;
    $subStmt = $conn->prepare("SELECT id FROM subjects WHERE subject_name = ? LIMIT 1");
    $subStmt->bind_param("s", $subject);
    $subStmt->execute();
    $subRow = $subStmt->get_result()->fetch_assoc();
    if ($subRow && isset($subRow['id'])) {
        $subjectId = (int)$subRow['id'];
    }
    if ($subjectId <= 0) {
        throw new Exception('Selected subject not found');
    }

    $enrollCheck = $conn->prepare("SELECT 1 FROM class_enrollments WHERE class_id = ? AND student_username = ? LIMIT 1");
    $upsertBook = $conn->prepare("
        INSERT INTO teacher_grade_book
            (teacher_username, class_id, student_username, subject, term, assignment_score, mid_exam_score, final_exam_score, total_score, letter_grade, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            assignment_score = VALUES(assignment_score),
            mid_exam_score = VALUES(mid_exam_score),
            final_exam_score = VALUES(final_exam_score),
            total_score = VALUES(total_score),
            letter_grade = VALUES(letter_grade),
            updated_at = NOW()
    ");
    $upsertFinal = $conn->prepare("
        INSERT INTO final_grades
            (student_username, class_id, subject_id, term, academic_year_id, total_marks, letter_grade, teacher_username, structure_id, entered_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            total_marks = VALUES(total_marks),
            letter_grade = VALUES(letter_grade),
            teacher_username = VALUES(teacher_username),
            updated_at = NOW()
    ");

    $saved = 0;
    foreach ($rows as $row) {
        $studentUsername = trim((string)($row['student_username'] ?? ''));
        if ($studentUsername === '') {
            continue;
        }
        $assignment = (float)($row['assignment'] ?? 0);
        $midExam = (float)($row['mid_exam'] ?? 0);
        $finalExam = (float)($row['final_exam'] ?? 0);

        if ($assignment < 0 || $assignment > 10 || $midExam < 0 || $midExam > 30 || $finalExam < 0 || $finalExam > 60) {
            throw new Exception('Scores must be within Assignment(0-10), Mid(0-30), Final(0-60)');
        }

        $enrollCheck->bind_param("is", $classId, $studentUsername);
        $enrollCheck->execute();
        if ($enrollCheck->get_result()->num_rows === 0) {
            continue;
        }

        $total = $assignment + $midExam + $finalExam;
        $letter = gradeLetterFromTotal($total);

        $upsertBook->bind_param(
            "sisssdddds",
            $teacherUsername,
            $classId,
            $studentUsername,
            $subject,
            $term,
            $assignment,
            $midExam,
            $finalExam,
            $total,
            $letter
        );
        $upsertBook->execute();

        $upsertFinal->bind_param(
            "siisidss",
            $studentUsername,
            $classId,
            $subjectId,
            $term,
            $academicYearId,
            $total,
            $letter,
            $teacherUsername
        );
        $upsertFinal->execute();
        $saved++;
    }

    $conn->commit();
    sendResponse(true, 'Grades saved successfully', ['saved' => $saved], 200);
} catch (Throwable $e) {
    $conn->rollback();
    sendResponse(false, 'Failed to save grades: ' . $e->getMessage(), null, 500);
}

$conn->close();
?>
