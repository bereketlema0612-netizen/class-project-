<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username'])) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$sessionUsername = (string)$_SESSION['username'];
$sessionRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
$sessionRole = str_replace(['-', ' '], '_', $sessionRole);
$isAllowedRole = in_array($sessionRole, ['admin', 'director', 'registration_admin'], true);
if (!$isAllowedRole) {
    $isAdminTableUser = false;
    $tblAdmins = $conn->query("SHOW TABLES LIKE 'admins'");
    $tblDirectors = $conn->query("SHOW TABLES LIKE 'directors'");
    if (($tblAdmins && $tblAdmins->num_rows > 0) || ($tblDirectors && $tblDirectors->num_rows > 0)) {
        $authSqlParts = [];
        if ($tblAdmins && $tblAdmins->num_rows > 0) {
            $authSqlParts[] = "SELECT username FROM admins WHERE username = ?";
        }
        if ($tblDirectors && $tblDirectors->num_rows > 0) {
            $authSqlParts[] = "SELECT username FROM directors WHERE username = ?";
        }
        $authSql = implode(" UNION ", $authSqlParts) . " LIMIT 1";
        $authStmt = $conn->prepare($authSql);
        if ($authStmt) {
            if (count($authSqlParts) === 2) {
                $authStmt->bind_param("ss", $sessionUsername, $sessionUsername);
            } else {
                $authStmt->bind_param("s", $sessionUsername);
            }
            $authStmt->execute();
            $isAdminTableUser = (bool)$authStmt->get_result()->fetch_assoc();
        }
    }
    if (!$isAdminTableUser) {
        sendResponse(false, 'Unauthorized', null, 403);
    }
}

ensureAssignmentBlockColumn($conn);

$sql = "
    SELECT
        a.id AS assignment_id,
        a.class_id,
        a.teacher_username,
        t.fname,
        t.mname,
        t.lname,
        t.department,
        COALESCE(sub.subject_name, t.subject) AS subject,
        c.grade_level,
        c.section,
        u.status AS teacher_status,
        a.is_blocked
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN teachers t ON a.teacher_username = t.username
    JOIN users u ON u.username = t.username
    LEFT JOIN subjects sub ON a.subject_id = sub.id
    WHERE a.assignment_type = 'teacher'
    ORDER BY t.fname, t.lname, c.grade_level, c.section
";
$result = $conn->query($sql);
if (!$result) {
    sendResponse(false, 'Failed to load assigned teachers: ' . $conn->error, null, 500);
}

$items = [];
while ($row = $result->fetch_assoc()) {
    $row['full_name'] = trim($row['fname'] . ' ' . ($row['mname'] ? $row['mname'] . ' ' : '') . $row['lname']);
    $row['is_blocked'] = (int)($row['is_blocked'] ?? 0);
    if (preg_match('/^\d+$/', (string)$row['grade_level'])) {
        $row['grade_label'] = 'Grade ' . $row['grade_level'];
    } else {
        $row['grade_label'] = (string)$row['grade_level'];
    }
    $items[] = $row;
}

sendResponse(true, 'Assigned teachers retrieved', ['assigned_teachers' => $items], 200);
$conn->close();
?>
