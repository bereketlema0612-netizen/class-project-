<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'director') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST only', 'data' => null]);
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

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) $data = $_POST;

$schoolName = trim((string)($data['schoolName'] ?? $data['school_name'] ?? 'BENSE SECONDARY HIGH SCHOOL'));
$schoolEmail = trim((string)($data['schoolEmail'] ?? $data['school_email'] ?? ''));
$schoolPhone = trim((string)($data['schoolPhone'] ?? $data['school_phone'] ?? ''));
$schoolAddress = trim((string)($data['schoolAddress'] ?? $data['school_address'] ?? ''));
$academicYear = trim((string)($data['academicYear'] ?? $data['academic_year'] ?? ''));
$openingDate = trim((string)($data['openingDate'] ?? $data['opening_date'] ?? ''));
$term1End = trim((string)($data['term1End'] ?? $data['term1_end'] ?? ''));
$closingDate = trim((string)($data['closingDate'] ?? $data['closing_date'] ?? ''));

$exists = $conn->query("SELECT id FROM school_settings WHERE id = 1");
if ($exists && $exists->num_rows > 0) {
    $st = $conn->prepare("UPDATE school_settings SET school_name = ?, school_email = ?, school_phone = ?, school_address = ?, academic_year = ?, opening_date = NULLIF(?, ''), term1_end = NULLIF(?, ''), closing_date = NULLIF(?, '') WHERE id = 1");
    if (!$st) {
        echo json_encode(['success' => false, 'message' => 'DB error', 'data' => null]);
        exit;
    }
    $st->bind_param('ssssssss', $schoolName, $schoolEmail, $schoolPhone, $schoolAddress, $academicYear, $openingDate, $term1End, $closingDate);
    $st->execute();
} else {
    $st = $conn->prepare("INSERT INTO school_settings (id, school_name, school_email, school_phone, school_address, academic_year, opening_date, term1_end, closing_date) VALUES (1, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''))");
    if (!$st) {
        echo json_encode(['success' => false, 'message' => 'DB error', 'data' => null]);
        exit;
    }
    $st->bind_param('ssssssss', $schoolName, $schoolEmail, $schoolPhone, $schoolAddress, $academicYear, $openingDate, $term1End, $closingDate);
    $st->execute();
}

echo json_encode(['success' => true, 'message' => 'School settings saved', 'data' => null]);
?>
