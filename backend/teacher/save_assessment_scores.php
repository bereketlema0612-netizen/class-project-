<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';
require_once '../helpers/grading_backend.php';

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
$subjectInput = trim((string)($data['subject'] ?? ''));
$term = trim((string)($data['term'] ?? 'Term1'));
$rows = $data['rows'] ?? [];

if ($classId <= 0 || $subjectInput === '' || !is_array($rows) || count($rows) === 0) {
    sendResponse(false, 'class_id, subject, and rows are required', null, 400);
}

$validTerms = ['Term1', 'Term2', 'Term3', 'Term4', 'Term5'];
if (!in_array($term, $validTerms, true)) {
    sendResponse(false, 'Invalid term', null, 400);
}

try {
    ensureAssessmentTables($conn);

    if (!teacherAssignedToClass($conn, $teacherUsername, $classId)) {
        sendResponse(false, 'Teacher not assigned to class', null, 403);
    }

    $subjectId = resolveSubjectId($conn, $subjectInput);
    $academicYearId = getClassAcademicYearId($conn, $classId);

    $classStmt = $conn->prepare("SELECT grade_level FROM classes WHERE id = ? LIMIT 1");
    $classStmt->bind_param("i", $classId);
    $classStmt->execute();
    $classRow = $classStmt->get_result()->fetch_assoc();
    if (!$classRow) {
        sendResponse(false, 'Class not found', null, 404);
    }
    $gradeKey = normalizeGradeLevelKey((string)$classRow['grade_level']);

    $structureStmt = $conn->prepare("
        SELECT id, total_points
        FROM assessment_structures
        WHERE teacher_username = ?
          AND class_id = ?
          AND subject_id = ?
          AND term = ?
          AND academic_year_id = ?
        LIMIT 1
    ");
    $structureStmt->bind_param("siisi", $teacherUsername, $classId, $subjectId, $term, $academicYearId);
    $structureStmt->execute();
    $structure = $structureStmt->get_result()->fetch_assoc();
    if (!$structure) {
        sendResponse(false, 'Assessment structure not found. Please create Step 1 structure first.', null, 400);
    }
    $structureId = (int)$structure['id'];

    $itemStmt = $conn->prepare("
        SELECT id, max_points
        FROM assessment_structure_items
        WHERE structure_id = ?
    ");
    $itemStmt->bind_param("i", $structureId);
    $itemStmt->execute();
    $itemRes = $itemStmt->get_result();
    $itemMax = [];
    while ($i = $itemRes->fetch_assoc()) {
        $itemMax[(int)$i['id']] = (float)$i['max_points'];
    }
    if (empty($itemMax)) {
        sendResponse(false, 'Assessment structure has no items', null, 400);
    }

    $upsertCompactScore = $conn->prepare("
        INSERT INTO student_assessment_compact_scores
            (structure_id, class_id, student_username, scores_json, total_score, letter_grade, grading_scale_id, entered_by_teacher_username, entered_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            scores_json = VALUES(scores_json),
            total_score = VALUES(total_score),
            letter_grade = VALUES(letter_grade),
            grading_scale_id = VALUES(grading_scale_id),
            entered_by_teacher_username = VALUES(entered_by_teacher_username),
            updated_at = NOW()
    ");

    $upsertFinalGradeStmt = $conn->prepare("
        INSERT INTO final_grades
            (student_username, class_id, subject_id, term, academic_year_id, total_marks, letter_grade, teacher_username, structure_id, entered_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            total_marks = VALUES(total_marks),
            letter_grade = VALUES(letter_grade),
            teacher_username = VALUES(teacher_username),
            structure_id = VALUES(structure_id),
            updated_at = NOW()
    ");

    $conn->begin_transaction();
    $savedStudents = 0;
    foreach ($rows as $row) {
        $studentUsername = trim((string)($row['student_username'] ?? ''));
        $scores = $row['scores'] ?? [];
        if ($studentUsername === '' || !is_array($scores)) {
            continue;
        }
        if (!isStudentInClass($conn, $studentUsername, $classId)) {
            throw new Exception('Student not enrolled in class: ' . $studentUsername);
        }

        $total = 0.0;
        foreach ($scores as $itemIdRaw => $scoreRaw) {
            $itemId = (int)$itemIdRaw;
            if (!isset($itemMax[$itemId])) {
                continue;
            }
            $score = (float)$scoreRaw;
            if ($score < 0 || $score > $itemMax[$itemId]) {
                throw new Exception('Invalid score for student ' . $studentUsername . ', item ' . $itemId);
            }
            $total += $score;
        }

        $scale = resolveGradeScale($conn, $total);
        $normalizedScores = normalizeScoresForStorage($scores, $itemMax);
        $scoresJson = json_encode($normalizedScores);
        if ($scoresJson === false) {
            throw new Exception('Failed to serialize scores for student ' . $studentUsername);
        }

        $upsertCompactScore->bind_param("iissdsis", $structureId, $classId, $studentUsername, $scoresJson, $total, $scale['grade'], $scale['id'], $teacherUsername);
        if (!$upsertCompactScore->execute()) {
            throw new Exception($upsertCompactScore->error);
        }

        $upsertFinalGradeStmt->bind_param(
            "siisidssi",
            $studentUsername,
            $classId,
            $subjectId,
            $term,
            $academicYearId,
            $total,
            $scale['grade'],
            $teacherUsername,
            $structureId
        );
        if (!$upsertFinalGradeStmt->execute()) {
            throw new Exception($upsertFinalGradeStmt->error);
        }
        $savedStudents++;
    }

    $conn->commit();
    logSystemActivity($conn, $teacherUsername, 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class ' . $classId . ', subject ' . $subjectInput . ', term ' . $term, 'success');

    sendResponse(true, 'Assessment scores saved successfully', [
        'class_id' => $classId,
        'subject' => $subjectInput,
        'term' => $term,
        'saved_students' => $savedStudents
    ], 200);
} catch (Exception $e) {
    $conn->rollback();
    sendResponse(false, 'Failed to save assessment scores: ' . $e->getMessage(), null, 500);
}

$conn->close();
?>
