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
$gradeLevelInput = trim((string)($data['grade_level'] ?? ''));
$subjectInput = trim((string)($data['subject'] ?? ''));
$term = trim((string)($data['term'] ?? 'Term1'));
$status = trim((string)($data['status'] ?? 'active'));
$academicYearIdInput = (int)($data['academic_year_id'] ?? 0);
$items = $data['items'] ?? [];

if ($classId <= 0 || $subjectInput === '') {
    sendResponse(false, 'class_id and subject are required', null, 400);
}
if (!is_array($items) || count($items) === 0) {
    sendResponse(false, 'At least one assessment item is required', null, 400);
}

$validTerms = ['Term1', 'Term2', 'Term3', 'Term4', 'Term5'];
if (!in_array($term, $validTerms, true)) {
    sendResponse(false, 'Invalid term', null, 400);
}

$validStatus = ['draft', 'active', 'closed'];
if (!in_array($status, $validStatus, true)) {
    sendResponse(false, 'Invalid status', null, 400);
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

    $cleanItems = [];
    $order = 1;
    $totalPoints = 0.0;
    foreach ($items as $item) {
        $name = trim((string)($item['name'] ?? ''));
        $points = (float)($item['points'] ?? $item['max_points'] ?? 0);
        if ($name === '' || $points <= 0) {
            continue;
        }
        $cleanItems[] = [
            'name' => $name,
            'points' => $points,
            'order' => $order++
        ];
        $totalPoints += $points;
    }

    if (count($cleanItems) === 0) {
        sendResponse(false, 'All assessment items are invalid', null, 400);
    }

    $conn->begin_transaction();

    $findStmt = $conn->prepare("
        SELECT id
        FROM assessment_structures
        WHERE teacher_username = ?
          AND class_id = ?
          AND subject_id = ?
          AND term = ?
          AND academic_year_id = ?
        LIMIT 1
    ");
    $findStmt->bind_param("siisi", $teacherUsername, $classId, $subjectId, $term, $academicYearId);
    $findStmt->execute();
    $existing = $findStmt->get_result()->fetch_assoc();

    if ($existing) {
        $structureId = (int)$existing['id'];

        // Snapshot the current structure, items, and saved scores before overwriting.
        $snapshotStmt = $conn->prepare("
            INSERT INTO assessment_structure_snapshots
                (structure_id, teacher_username, grade_level, class_id, subject_id, term, academic_year_id, total_points, status, snapshot_reason)
            SELECT id, teacher_username, grade_level, class_id, subject_id, term, academic_year_id, total_points, status, 'structure_update'
            FROM assessment_structures
            WHERE id = ?
        ");
        $snapshotStmt->bind_param("i", $structureId);
        if (!$snapshotStmt->execute()) {
            throw new Exception($snapshotStmt->error);
        }
        $snapshotId = (int)$snapshotStmt->insert_id;

        $snapshotItemsStmt = $conn->prepare("
            INSERT INTO assessment_structure_snapshot_items (snapshot_id, source_item_id, item_name, max_points, item_order)
            SELECT ?, id, item_name, max_points, item_order
            FROM assessment_structure_items
            WHERE structure_id = ?
            ORDER BY item_order ASC
        ");
        $snapshotItemsStmt->bind_param("ii", $snapshotId, $structureId);
        if (!$snapshotItemsStmt->execute()) {
            throw new Exception($snapshotItemsStmt->error);
        }

        $snapshotScoresStmt = $conn->prepare("
            INSERT INTO assessment_structure_snapshot_scores
                (snapshot_id, class_id, student_username, scores_json, total_score, letter_grade, grading_scale_id, entered_by_teacher_username, entered_at, updated_at)
            SELECT ?, class_id, student_username, scores_json, total_score, letter_grade, grading_scale_id, entered_by_teacher_username, entered_at, updated_at
            FROM student_assessment_compact_scores
            WHERE structure_id = ? AND class_id = ?
        ");
        $snapshotScoresStmt->bind_param("iii", $snapshotId, $structureId, $classId);
        if (!$snapshotScoresStmt->execute()) {
            throw new Exception($snapshotScoresStmt->error);
        }

        $upStmt = $conn->prepare("
            UPDATE assessment_structures
            SET grade_level = ?, total_points = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $upStmt->bind_param("sdsi", $gradeKey, $totalPoints, $status, $structureId);
        if (!$upStmt->execute()) {
            throw new Exception($upStmt->error);
        }

        $delStmt = $conn->prepare("DELETE FROM assessment_structure_items WHERE structure_id = ?");
        $delStmt->bind_param("i", $structureId);
        if (!$delStmt->execute()) {
            throw new Exception($delStmt->error);
        }
    } else {
        $insStmt = $conn->prepare("
            INSERT INTO assessment_structures
                (teacher_username, grade_level, class_id, subject_id, term, academic_year_id, total_points, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insStmt->bind_param("ssiisids", $teacherUsername, $gradeKey, $classId, $subjectId, $term, $academicYearId, $totalPoints, $status);
        if (!$insStmt->execute()) {
            throw new Exception($insStmt->error);
        }
        $structureId = (int)$insStmt->insert_id;
    }

    $itemInsert = $conn->prepare("
        INSERT INTO assessment_structure_items (structure_id, item_name, max_points, item_order)
        VALUES (?, ?, ?, ?)
    ");

    $savedItems = [];
    foreach ($cleanItems as $item) {
        $itemInsert->bind_param("isdi", $structureId, $item['name'], $item['points'], $item['order']);
        if (!$itemInsert->execute()) {
            throw new Exception($itemInsert->error);
        }
        $savedItems[] = [
            'id' => (int)$itemInsert->insert_id,
            'name' => $item['name'],
            'max_points' => (float)$item['points'],
            'order' => (int)$item['order']
        ];
    }

    $conn->commit();

    logSystemActivity($conn, $teacherUsername, 'ASSESSMENT_STRUCTURE_SAVE', 'Saved assessment structure for grade ' . $gradeKey . ', subject ' . $subjectInput . ', term ' . $term, 'success');

    sendResponse(true, 'Assessment structure saved', [
        'structure' => [
            'id' => $structureId,
            'teacher_username' => $teacherUsername,
            'class_id' => $classId,
            'grade_level' => $gradeKey,
            'subject_id' => $subjectId,
            'term' => $term,
            'academic_year_id' => $academicYearId,
            'total_points' => $totalPoints,
            'status' => $status
        ],
        'items' => $savedItems
    ], 200);
} catch (Exception $e) {
    if ($conn->errno) {
        $conn->rollback();
    }
    sendResponse(false, 'Failed to save assessment structure: ' . $e->getMessage(), null, 500);
}

$conn->close();
?>
