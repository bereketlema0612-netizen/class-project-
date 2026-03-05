<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'director') {
    sendResponse(false, 'Unauthorized access', null, 403);
}

$role = isset($_GET['role']) ? sanitizeInput($_GET['role']) : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

$query = "SELECT u.id, u.username, u.email, u.role, u.status, u.created_at,
COALESCE(s.fname, t.fname, ra.fname, d.fname) as fname,
COALESCE(s.mname, t.mname, ra.mname, d.mname) as mname,
COALESCE(s.lname, t.lname, ra.lname, d.lname) as lname
FROM users u
LEFT JOIN students s ON s.username = u.username
LEFT JOIN teachers t ON t.username = u.username
LEFT JOIN admins ra ON ra.username = u.username
LEFT JOIN directors d ON d.username = u.username
WHERE 1=1";

if ($role && $role !== 'all') {
    $query .= " AND u.role = '" . $conn->real_escape_string($role) . "'";
}

$query .= " ORDER BY u.created_at DESC LIMIT " . $offset . ", " . $limit;

$result = $conn->query($query);
if (!$result) {
    sendResponse(false, 'Query failed: ' . $conn->error, null, 500);
}

$users = [];
while ($row = $result->fetch_assoc()) {
    $row['full_name'] = $row['fname'] . ' ' . ($row['mname'] ? $row['mname'] . ' ' : '') . $row['lname'];
    
    if ($row['role'] === 'student') {
        $stmt = $conn->prepare("SELECT grade_level FROM students WHERE username = ?");
        $stmt->bind_param("s", $row['username']);
        $stmt->execute();
        $gradeResult = $stmt->get_result();
        if ($gradeResult->num_rows > 0) {
            $gradeRow = $gradeResult->fetch_assoc();
            $row['grade_level'] = $gradeRow['grade_level'];
        }
    } elseif ($row['role'] === 'teacher') {
        $stmt = $conn->prepare("SELECT department, subject FROM teachers WHERE username = ?");
        $stmt->bind_param("s", $row['username']);
        $stmt->execute();
        $deptResult = $stmt->get_result();
        if ($deptResult->num_rows > 0) {
            $deptRow = $deptResult->fetch_assoc();
            $row['department'] = $deptRow['department'];
            $row['subject'] = $deptRow['subject'];
        }
    }
    
    $users[] = $row;
}

$countQuery = "SELECT COUNT(*) as total FROM users u WHERE 1=1";
if ($role && $role !== 'all') {
    $countQuery .= " AND u.role = '" . $conn->real_escape_string($role) . "'";
}

$countResult = $conn->query($countQuery);
$countRow = $countResult->fetch_assoc();
$totalRecords = $countRow['total'];
$totalPages = ceil($totalRecords / $limit);

sendResponse(true, 'Users retrieved successfully', [
    'users' => $users,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_records' => $totalRecords,
        'records_per_page' => $limit
    ]
], 200);

$conn->close();
?>
