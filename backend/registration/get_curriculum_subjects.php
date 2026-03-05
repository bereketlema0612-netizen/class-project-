<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director', 'registration_admin'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$gradeRaw = isset($_GET['grade']) ? sanitizeInput($_GET['grade']) : '';
$grade = preg_replace('/\D+/', '', $gradeRaw);
$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$streamRaw = isset($_GET['stream']) ? sanitizeInput($_GET['stream']) : '';
$stream = strtolower(trim($streamRaw));
if (!in_array($stream, ['', 'natural', 'social'], true)) {
    $stream = '';
}

function columnExists(mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $res && $res->num_rows > 0;
}

$hasIsActive = columnExists($conn, 'curriculum_subjects', 'is_active');

if ($classId > 0) {
    $clsStmt = $conn->prepare("SELECT grade_level, stream FROM classes WHERE id = ? LIMIT 1");
    if ($clsStmt) {
        $clsStmt->bind_param('i', $classId);
        if ($clsStmt->execute()) {
            $clsRow = $clsStmt->get_result()->fetch_assoc();
            if ($clsRow) {
                $grade = preg_replace('/\D+/', '', (string)($clsRow['grade_level'] ?? $grade));
                if ($stream === '') {
                    $stream = strtolower(trim((string)($clsRow['stream'] ?? '')));
                    if (!in_array($stream, ['', 'natural', 'social'], true)) {
                        $stream = '';
                    }
                }
            }
        }
        $clsStmt->close();
    }
}

if ($grade === '' && $classId <= 0) {
    sendResponse(false, 'Grade or class_id is required', null, 400);
}

$gradeNumber = (int)$grade;
$isLowerGrade = $gradeNumber > 0 && $gradeNumber <= 10;
$subjects = [];

function fetchCurriculumSubjects(mysqli $conn, string $grade, string $stream, bool $hasIsActive, bool $respectStream, bool $respectIsActive): array {
    $sql = "
        SELECT DISTINCT s.id, s.subject_name, s.subject_code
        FROM curriculum_subjects cs
        JOIN subjects s ON s.id = cs.subject_id
        WHERE (
            TRIM(cs.grade_level) = ?
            OR TRIM(cs.grade_level) = CONCAT('Grade ', ?)
            OR TRIM(cs.grade_level) = CONCAT('grade ', ?)
            OR TRIM(cs.grade_level) REGEXP CONCAT('(^|[^0-9])', ?, '([^0-9]|$)')
        )
    ";

    if ($hasIsActive && $respectIsActive) {
        $sql .= " AND cs.is_active = 1";
    }

    if ($respectStream) {
        if ($stream !== '') {
            $sql .= " AND (cs.stream = ? OR cs.stream IS NULL OR cs.stream = '')";
        } else {
            $sql .= " AND (cs.stream IS NULL OR cs.stream = '')";
        }
    }

    $sql .= " ORDER BY s.subject_name";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($respectStream && $stream !== '') {
        $stmt->bind_param('sssss', $grade, $grade, $grade, $grade, $stream);
    } else {
        $stmt->bind_param('ssss', $grade, $grade, $grade, $grade);
    }

    $rows = [];
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
    return $rows;
}

