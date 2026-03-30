<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$classId = (int)($_GET['class_id'] ?? 0);
$term = trim((string)($_GET['term'] ?? ''));
if ($classId <= 0) {
    echo json_encode(['success' => false, 'message' => 'class_id required', 'data' => null]);
    exit;
}

$grades = [];
$tbl = $conn->query("SHOW TABLES LIKE 'grades'");
if ($tbl && $tbl->num_rows > 0) {
    if ($term !== '') {
        $stmt = $conn->prepare("SELECT id, student_username, term, subject, marks, letter_grade, entered_at FROM grades WHERE class_id = ? AND term = ? ORDER BY entered_at DESC");
        if ($stmt) {
            $stmt->bind_param('is', $classId, $term);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) $grades[] = $row;
            $stmt->close();
        }
    } else {
        $stmt = $conn->prepare("SELECT id, student_username, term, subject, marks, letter_grade, entered_at FROM grades WHERE class_id = ? ORDER BY entered_at DESC");
        if ($stmt) {
            $stmt->bind_param('i', $classId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) $grades[] = $row;
            $stmt->close();
        }
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Class grades loaded',
    'data' => [
        'class_id' => $classId,
        'term' => $term === '' ? 'All' : $term,
        'grades' => $grades,
        'total_grades' => count($grades)
    ]
]);
?>
