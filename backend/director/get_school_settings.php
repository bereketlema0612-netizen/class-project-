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
    sendResponse(false, 'Failed to ensure school settings table: ' . $conn->error, null, 500);
}

$stmt = $conn->prepare("SELECT * FROM school_settings WHERE id = 1 LIMIT 1");
if (!$stmt) {
    sendResponse(false, 'Failed to prepare school settings query: ' . $conn->error, null, 500);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $defaultName = 'Bensa School';
    $defaultEmail = '';
    $defaultPhone = '';
    $defaultAddress = '';
    $defaultYear = '';
    $insertStmt = $conn->prepare("
        INSERT INTO school_settings
            (id, school_name, email, phone, address, current_academic_year, school_opening_date, school_closing_date, term1_start_date, term1_end_date, term2_start_date, term2_end_date)
        VALUES (1, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL, NULL, NULL)
    ");
    if (!$insertStmt) {
        sendResponse(false, 'Failed to prepare default school settings insert: ' . $conn->error, null, 500);
    }
    $insertStmt->bind_param("sssss", $defaultName, $defaultEmail, $defaultPhone, $defaultAddress, $defaultYear);
    if (!$insertStmt->execute()) {
        sendResponse(false, 'Failed to create default school settings: ' . $insertStmt->error, null, 500);
    }

    $stmt->execute();
    $result = $stmt->get_result();
}

$settings = $result->fetch_assoc() ?: [
    'id' => 1,
    'school_name' => 'Bensa School',
    'email' => '',
    'phone' => '',
    'address' => '',
    'current_academic_year' => '',
    'school_opening_date' => null,
    'school_closing_date' => null,
    'term1_start_date' => null,
    'term1_end_date' => null,
    'term2_start_date' => null,
    'term2_end_date' => null
];

sendResponse(true, 'School settings retrieved successfully', ['settings' => $settings], 200);

$stmt->close();
$conn->close();
?>
