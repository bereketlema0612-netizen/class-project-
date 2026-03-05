<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    sendResponse(false, 'Unauthorized', null, 403);
}

$studentUsername = $_SESSION['username'];

function tableExists(mysqli $conn, string $table): bool {
    $safeTable = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $res && $res->num_rows > 0;
}

function columnExists(mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $res && $res->num_rows > 0;
}

$stmt = $conn->prepare("
    SELECT
        u.id, u.username, u.email,
        s.fname, s.mname, s.lname, s.grade_level, s.stream, s.address, s.parent_name, s.parent_phone
    FROM users u
    JOIN students s ON u.username = s.username
    WHERE u.username = ?
");
if (!$stmt) {
    sendResponse(false, 'Failed to prepare student query: ' . $conn->error, null, 500);
}
$stmt->bind_param("s", $studentUsername);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    sendResponse(false, 'Student not found', null, 404);
}

$gradesCount = 0;
$avgMarks = 0.0;
if (tableExists($conn, 'grades')) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM grades WHERE student_username = ?");
    if (!$stmt) {
        sendResponse(false, 'Failed to prepare grades count query: ' . $conn->error, null, 500);
    }
    $stmt->bind_param("s", $studentUsername);
    $stmt->execute();
    $gradesCount = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);

    $stmt = $conn->prepare("SELECT AVG(marks) as average_marks FROM grades WHERE student_username = ?");
    if (!$stmt) {
        sendResponse(false, 'Failed to prepare grades average query: ' . $conn->error, null, 500);
    }
    $stmt->bind_param("s", $studentUsername);
    $stmt->execute();
    $avgMarks = (float)($stmt->get_result()->fetch_assoc()['average_marks'] ?? 0);
} elseif (tableExists($conn, 'final_grades')) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM final_grades WHERE student_username = ?");
    if (!$stmt) {
        sendResponse(false, 'Failed to prepare final grades count query: ' . $conn->error, null, 500);
    }
    $stmt->bind_param("s", $studentUsername);
    $stmt->execute();
    $gradesCount = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);

    $stmt = $conn->prepare("SELECT AVG(total_marks) as average_marks FROM final_grades WHERE student_username = ?");
    if (!$stmt) {
        sendResponse(false, 'Failed to prepare final grades average query: ' . $conn->error, null, 500);
    }
    $stmt->bind_param("s", $studentUsername);
    $stmt->execute();
    $avgMarks = (float)($stmt->get_result()->fetch_assoc()['average_marks'] ?? 0);
}

$certificatesCount = 0;
if (tableExists($conn, 'certificates')) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM certificates WHERE student_username = ?");
    if ($stmt) {
        $stmt->bind_param("s", $studentUsername);
        $stmt->execute();
        $certificatesCount = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
    }
}

$promotionsCount = 0;
if (tableExists($conn, 'promotions')) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM promotions WHERE student_username = ?");
    if ($stmt) {
        $stmt->bind_param("s", $studentUsername);
        $stmt->execute();
        $promotionsCount = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
    }
}

$expectedSubjects = 0;
$submittedSubjects = 0;
$cgpaReady = false;
$overallCgpa = null;

$activeAcademicYearId = null;
if (tableExists($conn, 'academic_years') && columnExists($conn, 'academic_years', 'is_active')) {
    $res = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $activeAcademicYearId = (int)$row['id'];
    }
}

if (tableExists($conn, 'curriculum_subjects')) {
    $hasCurriculumActive = columnExists($conn, 'curriculum_subjects', 'is_active');
    $curriculumSql = "
        SELECT COUNT(DISTINCT cs.subject_id) AS expected_count
        FROM curriculum_subjects cs
        WHERE cs.grade_level = ?
          AND (cs.stream IS NULL OR cs.stream = '' OR cs.stream = ?)
    ";
    if ($hasCurriculumActive) {
        $curriculumSql .= " AND cs.is_active = 1";
    }
    $curriculumStmt = $conn->prepare($curriculumSql);
    if ($curriculumStmt) {
        $stream = (string)($student['stream'] ?? '');
        $gradeLevel = (string)($student['grade_level'] ?? '');
        $curriculumStmt->bind_param("ss", $gradeLevel, $stream);
        $curriculumStmt->execute();
        $expectedSubjects = (int)($curriculumStmt->get_result()->fetch_assoc()['expected_count'] ?? 0);
        $curriculumStmt->close();
    }
}

if (tableExists($conn, 'grades')) {
    $submittedSql = "SELECT COUNT(DISTINCT subject_id) AS submitted_count FROM grades WHERE student_username = ? AND subject_id IS NOT NULL";
    if ($activeAcademicYearId !== null && columnExists($conn, 'grades', 'academic_year_id')) {
        $submittedSql .= " AND academic_year_id = ?";
        $submittedStmt = $conn->prepare($submittedSql);
        if ($submittedStmt) {
            $submittedStmt->bind_param("si", $studentUsername, $activeAcademicYearId);
            $submittedStmt->execute();
            $submittedSubjects = (int)($submittedStmt->get_result()->fetch_assoc()['submitted_count'] ?? 0);
            $submittedStmt->close();
        }
    } else {
        $submittedStmt = $conn->prepare($submittedSql);
        if ($submittedStmt) {
            $submittedStmt->bind_param("s", $studentUsername);
            $submittedStmt->execute();
            $submittedSubjects = (int)($submittedStmt->get_result()->fetch_assoc()['submitted_count'] ?? 0);
            $submittedStmt->close();
        }
    }
} elseif (tableExists($conn, 'final_grades')) {
    $submittedSql = "SELECT COUNT(DISTINCT subject_id) AS submitted_count FROM final_grades WHERE student_username = ? AND subject_id IS NOT NULL";
    if ($activeAcademicYearId !== null && columnExists($conn, 'final_grades', 'academic_year_id')) {
        $submittedSql .= " AND academic_year_id = ?";
        $submittedStmt = $conn->prepare($submittedSql);
        if ($submittedStmt) {
            $submittedStmt->bind_param("si", $studentUsername, $activeAcademicYearId);
            $submittedStmt->execute();
            $submittedSubjects = (int)($submittedStmt->get_result()->fetch_assoc()['submitted_count'] ?? 0);
            $submittedStmt->close();
        }
    } else {
        $submittedStmt = $conn->prepare($submittedSql);
        if ($submittedStmt) {
            $submittedStmt->bind_param("s", $studentUsername);
            $submittedStmt->execute();
            $submittedSubjects = (int)($submittedStmt->get_result()->fetch_assoc()['submitted_count'] ?? 0);
            $submittedStmt->close();
        }
    }
}

