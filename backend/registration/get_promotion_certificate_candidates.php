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

$gradeRaw = sanitizeInput($_GET['grade'] ?? '');
$searchRaw = sanitizeInput($_GET['search'] ?? '');
$gradeDigits = preg_replace('/\D+/', '', $gradeRaw);
$search = trim($searchRaw);

$sql = "
    SELECT
        p.id AS promotion_id,
        p.student_username,
        p.from_grade,
        p.to_grade,
        p.promoted_date,
        p.remarks,
        s.fname,
        s.mname,
        s.lname
    FROM promotions p
    JOIN students s ON s.username = p.student_username
    WHERE 1=1
";

$types = '';
$params = [];

if ($gradeDigits !== '') {
    $sql .= " AND (p.from_grade = ? OR p.from_grade = CONCAT('Grade ', ?))";
    $types .= 'ss';
    $params[] = $gradeDigits;
    $params[] = $gradeDigits;
}

if ($search !== '') {
    $sql .= " AND (
        p.student_username LIKE CONCAT('%', ?, '%')
        OR s.fname LIKE CONCAT('%', ?, '%')
        OR s.mname LIKE CONCAT('%', ?, '%')
        OR s.lname LIKE CONCAT('%', ?, '%')
        OR CONCAT_WS(' ', s.fname, s.mname, s.lname) LIKE CONCAT('%', ?, '%')
    )";
    $types .= 'sssss';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

$sql .= " ORDER BY p.promoted_date DESC, p.id DESC";

if ($types === '') {
    $result = $conn->query($sql);
    if (!$result) {
        sendResponse(false, 'Failed to load promotion candidates: ' . $conn->error, null, 500);
    }
} else {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendResponse(false, 'Failed to prepare candidates query: ' . $conn->error, null, 500);
    }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        sendResponse(false, 'Failed to load promotion candidates: ' . $stmt->error, null, 500);
    }
    $result = $stmt->get_result();
}

$items = [];
while ($row = $result->fetch_assoc()) {
    $row['full_name'] = trim($row['fname'] . ' ' . ($row['mname'] ? $row['mname'] . ' ' : '') . $row['lname']);
    $items[] = $row;
}

sendResponse(true, 'Promotion certificate candidates retrieved', ['candidates' => $items], 200);
$conn->close();
?>
