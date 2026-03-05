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
$snapshotId = (int)($data['snapshot_id'] ?? 0);
$classId = (int)($data['class_id'] ?? 0);
$subjectInput = trim((string)($data['subject'] ?? ''));
$term = trim((string)($data['term'] ?? 'Term1'));

if ($classId <= 0 || $subjectInput === '') {
    sendResponse(false, 'class_id and subject are required', null, 400);
}

try {
    ensureAssessmentTables($conn);
    $subjectId = resolveSubjectId($conn, $subjectInput);
    $yearId = getClassAcademicYearId($conn, $classId);

    if ($snapshotId > 0) {
        $checkStmt = $conn->prepare("
            SELECT id
            FROM assessment_structure_snapshots
            WHERE id = ? AND teacher_username = ? AND class_id = ? AND subject_id = ? AND term = ? AND academic_year_id = ?
            LIMIT 1
        ");
        $checkStmt->bind_param("isiisi", $snapshotId, $teacherUsername, $classId, $subjectId, $term, $yearId);
        $checkStmt->execute();
        if (!$checkStmt->get_result()->fetch_assoc()) {
            sendResponse(false, 'Snapshot not found', null, 404);
        }

        $delStmt = $conn->prepare("DELETE FROM assessment_structure_snapshots WHERE id = ?");
        $delStmt->bind_param("i", $snapshotId);
        if (!$delStmt->execute()) {
            throw new Exception($delStmt->error);
        }
    } else {
        $delAllStmt = $conn->prepare("
            DELETE FROM assessment_structure_snapshots
            WHERE teacher_username = ? AND class_id = ? AND subject_id = ? AND term = ? AND academic_year_id = ?
        ");
        $delAllStmt->bind_param("siisi", $teacherUsername, $classId, $subjectId, $term, $yearId);
        if (!$delAllStmt->execute()) {
            throw new Exception($delAllStmt->error);
        }
    }

    sendResponse(true, 'Previous assessment table deleted', ['snapshot_id' => $snapshotId], 200);
} catch (Exception $e) {
    sendResponse(false, 'Failed to delete snapshot: ' . $e->getMessage(), null, 500);
}

$conn->close();
?>
