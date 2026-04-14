<?php
require_once __DIR__ . '/common.php';
$teacher = require_teacher(false);

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
    subject_name VARCHAR(100) NULL,
    assignment_type VARCHAR(20) NOT NULL DEFAULT 'teacher',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS class_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_username VARCHAR(50) NOT NULL,
    class_id INT NOT NULL,
    enrollment_date DATE NULL
)");

$assigned = [];
$stmt = $conn->prepare("SELECT DISTINCT a.class_id, c.name
    FROM assignments a
    LEFT JOIN classes c ON c.id = a.class_id
    WHERE a.teacher_username = ? AND a.assignment_type = 'teacher'
    ORDER BY a.class_id ASC");

if ($stmt) {
    $stmt->bind_param('s', $teacher);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $id = (int)($row['class_id'] ?? 0);
        if ($id > 0) {
            $assigned[] = [
                'class_id' => $id,
                'name' => (string)($row['name'] ?? ('Class ' . $id))
            ];
        }
    }
    $stmt->close();
}

$totalStudents = 0;
$countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM class_enrollments WHERE class_id = ?");
if ($countStmt) {
    foreach ($assigned as $c) {
        $cid = (int)$c['class_id'];
        $countStmt->bind_param('i', $cid);
        $countStmt->execute();
        $row = $countStmt->get_result()->fetch_assoc();
        $totalStudents += (int)($row['c'] ?? 0);
    }
    $countStmt->close();
}

respond(true, 'Teacher dashboard loaded', [
    'statistics' => [
        'total_classes' => count($assigned),
        'total_students' => $totalStudents
    ],
    'assigned_classes' => $assigned
]);
?>
