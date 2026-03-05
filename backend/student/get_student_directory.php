<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;
$gradeFilter = isset($_GET['grade']) ? sanitizeInput($_GET['grade']) : null;

$query = "
    SELECT 
        u.id, s.fname, s.mname, s.lname, u.email,
        s.username as student_id_generated, s.grade_level, s.stream, s.age, s.sex
    FROM users u
    JOIN students s ON u.username = s.username
    WHERE u.role = 'student' AND u.status = 'active'
";

if ($gradeFilter) {
    $query .= " AND s.grade_level = '" . $conn->real_escape_string($gradeFilter) . "'";
}

$query .= " ORDER BY s.grade_level ASC, s.fname ASC LIMIT " . $offset . ", " . $limit;

$result = $conn->query($query);

if (!$result) {
    sendResponse(false, 'Query failed: ' . $conn->error, null, 500);
}

$students = [];
while ($row = $result->fetch_assoc()) {
    $row['full_name'] = $row['fname'] . ' ' . ($row['mname'] ? $row['mname'] . ' ' : '') . $row['lname'];
    $students[] = $row;
}

$countQuery = "SELECT COUNT(*) as total FROM users u JOIN students s ON u.username = s.username WHERE u.role = 'student' AND u.status = 'active'";
if ($gradeFilter) {
    $countQuery .= " AND s.grade_level = '" . $conn->real_escape_string($gradeFilter) . "'";
}

$countResult = $conn->query($countQuery);
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

sendResponse(true, 'Students directory retrieved', [
    'students' => $students,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_records' => $totalRecords,
        'records_per_page' => $limit
    ]
], 200);

$conn->close();
?>
