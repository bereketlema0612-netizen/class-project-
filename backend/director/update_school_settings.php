<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'director') {
    sendResponse(false, 'Unauthorized access', null, 403);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$school_name = sanitizeInput($data['school_name'] ?? '');
$email = sanitizeInput($data['email'] ?? '');
$phone = sanitizeInput($data['phone'] ?? '');
$address = sanitizeInput($data['address'] ?? '');
$current_year = sanitizeInput($data['current_year'] ?? '');
$opening_date = $data['opening_date'] ?? '';
$closing_date = $data['closing_date'] ?? '';
$term1_start = $data['term1_start'] ?? '';
$term1_end = $data['term1_end'] ?? '';
$term2_start = $data['term2_start'] ?? '';
$term2_end = $data['term2_end'] ?? '';

$director_username = $_SESSION['username'];

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("UPDATE school_settings SET school_name = ?, email = ?, phone = ?, address = ?, current_academic_year = ?, school_opening_date = ?, school_closing_date = ?, term1_start_date = ?, term1_end_date = ?, term2_start_date = ?, term2_end_date = ?, updated_at = NOW() WHERE id = 1");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("sssssssssss", $school_name, $email, $phone, $address, $current_year, $opening_date, $closing_date, $term1_start, $term1_end, $term2_start, $term2_end);
    
    if (!$stmt->execute()) {
        throw new Exception("Update failed: " . $stmt->error);
    }
    
    logSystemActivity($conn, $director_username, 'UPDATE_SETTINGS', 'Updated school settings', 'success');
    
    $conn->commit();
    
    sendResponse(true, 'School settings updated successfully', [], 200);
    
} catch (Exception $e) {
    $conn->rollback();
    sendResponse(false, 'Update failed: ' . $e->getMessage(), null, 500);
}

$stmt->close();
$conn->close();
?>
