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
$semester = strtolower(trim((string)($_GET['semester'] ?? '')));
$term = '';
if ($semester === 'first') $term = 'Term1';
if ($semester === 'second') $term = 'Term2';

$grades = [];
$sql = "SELECT subject, term, marks, letter_grade, entered_at FROM grades WHERE student_username = ?";
if ($term !== '') $sql .= " AND term = ?";
$sql .= " ORDER BY entered_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($term !== '') $stmt->bind_param('ss', $username, $term);
    else $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $grades[] = [
            'subject' => (string)($row['subject'] ?? ''),
            'term' => (string)($row['term'] ?? ''),
            'marks' => (float)($row['marks'] ?? 0),
            'letter_grade' => (string)($row['letter_grade'] ?? 'F'),
            'entered_at' => (string)($row['entered_at'] ?? '')
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

echo json_encode([
    'success' => true,
    'message' => 'Grades loaded',
    'data' => [
        'grades' => $grades,
        'average_marks' => round($avg, 2),
        'gpa' => $gpa
    ]
]);
?>
