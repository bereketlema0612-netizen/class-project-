<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/curriculum.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$gradeInput = sanitizeInput($data['grade'] ?? '');
$section = sanitizeInput($data['section'] ?? '');
$targetStreamInput = sanitizeInput($data['target_stream'] ?? '');
$gradeDigits = preg_replace('/\D+/', '', $gradeInput);
if ($gradeDigits === '' || $section === '') {
    sendResponse(false, 'Grade and section are required', null, 400);
}

$selectedUsernames = [];
if (!empty($data['student_usernames']) && is_array($data['student_usernames'])) {
    foreach ($data['student_usernames'] as $u) {
        $clean = sanitizeInput((string)$u);
        if ($clean !== '') {
            $selectedUsernames[$clean] = true;
        }
    }
}

$nextGrade = ((int)$gradeDigits) + 1;
if ($nextGrade > 12) {
    sendResponse(false, 'Grade 12 students cannot be promoted to next grade', null, 400);
}

$activeYearId = null;
$activeYearResult = $conn->query("SELECT id, academic_year FROM academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
$activeYearLabel = null;
if ($activeYearResult && $activeYearResult->num_rows > 0) {
    $active = $activeYearResult->fetch_assoc();
    $activeYearId = (int)$active['id'];
    $activeYearLabel = $active['academic_year'];
}

$classSql = "
    SELECT c.id, c.stream, c.academic_year_id
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
$currentClassId = (int)$classRow['id'];
$classStream = normalizeStream($classRow['stream'] ?? '');
$needsStream = ((int)$gradeDigits >= 11 && (int)$gradeDigits <= 12);
if ($needsStream && $classStream === '') {
    sendResponse(false, 'Selected Grade 11/12 class must have stream set (natural/social)', null, 400);
}
$nextClassStream = $classStream;
if ((int)$gradeDigits === 10) {
    $nextClassStream = normalizeStream($targetStreamInput);
    if ($nextClassStream === '') {
        sendResponse(false, 'Target stream is required for Grade 10 promotion', null, 400);
    }
}

$expectedSubjectCount = curriculumSubjectCount($gradeDigits, $classStream);

$studentsStmt = $conn->prepare("
    SELECT ce.student_username, s.fname, s.mname, s.lname
    FROM class_enrollments ce
    JOIN students s ON s.username = ce.student_username
    WHERE ce.class_id = ?
");
$studentsStmt->bind_param('i', $currentClassId);
if (!$studentsStmt->execute()) {
    sendResponse(false, 'Failed to load students: ' . $studentsStmt->error, null, 500);
}
$students = $studentsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$gradeSql = "SELECT student_username, letter_grade, subject_id, subject FROM grades WHERE class_id = ?";
if ($activeYearId !== null) {
    $gradeSql .= " AND (academic_year_id = ? OR academic_year_id IS NULL)";
}
$gradeStmt = $conn->prepare($gradeSql);
if (!$gradeStmt) {
    sendResponse(false, 'Failed to prepare grades query: ' . $conn->error, null, 500);
}
if ($activeYearId !== null) {
    $gradeStmt->bind_param('ii', $currentClassId, $activeYearId);
} else {
    $gradeStmt->bind_param('i', $currentClassId);
}
if (!$gradeStmt->execute()) {
    sendResponse(false, 'Failed to load grades: ' . $gradeStmt->error, null, 500);
}
$gradeRows = $gradeStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$byStudent = [];
foreach ($gradeRows as $g) {
    $byStudent[$g['student_username']][] = $g;
}

function promoPoints($letter) {
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

$readyStudents = [];
foreach ($students as $s) {
    $u = $s['student_username'];
    if (!empty($selectedUsernames) && !isset($selectedUsernames[$u])) {
        continue;
    }

    $rows = $byStudent[$u] ?? [];
    $fCount = 0;
    $subjectKeys = [];
    $points = [];
    foreach ($rows as $g) {
        $letter = strtoupper(trim((string)($g['letter_grade'] ?? '')));
        if ($letter === 'F') $fCount++;
        $pt = promoPoints($letter);
        if ($pt !== null) $points[] = $pt;

        if (!empty($g['subject_id'])) {
            $subjectKeys['id:' . $g['subject_id']] = true;
        } elseif (!empty($g['subject'])) {
            $subjectKeys['name:' . $g['subject']] = true;
        }
    }
    $submitted = count($subjectKeys);
    $hasMissing = $expectedSubjectCount > 0 ? ($submitted < $expectedSubjectCount) : ($submitted === 0);
    if ($hasMissing) continue;

    $cgpa = count($points) ? (array_sum($points) / count($points)) : 0.0;
    if ($fCount <= 3 && $cgpa > 2.5) {
        $readyStudents[] = $u;
    }
}

if (empty($readyStudents)) {
    sendResponse(false, 'No eligible students to promote', null, 400);
}

$nextClassId = null;
$findNextStmt = $conn->prepare("
    SELECT id
    FROM classes
    WHERE section = ? AND (grade_level = ? OR grade_level = CONCAT('Grade ', ?))
      AND (stream = ? OR (stream IS NULL AND ? = ''))
      " . ($activeYearId !== null ? "AND (academic_year_id = ? OR academic_year_id IS NULL)" : "") . "
    ORDER BY id DESC
    LIMIT 1
");
if (!$findNextStmt) {
    sendResponse(false, 'Failed to prepare next class query: ' . $conn->error, null, 500);
}
if ($activeYearId !== null) {
    $nextGradeStr = (string)$nextGrade;
    $findNextStmt->bind_param('sssssi', $section, $nextGradeStr, $nextGradeStr, $nextClassStream, $nextClassStream, $activeYearId);
} else {
    $nextGradeStr = (string)$nextGrade;
    $findNextStmt->bind_param('sssss', $section, $nextGradeStr, $nextGradeStr, $nextClassStream, $nextClassStream);
}
$findNextStmt->execute();
$nextClassRow = $findNextStmt->get_result()->fetch_assoc();

if ($nextClassRow) {
    $nextClassId = (int)$nextClassRow['id'];
} else {
    $name = 'Grade ' . $nextGrade . ' - ' . $section;
    $className = 'Grade ' . $nextGrade . ' ' . $section;
    $nextGradeStr = (string)$nextGrade;
    if ($activeYearId !== null) {
        $createStmt = $conn->prepare("
            INSERT INTO classes (name, class_name, grade_level, section, stream, teacher_username, academic_year_id, created_at)
            VALUES (?, ?, ?, ?, NULLIF(?, ''), NULL, ?, NOW())
        ");
        if (!$createStmt) {
            sendResponse(false, 'Failed to prepare class creation: ' . $conn->error, null, 500);
        }
        $createStmt->bind_param('sssssi', $name, $className, $nextGradeStr, $section, $nextClassStream, $activeYearId);
    } else {
        $createStmt = $conn->prepare("
            INSERT INTO classes (name, class_name, grade_level, section, stream, teacher_username, academic_year_id, created_at)
            VALUES (?, ?, ?, ?, NULLIF(?, ''), NULL, NULL, NOW())
        ");
        if (!$createStmt) {
            sendResponse(false, 'Failed to prepare class creation: ' . $conn->error, null, 500);
        }
        $createStmt->bind_param('sssss', $name, $className, $nextGradeStr, $section, $nextClassStream);
    }
    if (!$createStmt->execute()) {
        sendResponse(false, 'Failed to create next class: ' . $createStmt->error, null, 500);
    }
    $nextClassId = (int)$conn->insert_id;
}

$today = date('Y-m-d');
$promotedBy = $_SESSION['username'];
$promoted = [];

$conn->begin_transaction();
try {
    $studentNameStmt = $conn->prepare("SELECT fname, mname, lname FROM students WHERE username = ? LIMIT 1");
    $insertPromoStmt = $conn->prepare("
        INSERT INTO promotions (student_username, from_grade, to_grade, promoted_date, promoted_by_username, remarks, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $updateStudentStmt = $conn->prepare("UPDATE students SET grade_level = ? WHERE username = ?");
    $updateStudentStreamStmt = $conn->prepare("UPDATE students SET stream = ? WHERE username = ? AND ? <> ''");
    $deleteEnrollStmt = $conn->prepare("DELETE FROM class_enrollments WHERE student_username = ? AND class_id = ?");
    $insertEnrollStmt = $conn->prepare("
        INSERT INTO class_enrollments (student_username, class_id, enrollment_date)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE enrollment_date = VALUES(enrollment_date)
    ");
    $announceStmt = $conn->prepare("
        INSERT INTO announcements (title, message, content, audience, priority, created_by_username, status, created_at, updated_at)
        VALUES (?, ?, ?, 'students', 'Normal', ?, 'active', NOW(), NOW())
    ");

    foreach ($readyStudents as $u) {
        $fromGrade = (string)$gradeDigits;
        $toGrade = (string)$nextGrade;
        $remarks = 'Auto promotion based on CGPA and F-grade criteria';

        $insertPromoStmt->bind_param('ssssss', $u, $fromGrade, $toGrade, $today, $promotedBy, $remarks);
        if (!$insertPromoStmt->execute()) throw new Exception($insertPromoStmt->error);

        $updateStudentStmt->bind_param('ss', $toGrade, $u);
        if (!$updateStudentStmt->execute()) throw new Exception($updateStudentStmt->error);
        $updateStudentStreamStmt->bind_param('sss', $nextClassStream, $u, $nextClassStream);
        if (!$updateStudentStreamStmt->execute()) throw new Exception($updateStudentStreamStmt->error);

        $deleteEnrollStmt->bind_param('si', $u, $currentClassId);
        if (!$deleteEnrollStmt->execute()) throw new Exception($deleteEnrollStmt->error);

        $insertEnrollStmt->bind_param('sis', $u, $nextClassId, $today);
        if (!$insertEnrollStmt->execute()) throw new Exception($insertEnrollStmt->error);

        $studentNameStmt->bind_param('s', $u);
        $studentNameStmt->execute();
        $nameRow = $studentNameStmt->get_result()->fetch_assoc();
        $fullName = $nameRow ? trim($nameRow['fname'] . ' ' . ($nameRow['mname'] ? $nameRow['mname'] . ' ' : '') . $nameRow['lname']) : $u;

        $title = 'Promotion Result';
        $msg = 'Congratulations ' . $fullName . ' (' . $u . '), you are promoted to Grade ' . $nextGrade . '.';
        $announceStmt->bind_param('ssss', $title, $msg, $msg, $promotedBy);
        if (!$announceStmt->execute()) throw new Exception($announceStmt->error);

        $promoted[] = $u;
    }

    logSystemActivity($conn, $promotedBy, 'BULK_PROMOTION', 'Promoted ' . count($promoted) . ' students from Grade ' . $gradeDigits . ' ' . $section . ' to Grade ' . $nextGrade, 'success');
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    sendResponse(false, 'Promotion processing failed: ' . $e->getMessage(), null, 500);
}

sendResponse(true, 'Ready students promoted successfully', [
    'promoted_count' => count($promoted),
    'promoted_students' => $promoted,
    'from_grade' => (string)$gradeDigits,
    'to_grade' => (string)$nextGrade,
    'section' => $section,
    'stream' => $nextClassStream,
    'academic_year' => $activeYearLabel
], 200);

$conn->close();
?>
