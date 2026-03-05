<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'director') {
    sendResponse(false, 'Unauthorized access', null, 403);
}

$reportType = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'enrollment';
$dateRange = isset($_GET['range']) ? sanitizeInput($_GET['range']) : 'all';
$director_username = $_SESSION['username'];

$reportData = [];

if ($reportType === 'enrollment') {
    $stmt = $conn->prepare("SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active') as total_students,
        (SELECT COUNT(*) FROM users WHERE role = 'teacher' AND status = 'active') as total_teachers,
        (SELECT COUNT(*) FROM users WHERE status = 'active') as total_users
    ");
    
    if (!$stmt->execute()) {
        sendResponse(false, 'Query failed: ' . $stmt->error, null, 500);
    }
    
    $result = $stmt->get_result();
    $reportData = $result->fetch_assoc();
    
} elseif ($reportType === 'grades') {
    $stmt = $conn->prepare("SELECT s.grade_level, COUNT(*) as student_count FROM students s JOIN users u ON s.username = u.username WHERE u.status = 'active' GROUP BY s.grade_level ORDER BY s.grade_level");
    
    if (!$stmt->execute()) {
        sendResponse(false, 'Query failed: ' . $stmt->error, null, 500);
    }
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reportData[] = $row;
    }
    
} elseif ($reportType === 'certifications') {
    $stmt = $conn->prepare("SELECT certificate_type, COUNT(*) as count FROM certificates GROUP BY certificate_type");
    
    if (!$stmt->execute()) {
        sendResponse(false, 'Query failed: ' . $stmt->error, null, 500);
    }
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reportData[] = $row;
    }
    
} elseif ($reportType === 'promotions') {
    $stmt = $conn->prepare("SELECT from_grade, to_grade, COUNT(*) as count FROM promotions GROUP BY from_grade, to_grade");
    
    if (!$stmt->execute()) {
        sendResponse(false, 'Query failed: ' . $stmt->error, null, 500);
    }
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reportData[] = $row;
    }
    
} elseif ($reportType === 'registrations') {
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM registrations GROUP BY status");
    
    if (!$stmt->execute()) {
        sendResponse(false, 'Query failed: ' . $stmt->error, null, 500);
    }
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reportData[] = $row;
    }
}

logSystemActivity($conn, $director_username, 'VIEW_REPORT', 'Viewed ' . $reportType . ' report', 'success');

sendResponse(true, 'Report generated successfully', [
    'report_type' => $reportType,
    'date_range' => $dateRange,
    'generated_at' => date('Y-m-d H:i:s'),
    'data' => $reportData
], 200);

$stmt->close();
$conn->close();
?>
