<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}
ensureAssignmentBlockColumn($conn);

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("
    SELECT u.id as user_id, t.username as employee_id_generated, t.fname, t.mname, t.lname, u.email, t.department, t.subject
    FROM teachers t
    JOIN users u ON t.username = u.username
    WHERE u.status = 'active'
    ORDER BY t.fname, t.lname
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$teachers = [];
while ($row = $result->fetch_assoc()) {
    $row['full_name'] = $row['fname'] . ' ' . ($row['mname'] ? $row['mname'] . ' ' : '') . $row['lname'];

    $classStmt = $conn->prepare("
        SELECT c.id, CONCAT('Grade ', c.grade_level, ' - ', c.section) AS name, c.grade_level, c.section
        FROM classes c
        JOIN assignments a ON c.id = a.class_id
        WHERE a.teacher_username = ? AND a.assignment_type = 'teacher' AND a.is_blocked = 0
    ");
    $classStmt->bind_param("s", $row['employee_id_generated']);
    $classStmt->execute();
    $classResult = $classStmt->get_result();
    $classes = [];
    while ($classRow = $classResult->fetch_assoc()) {
        $classes[] = $classRow;
    }

    $row['assigned_classes'] = $classes;
    $row['total_classes'] = count($classes);
    $teachers[] = $row;
}

$countStmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM teachers t
    JOIN users u ON t.username = u.username
    WHERE u.status = 'active'
");
$countStmt->execute();
$totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

sendResponse(true, 'Teachers retrieved successfully', [
    'teachers' => $teachers,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_records' => $totalRecords,
        'records_per_page' => $limit
    ]
], 200);

$stmt->close();
$countStmt->close();
$conn->close();
?>