if ($expectedSubjects > 0 && $submittedSubjects >= $expectedSubjects) {
    $cgpaReady = true;
    $overallCgpa = round($avgMarks / 25, 2);
}

$announcements = [];
if (tableExists($conn, 'announcements')) {
    $annContentExpr = columnExists($conn, 'announcements', 'content') ? "COALESCE(a.content, a.message)" : "a.message";
    $annTargetExpr = columnExists($conn, 'announcements', 'target_class_ids') ? "COALESCE(a.target_class_ids, '')" : "''";
    $annAttachmentPathExpr = columnExists($conn, 'announcements', 'attachment_path') ? "a.attachment_path" : "NULL";
    $annAttachmentNameExpr = columnExists($conn, 'announcements', 'attachment_name') ? "a.attachment_name" : "NULL";
    $annAttachmentMimeExpr = columnExists($conn, 'announcements', 'attachment_mime') ? "a.attachment_mime" : "NULL";
    $annAttachmentSizeExpr = columnExists($conn, 'announcements', 'attachment_size') ? "a.attachment_size" : "NULL";
    $studentClassIds = [];
    if (tableExists($conn, 'class_enrollments')) {
        $classStmt = $conn->prepare("SELECT class_id FROM class_enrollments WHERE student_username = ?");
        if ($classStmt) {
            $classStmt->bind_param("s", $studentUsername);
            $classStmt->execute();
            $classRes = $classStmt->get_result();
            while ($cr = $classRes->fetch_assoc()) {
                $cid = (int)($cr['class_id'] ?? 0);
                if ($cid > 0) {
                    $studentClassIds[] = (string)$cid;
                }
            }
            $classStmt->close();
        }
    }
    $stmt = $conn->prepare("
        SELECT a.id, a.title, a.message, {$annContentExpr} as content, a.audience, a.priority, a.created_at, {$annTargetExpr} as target_class_ids,
               {$annAttachmentPathExpr} AS attachment_path, {$annAttachmentNameExpr} AS attachment_name, {$annAttachmentMimeExpr} AS attachment_mime, {$annAttachmentSizeExpr} AS attachment_size
        FROM announcements a
        WHERE a.audience IN ('students', 'all')
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    if ($stmt) {
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as $row) {
            $targetCsv = trim((string)($row['target_class_ids'] ?? ''));
            if ($targetCsv === '') {
                $announcements[] = $row;
                continue;
            }
            $targets = array_filter(array_map('trim', explode(',', $targetCsv)), static fn($v) => $v !== '');
            if (count(array_intersect($targets, $studentClassIds)) > 0) {
                $announcements[] = $row;
            }
        }
    }
}

$schedule = [];
if (tableExists($conn, 'class_schedules') && tableExists($conn, 'classes')) {
    $classNameExpr = columnExists($conn, 'classes', 'class_name')
        ? 'c.class_name'
        : (columnExists($conn, 'classes', 'name') ? 'c.name' : "CONCAT('Grade ', c.grade_level, ' - ', c.section)");

    $stmt = $conn->prepare("
        SELECT cs.id, {$classNameExpr} AS class_name, c.grade_level, c.section, s.subject_name, t.fname, t.lname
        FROM class_schedules cs
        JOIN classes c ON cs.class_id = c.id
        LEFT JOIN subjects s ON cs.subject_id = s.id
        LEFT JOIN teachers t ON cs.teacher_username = t.username
        WHERE c.id IN (
            SELECT class_id FROM class_enrollments
            WHERE student_username = ?
        )
        LIMIT 5
    ");
    if ($stmt) {
        $stmt->bind_param("s", $studentUsername);
        $stmt->execute();
        $schedule = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

sendResponse(true, 'Dashboard data retrieved', [
    'student' => [
        'id' => $student['username'],
        'name' => $student['fname'] . ' ' . ($student['mname'] ? $student['mname'] . ' ' : '') . $student['lname'],
        'grade' => $student['grade_level'],
        'email' => $student['email']
    ],
    'statistics' => [
        'total_grades_entered' => $gradesCount,
        'average_marks' => round($avgMarks, 2),
        'overall_cgpa' => $overallCgpa,
        'cgpa_ready' => $cgpaReady,
        'expected_subjects' => $expectedSubjects,
        'submitted_subjects' => $submittedSubjects,
        'certificates_earned' => $certificatesCount,
        'promotions' => $promotionsCount
    ],
    'recent_announcements' => $announcements,
    'upcoming_schedule' => $schedule
], 200);

if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}
$conn->close();
?>
