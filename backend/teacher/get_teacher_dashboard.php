<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$username = (string)$_SESSION['username'];

$conn->query("CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    grade_level VARCHAR(20) NULL,
    section VARCHAR(20) NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    teacher_username VARCHAR(50) NOT NULL,
    assignment_type VARCHAR(20) NOT NULL DEFAULT 'teacher',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS class_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_username VARCHAR(50) NOT NULL,
    class_id INT NOT NULL,
    enrollment_date DATE NULL
)");

$totalClasses = 0;
$totalStudents = 0;
$assignedClasses = [];

$sql = "SELECT DISTINCT a.class_id, c.name
        FROM assignments a
        LEFT JOIN classes c ON c.id = a.class_id
        WHERE a.teacher_username = ? AND a.assignment_type = 'teacher'
        ORDER BY a.class_id ASC";
$st = $conn->prepare($sql);
if ($st) {
    $st->bind_param('s', $username);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $cid = (int)($row['class_id'] ?? 0);
        if ($cid > 0) {
            $assignedClasses[] = [
                'class_id' => $cid,
                'name' => (string)($row['name'] ?? ('Class ' . $cid))
            ];
        }
    }
    $st->close();
}

$totalClasses = count($assignedClasses);

if ($totalClasses > 0) {
    $classIds = array_map(function ($c) { return (int)$c['class_id']; }, $assignedClasses);
    $classIds = array_values(array_filter($classIds, function ($v) { return $v > 0; }));
    if (count($classIds) > 0) {
        $in = implode(',', $classIds);
        $resCount = $conn->query("SELECT COUNT(*) AS c FROM class_enrollments WHERE class_id IN ($in)");
        if ($resCount) {
            $row = $resCount->fetch_assoc();
            $totalStudents = (int)($row['c'] ?? 0);
        }
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Teacher dashboard loaded',
    'data' => [
        'statistics' => [
            'total_classes' => $totalClasses,
            'total_students' => $totalStudents
        ],
        'assigned_classes' => $assignedClasses
    ]
]);
?>
