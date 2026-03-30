<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$classId = (int)($_GET['class_id'] ?? 0);
if ($classId <= 0) {
    echo json_encode(['success' => false, 'message' => 'class_id required', 'data' => null]);
    exit;
}

$students = [];
$conn->query("CREATE TABLE IF NOT EXISTS class_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_username VARCHAR(50) NOT NULL,
    class_id INT NOT NULL,
    enrollment_date DATE NULL
)");

$hasDemoStudent = false;
$demoCheck = $conn->query("SELECT username FROM students WHERE username = 'std001' LIMIT 1");
if ($demoCheck && $demoCheck->num_rows > 0) {
    $hasDemoStudent = true;
}

if ($hasDemoStudent) {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM class_enrollments WHERE class_id = ?");
    if ($countStmt) {
        $countStmt->bind_param('i', $classId);
        $countStmt->execute();
        $countRow = $countStmt->get_result()->fetch_assoc();
        $countStmt->close();
        $c = (int)($countRow['c'] ?? 0);
        if ($c === 0) {
            $ins = $conn->prepare("INSERT INTO class_enrollments (student_username, class_id, enrollment_date) VALUES ('std001', ?, CURDATE())");
            if ($ins) {
                $ins->bind_param('i', $classId);
                $ins->execute();
                $ins->close();
            }
        }
    }
}

$stmt = $conn->prepare("SELECT ce.student_username, s.fname, s.lname FROM class_enrollments ce LEFT JOIN students s ON s.username = ce.student_username WHERE ce.class_id = ? ORDER BY s.fname, s.lname");
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

echo json_encode([
    'success' => true,
    'message' => 'Class students loaded',
    'data' => [
        'class_id' => $classId,
        'students' => $students,
        'total_students' => count($students)
    ]
]);
?>
