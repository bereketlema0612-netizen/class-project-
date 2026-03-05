<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/curriculum.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$gradeInput = sanitizeInput($_GET['grade'] ?? '');
$section = sanitizeInput($_GET['section'] ?? '');
$gradeDigits = preg_replace('/\D+/', '', $gradeInput);

if ($gradeDigits === '' || $section === '') {
    sendResponse(false, 'Grade and section are required', null, 400);
}

$activeYearId = null;
$activeYearResult = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
if ($activeYearResult && $activeYearResult->num_rows > 0) {
    $activeYearId = (int)$activeYearResult->fetch_assoc()['id'];
}

$classSql = "
    SELECT c.id, c.grade_level, c.section, c.stream, c.academic_year_id
    FROM classes c
    WHERE c.section = ? AND (c.grade_level = ? OR c.grade_level = CONCAT('Grade ', ?))
";
if ($activeYearId !== null) {
    $classSql .= " AND (c.academic_year_id = ? OR c.academic_year_id IS NULL)";
}
$classSql .= " ORDER BY c.id DESC LIMIT 1";

$classStmt = $conn->prepare($classSql);
if (!$classStmt) {
    sendResponse(false, 'Failed to prepare class query: ' . $conn->error, null, 500);
}
if ($activeYearId !== null) {
    $classStmt->bind_param('sssi', $section, $gradeDigits, $gradeDigits, $activeYearId);
} else {
    $classStmt->bind_param('sss', $section, $gradeDigits, $gradeDigits);
}
if (!$classStmt->execute()) {
    sendResponse(false, 'Failed to load class: ' . $classStmt->error, null, 500);
}
$classRow = $classStmt->get_result()->fetch_assoc();
if (!$classRow) {
    sendResponse(false, 'No class found for selected grade and section', null, 404);
}

$classId = (int)$classRow['id'];
$classStream = normalizeStream($classRow['stream'] ?? '');
$needsStream = ((int)$gradeDigits >= 11 && (int)$gradeDigits <= 12);
if ($needsStream && $classStream === '') {
    sendResponse(false, 'Selected Grade 11/12 class must have stream set (natural/social)', null, 400);
}
$nextGrade = ((int)$gradeDigits) + 1;
$canPromote = $nextGrade <= 12;

$studentsStmt = $conn->prepare("
    SELECT ce.student_username, s.fname, s.mname, s.lname
    FROM class_enrollments ce
    JOIN students s ON s.username = ce.student_username
    WHERE ce.class_id = ?
    ORDER BY s.fname, s.lname
");
$studentsStmt->bind_param('i', $classId);
if (!$studentsStmt->execute()) {
    sendResponse(false, 'Failed to load enrolled students: ' . $studentsStmt->error, null, 500);
}
$studentRows = $studentsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$expectedSubjectCount = curriculumSubjectCount($gradeDigits, $classStream);

$gradeSql = "SELECT student_username, letter_grade, subject_id, subject FROM grades WHERE class_id = ?";
if ($activeYearId !== null) {
    $gradeSql .= " AND (academic_year_id = ? OR academic_year_id IS NULL)";
}
$gradeStmt = $conn->prepare($gradeSql);
if (!$gradeStmt) {
    sendResponse(false, 'Failed to prepare grades query: ' . $conn->error, null, 500);
}
if ($activeYearId !== null) {
    $gradeStmt->bind_param('ii', $classId, $activeYearId);
} else {
    $gradeStmt->bind_param('i', $classId);
}
if (!$gradeStmt->execute()) {
    sendResponse(false, 'Failed to load grades: ' . $gradeStmt->error, null, 500);
}
$gradeRows = $gradeStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$studentGrades = [];
foreach ($gradeRows as $g) {
    $u = $g['student_username'];
    if (!isset($studentGrades[$u])) {
        $studentGrades[$u] = [];
    }
    $studentGrades[$u][] = $g;
}

function pointsFromLetter($letter) {
    $l = strtoupper(trim((string)$letter));
    if ($l === 'A+' || $l === 'A') return 4.0;
    if ($l === 'B+') return 3.5;
    if ($l === 'B') return 3.0;
    if ($l === 'C+') return 2.5;
    if ($l === 'C') return 2.0;
    if ($l === 'D+') return 1.5;
    if ($l === 'D') return 1.0;
    if ($l === 'F') return 0.0;
    return null;
}

$ready = [];
$notReady = [];
$missing = [];

foreach ($studentRows as $s) {
    $username = $s['student_username'];
    $fullName = trim($s['fname'] . ' ' . ($s['mname'] ? $s['mname'] . ' ' : '') . $s['lname']);
    $rows = $studentGrades[$username] ?? [];

    $fCount = 0;
    $subjectKeys = [];
    $points = [];
    foreach ($rows as $g) {
        $letter = strtoupper(trim((string)($g['letter_grade'] ?? '')));
        if ($letter === 'F') {
            $fCount++;
        }
        $pt = pointsFromLetter($letter);
        if ($pt !== null) {
            $points[] = $pt;
        }
        if (!empty($g['subject_id'])) {
            $subjectKeys['id:' . $g['subject_id']] = true;
        } elseif (!empty($g['subject'])) {
            $subjectKeys['name:' . $g['subject']] = true;
        }
    }

    $submittedSubjects = count($subjectKeys);
    $cgpa = count($points) ? round(array_sum($points) / count($points), 2) : 0.0;

    $hasMissing = false;
    if ($expectedSubjectCount > 0) {
        $hasMissing = $submittedSubjects < $expectedSubjectCount;
    } else {
        $hasMissing = $submittedSubjects === 0;
    }

    if ($hasMissing) {
        $missing[] = [
            'student_username' => $username,
            'full_name' => $fullName,
            'submitted_subjects' => $submittedSubjects,
            'expected_subjects' => $expectedSubjectCount
        ];
        continue;
    }

    $item = [
        'student_username' => $username,
        'full_name' => $fullName,
        'cgpa' => $cgpa,
        'f_count' => $fCount,
        'from_grade' => (string)$gradeDigits,
        'to_grade' => (string)$nextGrade,
        'stream' => $classStream
    ];

    if ($canPromote && $fCount <= 3 && $cgpa > 2.5) {
        $ready[] = $item;
    } else {
        $reason = !$canPromote
            ? 'Maximum grade reached'
            : ($fCount > 3 ? 'More than 3 F grades' : 'CGPA below or equal to 2.5');
        $item['reason'] = $reason;
        $notReady[] = $item;
    }
}

sendResponse(true, 'Promotion evaluation completed', [
    'class_id' => $classId,
    'grade' => (string)$gradeDigits,
    'section' => $section,
    'stream' => $classStream,
    'next_grade' => $canPromote ? (string)$nextGrade : null,
    'ready_students' => $ready,
    'not_ready_students' => $notReady,
    'missing_grade_students' => $missing
], 200);

$conn->close();
?>
