<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || (($_SESSION['role'] ?? '') !== 'admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST only', 'data' => null]);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student','teacher','admin') NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    fname VARCHAR(50) NOT NULL,
    mname VARCHAR(50) NULL,
    lname VARCHAR(50) NOT NULL,
    date_of_birth DATE NULL,
    age INT NULL,
    sex VARCHAR(20) NULL,
    address TEXT NULL,
    department VARCHAR(100) NULL,
    subject VARCHAR(100) NULL,
    office_room VARCHAR(50) NULL,
    office_phone VARCHAR(50) NULL
)");

$payload = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) $payload = [];
} else {
    $payload = $_POST;
}

$fname = trim((string)($payload['fname'] ?? ''));
$mname = trim((string)($payload['mname'] ?? ''));
$lname = trim((string)($payload['lname'] ?? ''));
$email = trim((string)($payload['email'] ?? ''));
$dob = trim((string)($payload['dateOfBirth'] ?? $payload['date_of_birth'] ?? ''));
$age = (int)($payload['age'] ?? 0);
$sex = trim((string)($payload['sex'] ?? ''));
$address = trim((string)($payload['address'] ?? ''));
$department = trim((string)($payload['department'] ?? ''));
$subject = trim((string)($payload['subject'] ?? ''));
$officeRoom = trim((string)($payload['officeRoom'] ?? $payload['office_room'] ?? ''));
$officePhone = trim((string)($payload['officePhone'] ?? $payload['office_phone'] ?? ''));

if ($fname === '' || $lname === '' || $email === '' || $department === '' || $subject === '') {
    echo json_encode(['success' => false, 'message' => 'Required: fname, lname, email, department, subject', 'data' => null]);
    exit;
}

function random_teacher_pass($len = 5) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) $out .= $chars[random_int(0, strlen($chars) - 1)];
    return $out;
}

$username = '';
for ($i = 1; $i <= 100; $i++) {
    $candidate = 'tch' . str_pad((string)random_int(1, 999), 3, '0', STR_PAD_LEFT);
    $st = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    if ($st) {
        $st->bind_param('s', $candidate);
        $st->execute();
        $exists = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$exists) { $username = $candidate; break; }
    }
}

if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'Could not generate username', 'data' => null]);
    exit;
}

$passwordPlain = random_teacher_pass(5);
$insertUser = $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'teacher', 'active')");
if (!$insertUser) {
    echo json_encode(['success' => false, 'message' => 'DB error (users)', 'data' => null]);
    exit;
}
$insertUser->bind_param('sss', $username, $email, $passwordPlain);
if (!$insertUser->execute()) {
    echo json_encode(['success' => false, 'message' => 'Email/username already exists', 'data' => null]);
    exit;
}

$insertTeacher = $conn->prepare("INSERT INTO teachers (username, fname, mname, lname, date_of_birth, age, sex, address, department, subject, office_room, office_phone) VALUES (?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, ?, ?)");
if (!$insertTeacher) {
    echo json_encode(['success' => false, 'message' => 'DB error (teachers)', 'data' => null]);
    exit;
}
$insertTeacher->bind_param('sssssissssss', $username, $fname, $mname, $lname, $dob, $age, $sex, $address, $department, $subject, $officeRoom, $officePhone);
$insertTeacher->execute();

echo json_encode([
    'success' => true,
    'message' => 'Teacher registered',
    'data' => [
        'username' => $username,
        'password' => $passwordPlain,
        'email' => $email,
        'full_name' => trim($fname . ' ' . $lname)
    ]
]);
?>
