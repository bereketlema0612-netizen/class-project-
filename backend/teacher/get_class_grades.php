<?php
require_once __DIR__ . '/common.php';
require_teacher(false);

$classId = (int)($_GET['class_id'] ?? 0);
$term = trim((string)($_GET['term'] ?? ''));
if ($classId <= 0) {
    respond(false, 'class_id required');
}

$grades = [];
if ($term !== '') {
    $stmt = $conn->prepare("SELECT id, student_username, term, subject, marks, letter_grade, entered_at
        FROM grades WHERE class_id = ? AND term = ? ORDER BY entered_at DESC");
    if ($stmt) {
        $stmt->bind_param('is', $classId, $term);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $grades[] = $row;
        $stmt->close();
    }
} else {
    $stmt = $conn->prepare("SELECT id, student_username, term, subject, marks, letter_grade, entered_at
        FROM grades WHERE class_id = ? ORDER BY entered_at DESC");
    if ($stmt) {
        $stmt->bind_param('i', $classId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $grades[] = $row;
        $stmt->close();
    }
}

respond(true, 'Class grades loaded', [
    'class_id' => $classId,
    'term' => ($term === '' ? 'All' : $term),
    'grades' => $grades,
    'total_grades' => count($grades)
]);
?>
