<?php
require_once __DIR__ . '/common.php';
require_teacher(false);

$classId = (int)($_GET['class_id'] ?? 0);
if ($classId <= 0) {
    respond(false, 'class_id required');
}

$conn->query("CREATE TABLE IF NOT EXISTS class_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_username VARCHAR(50) NOT NULL,
    class_id INT NOT NULL,
    enrollment_date DATE NULL
)");

$students = [];
$stmt = $conn->prepare("SELECT ce.student_username, s.fname, s.lname
    FROM class_enrollments ce
    LEFT JOIN students s ON s.username = ce.student_username
    WHERE ce.class_id = ?
    ORDER BY s.fname, s.lname");

if ($stmt) {
    $stmt->bind_param('i', $classId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $students[] = [
            'student_username' => (string)($row['student_username'] ?? ''),
            'full_name' => trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? ''))
        ];
    }
    $stmt->close();
}

respond(true, 'Class students loaded', [
    'class_id' => $classId,
    'students' => $students,
    'total_students' => count($students)
]);
?>
