<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'director'], true)) {
    sendResponse(false, 'Unauthorized', null, 403);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    sendResponse(false, 'Invalid JSON input', null, 400);
}

$studentUsername = sanitizeInput($data['student_username'] ?? '');
$type = sanitizeInput($data['type'] ?? '');
$issuedDate = $data['issued_date'] ?? date('Y-m-d');
$remarks = sanitizeInput($data['remarks'] ?? '');

if ($studentUsername === '' || $type === '') {
    sendResponse(false, 'Student and certificate type are required', null, 400);
}

$studentStmt = $conn->prepare("SELECT username FROM students WHERE username = ?");
$studentStmt->bind_param("s", $studentUsername);
$studentStmt->execute();
if ($studentStmt->get_result()->num_rows !== 1) {
    sendResponse(false, 'Student not found', null, 404);
}

$yearId = null;
$yearStmt = $conn->prepare("SELECT id FROM academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
$yearStmt->execute();
$yearRow = $yearStmt->get_result()->fetch_assoc();
if ($yearRow) {
    $yearId = (int)$yearRow['id'];
}

$prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $type), 0, 4));
if ($prefix === '') {
    $prefix = 'CERT';
}
$seqResult = $conn->query("SELECT IFNULL(MAX(id), 0) + 1 AS next_id FROM certificates");
$seq = (int)$seqResult->fetch_assoc()['next_id'];
$certificateNumber = $prefix . '-' . date('Y') . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

$stmt = $conn->prepare("
    INSERT INTO certificates (student_username, certificate_number, type, certificate_type, issued_date, academic_year_id, issued_by_username, remarks, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
$issuedBy = $_SESSION['username'];
$stmt->bind_param("sssssiss", $studentUsername, $certificateNumber, $type, $type, $issuedDate, $yearId, $issuedBy, $remarks);
if (!$stmt->execute()) {
    sendResponse(false, 'Failed to create certificate: ' . $stmt->error, null, 500);
}

logSystemActivity($conn, $issuedBy, 'CREATE_CERTIFICATE', 'Created ' . $type . ' certificate for ' . $studentUsername, 'success');
sendResponse(true, 'Certificate created', [
    'certificate_id' => $conn->insert_id,
    'certificate_number' => $certificateNumber
], 201);

$conn->close();
?>
