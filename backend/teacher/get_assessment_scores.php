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
$includeHistory = (int)($_GET['include_history'] ?? 0) === 1;

if ($classId <= 0 || $subjectInput === '') {
    sendResponse(false, 'class_id and subject are required', null, 400);
}

try {
    ensureAssessmentTables($conn);

    if (!teacherAssignedToClass($conn, $teacherUsername, $classId)) {
        sendResponse(false, 'Teacher not assigned to class', null, 403);
    }

    $subjectId = resolveSubjectId($conn, $subjectInput);
    $yearId = getClassAcademicYearId($conn, $classId);

    $classStmt = $conn->prepare("SELECT grade_level, section FROM classes WHERE id = ? LIMIT 1");
    $classStmt->bind_param("i", $classId);
    $classStmt->execute();
    $classRow = $classStmt->get_result()->fetch_assoc();
    if (!$classRow) {
        sendResponse(false, 'Class not found', null, 404);
    }
    $gradeKey = normalizeGradeLevelKey((string)$classRow['grade_level']);

    $structureStmt = $conn->prepare("
        SELECT id, total_points, status
        FROM assessment_structures
        WHERE teacher_username = ?
          AND class_id = ?
          AND subject_id = ?
          AND term = ?
          AND academic_year_id = ?
        LIMIT 1
    ");
    $structureStmt->bind_param("siisi", $teacherUsername, $classId, $subjectId, $term, $yearId);
    $structureStmt->execute();
    $structure = $structureStmt->get_result()->fetch_assoc();

    $studentsStmt = $conn->prepare("
        SELECT s.username AS student_username, s.fname, s.mname, s.lname
        FROM class_enrollments ce
        JOIN students s ON s.username = ce.student_username
        WHERE ce.class_id = ?
        ORDER BY s.fname, s.lname
    ");
    $studentsStmt->bind_param("i", $classId);
    $studentsStmt->execute();
    $studentsRes = $studentsStmt->get_result();
    $students = [];
    while ($s = $studentsRes->fetch_assoc()) {
        $fullName = trim($s['fname'] . ' ' . ($s['mname'] ? $s['mname'] . ' ' : '') . $s['lname']);
        $students[] = [
            'student_username' => $s['student_username'],
            'full_name' => $fullName === '' ? $s['student_username'] : $fullName
        ];
    }

    if (!$structure) {
        $historyCountStmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM assessment_structure_snapshots
            WHERE teacher_username = ? AND class_id = ? AND subject_id = ? AND term = ? AND academic_year_id = ?
        ");
        $historyCountStmt->bind_param("siisi", $teacherUsername, $classId, $subjectId, $term, $yearId);
        $historyCountStmt->execute();
        $historyCountRow = $historyCountStmt->get_result()->fetch_assoc();
        $historyCount = (int)($historyCountRow['total'] ?? 0);

        sendResponse(true, 'No active structure for selected class subject term', [
            'has_structure' => false,
            'class_id' => $classId,
            'grade_level' => $gradeKey,
            'section' => $classRow['section'],
            'subject_id' => $subjectId,
            'term' => $term,
            'students' => $students,
            'items' => [],
            'scores' => [],
            'history_count' => $historyCount,
            'history_tables' => []
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
    $itemIds = [];
    while ($i = $itemRes->fetch_assoc()) {
        $id = (int)$i['id'];
        $items[] = [
            'id' => $id,
            'name' => $i['item_name'],
            'max_points' => (float)$i['max_points'],
            'order' => (int)$i['item_order']
        ];
        $itemIds[] = $id;
    }

    $scores = [];
    $compactStmt = $conn->prepare("
        SELECT student_username, scores_json
        FROM student_assessment_compact_scores
        WHERE structure_id = ? AND class_id = ?
    ");
    $compactStmt->bind_param("ii", $structureId, $classId);
    $compactStmt->execute();
    $compactRes = $compactStmt->get_result();
    while ($row = $compactRes->fetch_assoc()) {
        $scores[$row['student_username']] = decodeStoredScores($row['scores_json']);
    }

    // Backward-compatible read for legacy row-per-item records.
    if (empty($scores) && !empty($itemIds)) {
        $in = implode(',', array_map('intval', $itemIds));
        $scoreSql = "
            SELECT structure_item_id, student_username, score
            FROM student_assessment_scores
            WHERE class_id = ? AND structure_item_id IN ($in)
        ";
        $scoreStmt = $conn->prepare($scoreSql);
        $scoreStmt->bind_param("i", $classId);
        $scoreStmt->execute();
        $scoreRes = $scoreStmt->get_result();
        while ($row = $scoreRes->fetch_assoc()) {
            $su = $row['student_username'];
            $itemId = (int)$row['structure_item_id'];
            if (!isset($scores[$su])) {
                $scores[$su] = [];
            }
            $scores[$su][(string)$itemId] = (float)$row['score'];
        }
    }

    $historyCountStmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM assessment_structure_snapshots
        WHERE teacher_username = ? AND class_id = ? AND subject_id = ? AND term = ? AND academic_year_id = ?
    ");
    $historyCountStmt->bind_param("siisi", $teacherUsername, $classId, $subjectId, $term, $yearId);
    $historyCountStmt->execute();
    $historyCountRow = $historyCountStmt->get_result()->fetch_assoc();
    $historyCount = (int)($historyCountRow['total'] ?? 0);

    $historyTables = [];
    if ($includeHistory && $historyCount > 0) {
        $historyStmt = $conn->prepare("
            SELECT id, total_points, status, created_at
            FROM assessment_structure_snapshots
            WHERE teacher_username = ? AND class_id = ? AND subject_id = ? AND term = ? AND academic_year_id = ?
            ORDER BY id DESC
        ");
        $historyStmt->bind_param("siisi", $teacherUsername, $classId, $subjectId, $term, $yearId);
        $historyStmt->execute();
        $historyRes = $historyStmt->get_result();
        while ($h = $historyRes->fetch_assoc()) {
            $snapshotId = (int)$h['id'];

            $historyItems = [];
            $hItemStmt = $conn->prepare("
                SELECT source_item_id, item_name, max_points, item_order
                FROM assessment_structure_snapshot_items
                WHERE snapshot_id = ?
                ORDER BY item_order ASC
            ");
            $hItemStmt->bind_param("i", $snapshotId);
            $hItemStmt->execute();
            $hItemRes = $hItemStmt->get_result();
            while ($it = $hItemRes->fetch_assoc()) {
                $historyItems[] = [
                    'source_item_id' => isset($it['source_item_id']) ? (int)$it['source_item_id'] : 0,
                    'name' => $it['item_name'],
                    'max_points' => (float)$it['max_points'],
                    'order' => (int)$it['item_order']
                ];
            }

            $historyScores = [];
            $hScoreStmt = $conn->prepare("
                SELECT student_username, scores_json
                FROM assessment_structure_snapshot_scores
                WHERE snapshot_id = ? AND class_id = ?
            ");
            $hScoreStmt->bind_param("ii", $snapshotId, $classId);
            $hScoreStmt->execute();
            $hScoreRes = $hScoreStmt->get_result();
            while ($sr = $hScoreRes->fetch_assoc()) {
                $historyScores[$sr['student_username']] = decodeStoredScores($sr['scores_json']);
            }

            $historyTables[] = [
                'snapshot_id' => $snapshotId,
                'label' => 'Previous structure #' . $snapshotId,
                'total_points' => (float)$h['total_points'],
                'status' => $h['status'],
                'created_at' => $h['created_at'],
                'items' => $historyItems,
                'scores' => $historyScores
            ];
        }
    }

    sendResponse(true, 'Assessment scores loaded', [
        'has_structure' => true,
        'structure' => [
            'id' => $structureId,
            'total_points' => (float)$structure['total_points'],
            'status' => $structure['status']
        ],
        'class_id' => $classId,
        'grade_level' => $gradeKey,
        'section' => $classRow['section'],
        'subject_id' => $subjectId,
        'term' => $term,
        'students' => $students,
        'items' => $items,
        'scores' => $scores,
        'history_count' => $historyCount,
        'history_tables' => $historyTables
    ], 200);
} catch (Exception $e) {
    sendResponse(false, 'Failed to load assessment scores: ' . $e->getMessage(), null, 500);
}

$conn->close();
?>
