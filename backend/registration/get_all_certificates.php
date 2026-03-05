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

$type = sanitizeInput($_GET['type'] ?? '');

$query = "
    SELECT c.id, c.student_username, c.certificate_number, c.type, c.issued_date, c.remarks, c.created_at,
           s.fname, s.mname, s.lname
    FROM certificates c
    JOIN students s ON c.student_username = s.username
    WHERE 1=1
";
if ($type !== '') {
    $query .= " AND c.type = '" . $conn->real_escape_string($type) . "'";
}
$query .= " ORDER BY c.issued_date DESC, c.id DESC";

$result = $conn->query($query);
if (!$result) {
    sendResponse(false, 'Failed to load certificates: ' . $conn->error, null, 500);
}

$items = [];
while ($row = $result->fetch_assoc()) {
    $row['full_name'] = trim($row['fname'] . ' ' . ($row['mname'] ? $row['mname'] . ' ' : '') . $row['lname']);
    $items[] = $row;
}

sendResponse(true, 'Certificates retrieved', ['certificates' => $items], 200);
$conn->close();
?>
