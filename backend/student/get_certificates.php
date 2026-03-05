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

$stmt = $conn->prepare("SELECT username FROM students WHERE username = ?");
$stmt->bind_param("s", $studentUsername);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    sendResponse(false, 'Student not found', null, 404);
}

$stmt = $conn->prepare("
    SELECT 
        c.id, c.certificate_number, c.type, c.issued_date, c.remarks,
        ay.academic_year,
        COALESCE(su.fname, tu.fname, ra.fname, d.fname) as fname,
        COALESCE(su.lname, tu.lname, ra.lname, d.lname) as lname
    FROM certificates c
    JOIN academic_years ay ON c.academic_year_id = ay.id
    LEFT JOIN users u ON c.issued_by_username = u.username
    LEFT JOIN students su ON su.username = u.username
    LEFT JOIN teachers tu ON tu.username = u.username
    LEFT JOIN admins ra ON ra.username = u.username
    LEFT JOIN directors d ON d.username = u.username
    WHERE c.student_username = ?
    ORDER BY c.issued_date DESC
");
$stmt->bind_param("s", $studentUsername);
$stmt->execute();
$certificates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (!$certificates) {
    sendResponse(true, 'No certificates yet', ['certificates' => [], 'count' => 0], 200);
}

foreach ($certificates as &$cert) {
    $cert['issued_by'] = $cert['fname'] . ' ' . $cert['lname'];
    unset($cert['fname'], $cert['lname']);
}

sendResponse(true, 'Certificates retrieved', [
    'certificates' => $certificates,
    'total_certificates' => count($certificates)
], 200);

$stmt->close();
$conn->close();
?>
