<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';
require_once '../helpers/grading_backend.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    sendResponse(false, 'Unauthorized', null, 403);
}

$teacherUsername = $_SESSION['username'];

try {
    ensureAssessmentTables($conn);
    ensureAssignmentBlockColumn($conn);

    $stmt = $conn->prepare("
        SELECT DISTINCT c.id AS class_id, c.grade_level, c.section, c.academic_year_id
        FROM assignments a
        JOIN classes c ON c.id = a.class_id
        WHERE a.teacher_username = ? AND a.assignment_type = 'teacher' AND a.is_blocked = 0
        ORDER BY c.grade_level, c.section
    ");
    $stmt->bind_param("s", $teacherUsername);
    $stmt->execute();
    $res = $stmt->get_result();

    $gradesMap = [];
    while ($row = $res->fetch_assoc()) {
        $gradeKey = normalizeGradeLevelKey((string)$row['grade_level']);
        if ($gradeKey === '') {
            continue;
        }
        if (!isset($gradesMap[$gradeKey])) {
            $gradesMap[$gradeKey] = [
                'grade_level' => $gradeKey,
                'sections' => [],
                'class_ids' => []
            ];
        }
        $section = strtoupper(trim((string)($row['section'] ?? '')));
        if ($section !== '' && !in_array($section, $gradesMap[$gradeKey]['sections'], true)) {
            $gradesMap[$gradeKey]['sections'][] = $section;
        }
        $classId = (int)$row['class_id'];
        if (!in_array($classId, $gradesMap[$gradeKey]['class_ids'], true)) {
            $gradesMap[$gradeKey]['class_ids'][] = $classId;
        }
    }

    foreach ($gradesMap as $gradeKey => $gradeData) {
        $subjects = [];
        foreach ($gradeData['class_ids'] as $classId) {
            $subjectStmt = $conn->prepare("
                SELECT DISTINCT s.subject_name
                FROM assignments a
                LEFT JOIN subjects s ON s.id = a.subject_id
                WHERE a.class_id = ? AND a.teacher_username = ? AND a.subject_id IS NOT NULL AND a.is_blocked = 0
            ");
            $subjectStmt->bind_param("is", $classId, $teacherUsername);
            $subjectStmt->execute();
            $sRes = $subjectStmt->get_result();
            while ($sRow = $sRes->fetch_assoc()) {
                $name = trim((string)($sRow['subject_name'] ?? ''));
                if ($name !== '' && !in_array($name, $subjects, true)) {
                    $subjects[] = $name;
                }
            }
        }
        sort($subjects);
        sort($gradesMap[$gradeKey]['sections']);
        $gradesMap[$gradeKey]['subjects'] = $subjects;
    }

    $grades = array_values($gradesMap);
    usort($grades, fn($a, $b) => strcmp((string)$a['grade_level'], (string)$b['grade_level']));

    sendResponse(true, 'Grading setup options retrieved', [
        'teacher_username' => $teacherUsername,
        'grades' => $grades
    ], 200);
} catch (Exception $e) {
    sendResponse(false, 'Failed to load grading setup options: ' . $e->getMessage(), null, 500);
}

$conn->close();
?>
