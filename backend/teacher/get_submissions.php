<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';
require_once '../helpers/resource_submission_tables.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'teacher') {
    sendResponse(false, 'Unauthorized', null, 403);
}

$teacherUsername = (string)$_SESSION['username'];
$classId = (int)($_GET['class_id'] ?? 0);
$status = strtolower(trim((string)($_GET['status'] ?? '')));
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

try {
    ensure_resource_submission_schema($conn);
} catch (Exception $e) {
    sendResponse(false, $e->getMessage(), null, 500);
}

$where = "WHERE srs.teacher_username = ?";
$types = 's';
$params = [$teacherUsername];

if ($classId > 0) {
    $where .= " AND srs.class_id = ?";
    $types .= 'i';
    $params[] = $classId;
}

if ($status !== '' && in_array($status, ['submitted', 'seen', 'graded'], true)) {
    $where .= " AND srs.status = ?";
    $types .= 's';
    $params[] = $status;
}

$countTypes = $types;
$countParams = $params;

$sql = "
    SELECT
        srs.id, srs.resource_id, srs.student_username, srs.teacher_username, srs.class_id, srs.notes,
        srs.file_path, srs.file_name, srs.file_mime, srs.file_size, srs.status, srs.submitted_at, srs.updated_at, srs.seen_at,
        lr.title AS resource_title, lr.resource_type, lr.due_date, lr.target_class_ids,
        st.fname AS student_fname, st.lname AS student_lname,
        c.grade_level, c.section
    FROM student_resource_submissions srs
    JOIN learning_resources lr ON lr.id = srs.resource_id
    LEFT JOIN students st ON st.username = srs.student_username
    LEFT JOIN classes c ON c.id = srs.class_id
    {$where}
    ORDER BY srs.submitted_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    sendResponse(false, 'Failed to prepare submissions query: ' . $conn->error, null, 500);
}
$typesWithPage = $types . 'ii';
$params[] = $limit;
$params[] = $offset;
$stmt->bind_param($typesWithPage, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) {
    $row['student_name'] = trim(((string)($row['student_fname'] ?? '')) . ' ' . ((string)($row['student_lname'] ?? '')));
    $row['class_name'] = ($row['grade_level'] !== null && $row['section'] !== null)
        ? ('Grade ' . $row['grade_level'] . ' - ' . $row['section'])
        : '-';
    unset($row['student_fname'], $row['student_lname']);
    $rows[] = $row;
}
$stmt->close();

$countSql = "SELECT COUNT(*) AS total FROM student_resource_submissions srs {$where}";
$countStmt = $conn->prepare($countSql);
if (!$countStmt) {
    sendResponse(false, 'Failed to prepare submissions count query: ' . $conn->error, null, 500);
}
$countStmt->bind_param($countTypes, ...$countParams);
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

sendResponse(true, 'Submissions retrieved successfully', [
    'submissions' => $rows,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $limit > 0 ? (int)ceil($total / $limit) : 1,
        'total_records' => $total,
        'records_per_page' => $limit
    ]
], 200);
