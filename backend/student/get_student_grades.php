<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';
require_once '../helpers/grading_backend.php';

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

function decodeScoresJson(?string $json): array {
    if ($json === null || trim($json) === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }
    $scores = [];
    foreach ($decoded as $itemId => $score) {
        $scores[(string)((int)$itemId)] = is_numeric($score) ? (float)$score : null;
    }
    return $scores;
}

$stmt = $conn->prepare("SELECT username FROM students WHERE username = ?");
if (!$stmt) {
    sendResponse(false, 'Failed to prepare student lookup query: ' . $conn->error, null, 500);
}
$stmt->bind_param("s", $studentUsername);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    sendResponse(false, 'Student not found', null, 404);
}

$grades = [];
$completeTotals = [];

if (
    tableExists($conn, 'assessment_structures') &&
    tableExists($conn, 'assessment_structure_items') &&
    tableExists($conn, 'student_assessment_compact_scores')
) {
    $sql = "
        SELECT
            ast.id AS structure_id,
            ast.class_id,
            ast.term,
            ast.total_points,
            asi.id AS item_id,
            asi.item_name,
            asi.max_points,
            asi.item_order,
            sub.subject_name,
            COALESCE(ay.academic_year, '-') AS academic_year,
            t.fname,
            t.lname,
            sacs.scores_json,
            sacs.total_score,
            sacs.letter_grade
        FROM class_enrollments ce
        JOIN assessment_structures ast
            ON ast.class_id = ce.class_id
        JOIN assessment_structure_items asi
            ON asi.structure_id = ast.id
        LEFT JOIN student_assessment_compact_scores sacs
            ON sacs.structure_id = ast.id
           AND sacs.class_id = ast.class_id
           AND sacs.student_username = ce.student_username
        LEFT JOIN subjects sub
            ON sub.id = ast.subject_id
        LEFT JOIN academic_years ay
            ON ay.id = ast.academic_year_id
        LEFT JOIN teachers t
            ON t.username = ast.teacher_username
        WHERE ce.student_username = ?
        ORDER BY ay.academic_year DESC, ast.term DESC, sub.subject_name ASC, asi.item_order ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendResponse(false, 'Failed to prepare assessment grades query: ' . $conn->error, null, 500);
    }
    $stmt->bind_param("s", $studentUsername);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $grouped = [];
    foreach ($rows as $row) {
        $groupKey = implode('|', [
            (int)$row['structure_id'],
            (int)$row['class_id'],
            (string)$row['term'],
            (string)$row['subject_name']
        ]);
        if (!isset($grouped[$groupKey])) {
            $teacherName = trim(((string)($row['fname'] ?? '')) . ' ' . ((string)($row['lname'] ?? '')));
            $grouped[$groupKey] = [
                'id' => (int)$row['structure_id'],
                'structure_id' => (int)$row['structure_id'],
                'class_id' => (int)$row['class_id'],
                'term' => (string)$row['term'],
                'subject_name' => (string)($row['subject_name'] ?? '-'),
                'academic_year' => (string)($row['academic_year'] ?? '-'),
                'teacher_name' => $teacherName !== '' ? $teacherName : '-',
                'assessment_items' => [],
                'marks' => null,
                'letter_grade' => '',
                'is_complete' => false
            ];
        }

        $scoresMap = decodeScoresJson($row['scores_json'] ?? null);
        $itemIdKey = (string)((int)$row['item_id']);
        $hasScore = array_key_exists($itemIdKey, $scoresMap);
        $grouped[$groupKey]['assessment_items'][] = [
            'id' => (int)$row['item_id'],
            'name' => (string)$row['item_name'],
            'max_points' => (float)$row['max_points'],
            'order' => (int)$row['item_order'],
            'score' => $hasScore ? $scoresMap[$itemIdKey] : null
        ];
    }

    foreach ($grouped as $entry) {
        $isComplete = count($entry['assessment_items']) > 0;
        $total = 0.0;
        $hasAnyScore = false;
        foreach ($entry['assessment_items'] as $item) {
            if ($item['score'] === null) {
                $isComplete = false;
                continue;
            }
            $hasAnyScore = true;
            $total += (float)$item['score'];
        }
        $entry['is_complete'] = $isComplete;
        $entry['marks'] = $hasAnyScore ? round($total, 2) : null;
        if ($isComplete) {
            $entry['letter_grade'] = trim((string)($entry['letter_grade'] ?? ''));
            if ($entry['letter_grade'] === '') {
                $scale = resolveGradeScale($conn, $entry['marks']);
                $entry['letter_grade'] = (string)($scale['grade'] ?? '');
            }
            $completeTotals[] = (float)$entry['marks'];
        } else {
            $entry['letter_grade'] = '';
        }
        $grades[] = $entry;
    }
}

