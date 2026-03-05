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
$classId = (int)($_GET['class_id'] ?? 0);
$subjectInput = trim((string)($_GET['subject'] ?? ''));
$term = trim((string)($_GET['term'] ?? 'Term1'));
$academicYearIdInput = (int)($_GET['academic_year_id'] ?? 0);

if ($classId <= 0 || $subjectInput === '') {
    sendResponse(false, 'class_id and subject are required', null, 400);
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

    $classStmt = $conn->prepare("SELECT grade_level FROM classes WHERE id = ? LIMIT 1");
    $classStmt->bind_param("i", $classId);
    $classStmt->execute();
    $classRow = $classStmt->get_result()->fetch_assoc();
    if (!$classRow) {
        sendResponse(false, 'Class not found', null, 404);
    }
    $gradeKey = normalizeGradeLevelKey((string)$classRow['grade_level']);
    if ($gradeKey === '') {
        sendResponse(false, 'Invalid class grade level', null, 400);
    }

    $subjectId = resolveSubjectId($conn, $subjectInput);
    $academicYearId = $academicYearIdInput > 0 ? $academicYearIdInput : getActiveAcademicYearId($conn);

    $stmt = $conn->prepare("
        SELECT id, total_points, status, created_at, updated_at
        FROM assessment_structures
        WHERE teacher_username = ?
          AND class_id = ?
          AND subject_id = ?
          AND term = ?
          AND academic_year_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("siisi", $teacherUsername, $classId, $subjectId, $term, $academicYearId);
    $stmt->execute();
    $structure = $stmt->get_result()->fetch_assoc();

    if (!$structure) {
        sendResponse(true, 'No assessment structure found', [
            'exists' => false,
            'structure' => null,
            'items' => []
        ], 200);
    }

    $structureId = (int)$structure['id'];
    $itemStmt = $conn->prepare("
        SELECT id, item_name, max_points, item_order
        FROM assessment_structure_items
        WHERE structure_id = ?
        ORDER BY item_order ASC
    ");
    $itemStmt->bind_param("i", $structureId);
    $itemStmt->execute();
    $itemRes = $itemStmt->get_result();
    $items = [];
    while ($row = $itemRes->fetch_assoc()) {
        $items[] = [
            'id' => (int)$row['id'],
            'name' => $row['item_name'],
            'max_points' => (float)$row['max_points'],
            'order' => (int)$row['item_order']
        ];
    }

    sendResponse(true, 'Assessment structure retrieved', [
        'exists' => true,
        'structure' => [
            'id' => $structureId,
            'class_id' => $classId,
            'grade_level' => $gradeKey,
            'subject_id' => $subjectId,
            'term' => $term,
            'academic_year_id' => $academicYearId,
            'total_points' => (float)$structure['total_points'],
            'status' => $structure['status'],
            'created_at' => $structure['created_at'],
            'updated_at' => $structure['updated_at']
        ],
        'items' => $items
    ], 200);
} catch (Exception $e) {
    sendResponse(false, 'Failed to load assessment structure: ' . $e->getMessage(), null, 500);
}

$conn->close();
?>
