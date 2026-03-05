<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$gradeFilterRaw = isset($_GET['grade']) ? sanitizeInput($_GET['grade']) : '';
$gradeDigits = preg_replace('/\D+/', '', $gradeFilterRaw);
$streamFilterRaw = isset($_GET['stream']) ? sanitizeInput($_GET['stream']) : '';
$streamFilter = strtolower(trim($streamFilterRaw));
if (!in_array($streamFilter, ['', 'natural', 'social'], true)) {
    $streamFilter = '';
}

$sql = "
    SELECT
        c.id,
        c.grade_level,
        c.section,
        c.stream,
        c.teacher_username,
        c.academic_year_id,
        CONCAT('Grade ', c.grade_level, ' - ', c.section) AS display_name
    FROM classes c
    WHERE 1=1
";
$types = '';
$params = [];
if ($gradeDigits !== '') {
    $sql .= " AND (c.grade_level = ? OR c.grade_level = CONCAT('Grade ', ?))";
    $types = 'ss';
    $params = [$gradeDigits, $gradeDigits];
}
if ($streamFilter !== '') {
    $sql .= " AND c.stream = ?";
    $types .= 's';
    $params[] = $streamFilter;
}
$sql .= " ORDER BY c.grade_level, c.section, c.id";

if ($types === '') {
    $result = $conn->query($sql);
    if (!$result) {
        sendResponse(false, 'Failed to load classes: ' . $conn->error, null, 500);
    }
} else {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendResponse(false, 'Failed to prepare classes query: ' . $conn->error, null, 500);
    }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        sendResponse(false, 'Failed to load classes: ' . $stmt->error, null, 500);
    }
    $result = $stmt->get_result();
}

$classes = [];
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}

sendResponse(true, 'Classes retrieved', ['classes' => $classes], 200);
$conn->close();
?>