if (count($grades) === 0 && tableExists($conn, 'grades')) {
    $stmt = $conn->prepare("
        SELECT
            g.id, g.term, g.marks, g.letter_grade,
            COALESCE(s.subject_name, g.subject) AS subject_name,
            t.fname, t.lname,
            COALESCE(ay.academic_year, '-') AS academic_year
        FROM grades g
        LEFT JOIN subjects s ON g.subject_id = s.id
        LEFT JOIN teachers t ON g.teacher_username = t.username
        LEFT JOIN academic_years ay ON g.academic_year_id = ay.id
        WHERE g.student_username = ?
        ORDER BY ay.academic_year DESC, g.term DESC
    ");
    if ($stmt) {
        $stmt->bind_param("s", $studentUsername);
        $stmt->execute();
        $legacyGrades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($legacyGrades as &$grade) {
            $fname = (string)($grade['fname'] ?? '');
            $lname = (string)($grade['lname'] ?? '');
            $grade['teacher_name'] = trim($fname . ' ' . $lname) ?: '-';
            $grade['assessment_items'] = [];
            $grade['is_complete'] = true;
            unset($grade['fname'], $grade['lname']);
            $completeTotals[] = (float)$grade['marks'];
        }
        unset($grade);
        $grades = $legacyGrades;
    }
}

if (count($grades) === 0 && tableExists($conn, 'final_grades')) {
    $stmt = $conn->prepare("
        SELECT
            fg.id, fg.term, fg.total_marks AS marks, fg.letter_grade,
            s.subject_name AS subject_name,
            t.fname, t.lname,
            COALESCE(ay.academic_year, '-') AS academic_year
        FROM final_grades fg
        LEFT JOIN subjects s ON fg.subject_id = s.id
        LEFT JOIN teachers t ON fg.teacher_username = t.username
        LEFT JOIN academic_years ay ON fg.academic_year_id = ay.id
        WHERE fg.student_username = ?
        ORDER BY ay.academic_year DESC, fg.term DESC
    ");
    if ($stmt) {
        $stmt->bind_param("s", $studentUsername);
        $stmt->execute();
        $legacyFinalGrades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($legacyFinalGrades as &$grade) {
            $fname = (string)($grade['fname'] ?? '');
            $lname = (string)($grade['lname'] ?? '');
            $grade['teacher_name'] = trim($fname . ' ' . $lname) ?: '-';
            $grade['assessment_items'] = [];
            $grade['is_complete'] = true;
            unset($grade['fname'], $grade['lname']);
            $completeTotals[] = (float)$grade['marks'];
        }
        unset($grade);
        $grades = $legacyFinalGrades;
    }
}

if (count($grades) === 0) {
    sendResponse(true, 'No grades found yet', ['grades' => [], 'average_marks' => 0, 'total_grades' => 0], 200);
}

$average = count($completeTotals) > 0 ? array_sum($completeTotals) / count($completeTotals) : 0;

sendResponse(true, 'Grades retrieved', [
    'grades' => $grades,
    'average_marks' => round($average, 2),
    'total_grades' => count($grades)
], 200);

if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}
$conn->close();
?>
