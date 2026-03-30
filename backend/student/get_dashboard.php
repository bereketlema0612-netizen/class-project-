<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_username VARCHAR(50) NOT NULL,
    class_id INT NOT NULL,
    teacher_username VARCHAR(50) NOT NULL,
    term VARCHAR(20) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    marks DECIMAL(6,2) NOT NULL DEFAULT 0,
    letter_grade VARCHAR(3) NOT NULL,
    entered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_grade (student_username, class_id, term, subject)
)");

$username = (string)$_SESSION['username'];

$grades = [];
$stmt = $conn->prepare("SELECT subject, marks, letter_grade, entered_at FROM grades WHERE student_username = ? ORDER BY entered_at DESC LIMIT 10");
if ($stmt) {
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $grades[] = [
            'subject' => (string)($row['subject'] ?? ''),
            'assessment' => 'Assessment',
            'marks' => (float)($row['marks'] ?? 0),
            'letter_grade' => (string)($row['letter_grade'] ?? 'F'),
            'created_at' => (string)($row['entered_at'] ?? '')
        ];
    }
    $stmt->close();
}

$avg = 0.0;
if (count($grades) > 0) {
    $sum = 0.0;
    foreach ($grades as $g) $sum += (float)$g['marks'];
    $avg = $sum / count($grades);
}
$gpa = round(($avg / 100) * 4, 2);

$conn->query("CREATE TABLE IF NOT EXISTS class_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_username VARCHAR(50) NOT NULL,
    class_id INT NOT NULL,
    enrollment_date DATE NULL
)");
$conn->query("CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_username VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    class_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS director_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    director_username VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    send_to VARCHAR(40) NOT NULL DEFAULT 'all',
    target_username VARCHAR(50) NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'normal',
    attachment_name VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

$classIds = [];
$stEnroll = $conn->prepare("SELECT class_id FROM class_enrollments WHERE student_username = ?");
if ($stEnroll) {
    $stEnroll->bind_param('s', $username);
    $stEnroll->execute();
    $resE = $stEnroll->get_result();
    while ($r = $resE->fetch_assoc()) {
        $cid = (int)($r['class_id'] ?? 0);
        if ($cid > 0) $classIds[] = $cid;
    }
    $stEnroll->close();
}

$announcements = [];
if (count($classIds) > 0) {
    $in = implode(',', array_map('intval', $classIds));
    $resA = $conn->query("SELECT title, message, created_at FROM announcements WHERE class_id IN ($in) OR class_id = 0 OR class_id IS NULL ORDER BY id DESC LIMIT 50");
} else {
    $resA = $conn->query("SELECT title, message, created_at FROM announcements WHERE class_id = 0 OR class_id IS NULL ORDER BY id DESC LIMIT 50");
}
if ($resA) {
    while ($row = $resA->fetch_assoc()) {
        $announcements[] = [
            'source' => 'teacher',
            'title' => (string)($row['title'] ?? ''),
            'message' => (string)($row['message'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? '')
        ];
    }
}

$resD = $conn->prepare("SELECT title, message, created_at FROM director_announcements WHERE send_to IN ('all','students') OR (send_to='individual_student' AND target_username = ?) ORDER BY id DESC LIMIT 50");
if ($resD) {
    $resD->bind_param('s', $username);
    $resD->execute();
    $rd = $resD->get_result();
    while ($row = $rd->fetch_assoc()) {
        $announcements[] = [
            'source' => 'director',
            'title' => (string)($row['title'] ?? ''),
            'message' => (string)($row['message'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? '')
        ];
    }
    $resD->close();
}

usort($announcements, function ($a, $b) {
    return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
});

echo json_encode([
    'success' => true,
    'message' => 'Student dashboard loaded',
    'data' => [
        'gpa' => $gpa,
        'pending_tasks' => 0,
        'announcements_count' => count($announcements),
        'recent_grades' => array_slice($grades, 0, 5),
        'recent_announcements' => array_slice($announcements, 0, 5),
        'upcoming_events' => []
    ]
]);
?>
