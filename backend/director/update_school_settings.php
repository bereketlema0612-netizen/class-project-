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
    if (!$conn->query("
        CREATE TABLE IF NOT EXISTS school_settings (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            school_name VARCHAR(150) NOT NULL,
            email VARCHAR(120) NOT NULL,
            phone VARCHAR(40) NOT NULL,
            address VARCHAR(255) NOT NULL,
            current_academic_year VARCHAR(30) NOT NULL,
            school_opening_date DATE DEFAULT NULL,
            school_closing_date DATE DEFAULT NULL,
            term1_start_date DATE DEFAULT NULL,
            term1_end_date DATE DEFAULT NULL,
            term2_start_date DATE DEFAULT NULL,
            term2_end_date DATE DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ")) {
        throw new Exception("Failed to ensure school settings table: " . $conn->error);
    }

    $ensureStmt = $conn->prepare("SELECT id FROM school_settings WHERE id = 1 LIMIT 1");
    if (!$ensureStmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $ensureStmt->execute();
    $ensureResult = $ensureStmt->get_result();
    if ($ensureResult->num_rows === 0) {
        $seedStmt = $conn->prepare("
            INSERT INTO school_settings
                (id, school_name, email, phone, address, current_academic_year, school_opening_date, school_closing_date, term1_start_date, term1_end_date, term2_start_date, term2_end_date)
            VALUES (1, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL)
        ");
        if (!$seedStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        if (!$seedStmt->execute()) {
            throw new Exception("Initial insert failed: " . $seedStmt->error);
        }
    }

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
