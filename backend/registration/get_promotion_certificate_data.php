<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$promotionId = isset($_GET['promotion_id']) ? (int)$_GET['promotion_id'] : 0;
if ($promotionId <= 0) {
    sendResponse(false, 'promotion_id is required', null, 400);
}

$promoStmt = $conn->prepare("
    SELECT
        p.id AS promotion_id,
        p.student_username,
        p.from_grade,
        p.to_grade,
        p.promoted_date,
        p.remarks,
        s.fname,
        s.mname,
        s.lname,
        s.stream,
        u.email
    FROM promotions p
    JOIN students s ON s.username = p.student_username
    JOIN users u ON u.username = s.username
    WHERE p.id = ?
    LIMIT 1
");
if (!$promoStmt) {
    sendResponse(false, 'Failed to prepare promotion query: ' . $conn->error, null, 500);
}
$promoStmt->bind_param("i", $promotionId);
if (!$promoStmt->execute()) {
    sendResponse(false, 'Failed to load promotion data: ' . $promoStmt->error, null, 500);
}
$promo = $promoStmt->get_result()->fetch_assoc();
if (!$promo) {
    sendResponse(false, 'Promotion record not found', null, 404);
}

$schoolName = 'Bensa School';
$yearLabel = 'N/A';
$schoolResult = $conn->query("SELECT school_name, current_academic_year FROM school_settings ORDER BY id ASC LIMIT 1");
if ($schoolResult && $schoolResult->num_rows > 0) {
    $s = $schoolResult->fetch_assoc();
    $schoolName = $s['school_name'] ?: $schoolName;
    $yearLabel = $s['current_academic_year'] ?: $yearLabel;
}

$fromGrade = preg_replace('/\D+/', '', (string)$promo['from_grade']);
$gradeStmt = $conn->prepare("
    SELECT
        COALESCE(sub.subject_name, g.subject) AS subject_name,
        g.marks,
        g.letter_grade,
        g.term,
        g.entered_at
    FROM grades g
    JOIN classes c ON c.id = g.class_id
    LEFT JOIN subjects sub ON sub.id = g.subject_id
    WHERE g.student_username = ?
      AND (c.grade_level = ? OR c.grade_level = CONCAT('Grade ', ?))
    ORDER BY COALESCE(sub.subject_name, g.subject), g.entered_at DESC
");
if (!$gradeStmt) {
    sendResponse(false, 'Failed to prepare grades query: ' . $conn->error, null, 500);
}
$gradeStmt->bind_param("sss", $promo['student_username'], $fromGrade, $fromGrade);
if (!$gradeStmt->execute()) {
    sendResponse(false, 'Failed to load grade results: ' . $gradeStmt->error, null, 500);
}
$gradeRows = $gradeStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$latestBySubject = [];
foreach ($gradeRows as $row) {
    $subjectName = trim((string)($row['subject_name'] ?? ''));
    if ($subjectName === '') {
        continue;
    }
    if (!isset($latestBySubject[$subjectName])) {
        $latestBySubject[$subjectName] = [
            'subject_name' => $subjectName,
            'marks' => (float)$row['marks'],
            'letter_grade' => $row['letter_grade'],
            'term' => $row['term']
        ];
    }
}
$subjectResults = array_values($latestBySubject);

$total = 0.0;
foreach ($subjectResults as $r) {
    $total += (float)$r['marks'];
}
$count = count($subjectResults);
$average = $count > 0 ? round($total / $count, 2) : 0.0;

$overallLetter = 'N/A';
$scaleStmt = $conn->prepare("SELECT grade FROM grading_scales WHERE ? BETWEEN min_marks AND max_marks ORDER BY max_marks - min_marks ASC LIMIT 1");
if ($scaleStmt) {
    $scaleStmt->bind_param("d", $average);
    if ($scaleStmt->execute()) {
        $scaleRow = $scaleStmt->get_result()->fetch_assoc();
        if ($scaleRow) {
            $overallLetter = $scaleRow['grade'];
        }
    }
}

$fullName = trim($promo['fname'] . ' ' . ($promo['mname'] ? $promo['mname'] . ' ' : '') . $promo['lname']);

sendResponse(true, 'Promotion certificate data retrieved', [
    'certificate' => [
        'promotion_id' => (int)$promo['promotion_id'],
        'student_username' => $promo['student_username'],
        'student_name' => $fullName,
        'student_email' => $promo['email'],
        'from_grade' => $promo['from_grade'],
        'to_grade' => $promo['to_grade'],
        'stream' => $promo['stream'],
        'promoted_date' => $promo['promoted_date'],
        'remarks' => $promo['remarks'],
        'school_name' => $schoolName,
        'academic_year' => $yearLabel,
        'subject_results' => $subjectResults,
        'total_marks' => round($total, 2),
        'average_marks' => $average,
        'overall_letter_grade' => $overallLetter
    ]
], 200);

$conn->close();
?>
