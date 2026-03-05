<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$sql = "
    SELECT p.id, p.student_username, p.from_grade, p.to_grade, p.promoted_date, p.remarks,
           s.fname, s.mname, s.lname
    FROM promotions p
    JOIN students s ON p.student_username = s.username
    ORDER BY p.promoted_date DESC, p.id DESC
";
$result = $conn->query($sql);
if (!$result) {
    sendResponse(false, 'Failed to load promotions: ' . $conn->error, null, 500);
}

$items = [];
while ($row = $result->fetch_assoc()) {
    $row['full_name'] = trim($row['fname'] . ' ' . ($row['mname'] ? $row['mname'] . ' ' : '') . $row['lname']);
    $items[] = $row;
}

sendResponse(true, 'Promotions retrieved', ['promotions' => $items], 200);
$conn->close();
?>
