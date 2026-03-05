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
        p.id, p.from_grade, p.to_grade, p.promoted_date, p.remarks,
        COALESCE(su.fname, tu.fname, ra.fname, d.fname) as fname,
        COALESCE(su.lname, tu.lname, ra.lname, d.lname) as lname
    FROM promotions p
    LEFT JOIN users u ON p.promoted_by_username = u.username
    LEFT JOIN students su ON su.username = u.username
    LEFT JOIN teachers tu ON tu.username = u.username
    LEFT JOIN admins ra ON ra.username = u.username
    LEFT JOIN directors d ON d.username = u.username
    WHERE p.student_username = ?
    ORDER BY p.promoted_date DESC
");
$stmt->bind_param("s", $studentUsername);
$stmt->execute();
$promotions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (!$promotions) {
    sendResponse(true, 'No promotions yet', ['promotions' => [], 'count' => 0], 200);
}

foreach ($promotions as &$promo) {
    $promo['promoted_by'] = $promo['fname'] . ' ' . $promo['lname'];
    unset($promo['fname'], $promo['lname']);
}

sendResponse(true, 'Promotions retrieved', [
    'promotions' => $promotions,
    'total_promotions' => count($promotions)
], 200);

$stmt->close();
$conn->close();
?>
