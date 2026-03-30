<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'director') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS school_settings (
    id INT PRIMARY KEY,
    school_name VARCHAR(255) NOT NULL,
    school_email VARCHAR(255) NULL,
    school_phone VARCHAR(50) NULL,
    school_address TEXT NULL,
    academic_year VARCHAR(30) NULL,
    opening_date DATE NULL,
    term1_end DATE NULL,
    closing_date DATE NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$check = $conn->query("SELECT id FROM school_settings WHERE id = 1");
if ($check && $check->num_rows === 0) {
    $conn->query("INSERT INTO school_settings (id, school_name, school_email, school_phone, school_address, academic_year) VALUES (1, 'BENSE SECONDARY HIGH SCHOOL', '', '', '', '')");
}

$settings = [
    'school_name' => '',
    'school_email' => '',
    'school_phone' => '',
    'school_address' => '',
    'academic_year' => '',
    'opening_date' => '',
    'term1_end' => '',
    'closing_date' => ''
];
$res = $conn->query("SELECT school_name, school_email, school_phone, school_address, academic_year, opening_date, term1_end, closing_date FROM school_settings WHERE id = 1 LIMIT 1");
if ($res) {
    $row = $res->fetch_assoc();
    if ($row) $settings = array_merge($settings, $row);
}

echo json_encode(['success' => true, 'message' => 'School settings loaded', 'data' => ['settings' => $settings]]);
?>
