<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    sendResponse(false, 'Unauthorized', null, 403);
}

$teacherUsername = $_SESSION['username'];
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$tableCheck = $conn->query("SHOW TABLES LIKE 'teacher_reports'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    sendResponse(true, 'No reports yet', [
        'reports' => [],
        'pagination' => [
            'current_page' => $page,
            'total_pages' => 0,
            'total_records' => 0,
            'records_per_page' => $limit
        ]
    ], 200);
}

$listStmt = $conn->prepare("
    SELECT tr.id, tr.class_id, tr.section, tr.report_type, tr.status, tr.payload_json, tr.generated_at,
           c.grade_level, c.section AS class_section
    FROM teacher_reports tr
    JOIN classes c ON c.id = tr.class_id
    WHERE tr.teacher_username = ?
    ORDER BY tr.generated_at DESC, tr.id DESC
    LIMIT ? OFFSET ?
");
$listStmt->bind_param("sii", $teacherUsername, $limit, $offset);
$listStmt->execute();
$res = $listStmt->get_result();

$reports = [];
while ($row = $res->fetch_assoc()) {
    $payload = json_decode((string)$row['payload_json'], true);
    if (!is_array($payload)) {
        $payload = [];
    }
    $reports[] = [
        'id' => (int)$row['id'],
        'class_id' => (int)$row['class_id'],
        'grade_level' => (string)$row['grade_level'],
        'section' => (string)($row['section'] ?: $row['class_section']),
        'report_type' => (string)$row['report_type'],
        'status' => (string)$row['status'],
        'generated_at' => (string)$row['generated_at'],
        'summary' => $payload
    ];
}

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM teacher_reports WHERE teacher_username = ?");
$countStmt->bind_param("s", $teacherUsername);
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$totalPages = (int)ceil($total / $limit);

sendResponse(true, 'Reports loaded successfully', [
    'reports' => $reports,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_records' => $total,
        'records_per_page' => $limit
    ]
], 200);

$conn->close();
?>