function fetchDefaultCurriculumByGrade(mysqli $conn, string $grade, string $stream): array {
    $gradeNum = (int)$grade;
    $streamNorm = strtolower(trim($stream));
    $isNatural = ($streamNorm === 'natural');
    $isSocial = ($streamNorm === 'social');

    // Project default curriculum used when curriculum_subjects table is missing/incomplete.
    // Use subject_code first because names may vary across DB seeds.
    $mapCodes = [
        '9' => ['BIO101', 'CHM101', 'CVC101', 'ENG101', 'GEO101', 'HIS101', 'IT101', 'MTH101', 'PHY101', 'SPT101'],
        '10' => ['BIO101', 'CHM101', 'CVC101', 'ENG101', 'GEO101', 'HIS101', 'IT101', 'MTH101', 'PHY101', 'SPT101'],
        '11_natural' => ['BIO101', 'CHM101', 'DRW101', 'ENG101', 'AI101', 'ENGR101', 'MTH101', 'PHY101'],
        '11_social' => ['AGR101', 'ECO101', 'ENG101', 'GEO101', 'HIS101', 'MTH101'],
        '12_natural' => ['BIO101', 'CHM101', 'DRW101', 'ENG101', 'AI101', 'ENGR101', 'MTH101', 'PHY101'],
        '12_social' => ['AGR101', 'ECO101', 'ENG101', 'GEO101', 'HIS101', 'MTH101']
    ];
    $mapNames = [
        '9' => ['Biology', 'Chemistry', 'Civic', 'English', 'Geography', 'History', 'IT', 'Mathematics', 'Physics', 'Sports'],
        '10' => ['Biology', 'Chemistry', 'Civic', 'English', 'Geography', 'History', 'IT', 'Mathematics', 'Physics', 'Sports'],
        '11_natural' => ['Biology', 'Chemistry', 'Drawing', 'English', 'Introduction to AI', 'Introduction to Engineering', 'Mathematics', 'Physics'],
        '11_social' => ['Agriculture', 'Economics', 'English', 'Geography', 'History', 'Mathematics'],
        '12_natural' => ['Biology', 'Chemistry', 'Drawing', 'English', 'Introduction to AI', 'Introduction to Engineering', 'Mathematics', 'Physics'],
        '12_social' => ['Agriculture', 'Economics', 'English', 'Geography', 'History', 'Mathematics']
    ];

    $key = (string)$gradeNum;
    if ($gradeNum >= 11) {
        if ($isNatural) {
            $key .= '_natural';
        } elseif ($isSocial) {
            $key .= '_social';
        } else {
            // No stream selected for upper grades: union both streams.
            $codesNatural = $mapCodes[$key . '_natural'] ?? [];
            $codesSocial = $mapCodes[$key . '_social'] ?? [];
            $namesNatural = $mapNames[$key . '_natural'] ?? [];
            $namesSocial = $mapNames[$key . '_social'] ?? [];
            $subjectCodes = array_values(array_unique(array_merge($codesNatural, $codesSocial)));
            $subjectNames = array_values(array_unique(array_merge($namesNatural, $namesSocial)));
            if (count($subjectCodes) === 0 && count($subjectNames) === 0) {
                return [];
            }
            $codePh = count($subjectCodes) ? implode(',', array_fill(0, count($subjectCodes), '?')) : '';
            $namePh = count($subjectNames) ? implode(',', array_fill(0, count($subjectNames), '?')) : '';
            $parts = [];
            if ($codePh !== '') $parts[] = "subject_code IN ($codePh)";
            if ($namePh !== '') $parts[] = "subject_name IN ($namePh)";
            $sql = "SELECT id, subject_name, subject_code FROM subjects WHERE (" . implode(' OR ', $parts) . ") ORDER BY subject_name";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return [];
            }
            $params = array_merge($subjectCodes, $subjectNames);
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
            $rows = [];
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
            $stmt->close();
            return $rows;
        }
    }

    $subjectCodes = $mapCodes[$key] ?? [];
    $subjectNames = $mapNames[$key] ?? [];
    if (count($subjectCodes) === 0 && count($subjectNames) === 0) {
        return [];
    }

    $codePh = count($subjectCodes) ? implode(',', array_fill(0, count($subjectCodes), '?')) : '';
    $namePh = count($subjectNames) ? implode(',', array_fill(0, count($subjectNames), '?')) : '';
    $parts = [];
    if ($codePh !== '') $parts[] = "subject_code IN ($codePh)";
    if ($namePh !== '') $parts[] = "subject_name IN ($namePh)";
    $sql = "SELECT id, subject_name, subject_code FROM subjects WHERE (" . implode(' OR ', $parts) . ") ORDER BY subject_name";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $params = array_merge($subjectCodes, $subjectNames);
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    $rows = [];
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
    return $rows;
}

// 1) Strict curriculum (active + stream)
$subjects = fetchCurriculumSubjects($conn, $grade, $stream, $hasIsActive, true, true);

// 2) Relax stream only (important when class stream/curriculum stream mismatch)
if (count($subjects) === 0) {
    $subjects = fetchCurriculumSubjects($conn, $grade, $stream, $hasIsActive, false, true);
}

// 3) Relax active flag too (legacy data where is_active not maintained)
if (count($subjects) === 0) {
    $subjects = fetchCurriculumSubjects($conn, $grade, $stream, $hasIsActive, false, false);
}

// 4) For lower grades, if stream filtering accidentally narrowed the list, load full grade curriculum.
if ($isLowerGrade && count($subjects) <= 1) {
    $fallbackLower = fetchCurriculumSubjects($conn, $grade, '', $hasIsActive, false, false);
    if (count($fallbackLower) > count($subjects)) {
        $subjects = $fallbackLower;
    }
}

// 5) If curriculum table is missing/incomplete, return default curriculum set by grade/stream.
if (count($subjects) <= 1) {
    $defaultSubjects = fetchDefaultCurriculumByGrade($conn, $grade, $stream);
    if (count($defaultSubjects) > count($subjects)) {
        $subjects = $defaultSubjects;
    }
}

if (count($subjects) === 0 && $classId > 0) {
    $fallbackSql = "
        SELECT DISTINCT s.id, s.subject_name, s.subject_code
        FROM assignments a
        JOIN subjects s ON s.id = a.subject_id
        WHERE a.class_id = ? AND a.assignment_type = 'teacher'
        ORDER BY s.subject_name
    ";
    $fbStmt = $conn->prepare($fallbackSql);
    if ($fbStmt) {
        $fbStmt->bind_param('i', $classId);
        if ($fbStmt->execute()) {
            $fbRes = $fbStmt->get_result();
            while ($row = $fbRes->fetch_assoc()) {
                $subjects[] = $row;
            }
        }
        $fbStmt->close();
    }
}

if (count($subjects) === 0 && $classId > 0) {
    $fallbackSql2 = "
        SELECT DISTINCT s.id, s.subject_name, s.subject_code
        FROM class_schedules cs
        JOIN subjects s ON s.id = cs.subject_id
        WHERE cs.class_id = ?
        ORDER BY s.subject_name
    ";
    $fbStmt2 = $conn->prepare($fallbackSql2);
    if ($fbStmt2) {
        $fbStmt2->bind_param('i', $classId);
        if ($fbStmt2->execute()) {
            $fbRes2 = $fbStmt2->get_result();
            while ($row = $fbRes2->fetch_assoc()) {
                $subjects[] = $row;
            }
        }
        $fbStmt2->close();
    }
}

sendResponse(true, 'Curriculum subjects retrieved', [
    'subjects' => $subjects,
    'total_subjects' => count($subjects)
], 200);

$conn->close();
?>
