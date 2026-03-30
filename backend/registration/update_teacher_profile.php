<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

session_start();
if (!isset($_SESSION['username']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'director'], true)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST only', 'data' => null]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON', 'data' => null]);
    exit;
}

$username = trim((string)($data['username'] ?? ''));
if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'username is required', 'data' => null]);
    exit;
}

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
$teacherCols = [
    "mname VARCHAR(50) NULL",
    "date_of_birth DATE NULL",
    "age INT NULL",
    "sex VARCHAR(20) NULL",
    "address TEXT NULL",
    "department VARCHAR(100) NULL",
    "subject VARCHAR(100) NULL",
    "office_room VARCHAR(50) NULL",
    "office_phone VARCHAR(50) NULL"
];
foreach ($teacherCols as $colDef) {
    $col = explode(' ', $colDef)[0];
    $chk = $conn->query("SHOW COLUMNS FROM teachers LIKE '" . $conn->real_escape_string($col) . "'");
    if ($chk && $chk->num_rows === 0) $conn->query("ALTER TABLE teachers ADD COLUMN $colDef");
}

$email = trim((string)($data['email'] ?? ''));
$status = trim((string)($data['status'] ?? 'active'));
$fname = trim((string)($data['fname'] ?? ''));
$mname = trim((string)($data['mname'] ?? ''));
$lname = trim((string)($data['lname'] ?? ''));
$dob = trim((string)($data['date_of_birth'] ?? ''));
$age = (int)($data['age'] ?? 0);
$sex = trim((string)($data['sex'] ?? ''));
$address = trim((string)($data['address'] ?? ''));
$department = trim((string)($data['department'] ?? ''));
$subject = trim((string)($data['subject'] ?? ''));
$officeRoom = trim((string)($data['office_room'] ?? ''));
$officePhone = trim((string)($data['office_phone'] ?? ''));

$u = $conn->prepare("UPDATE users SET email = ?, status = ? WHERE username = ? AND role = 'teacher'");
if ($u) {
    $u->bind_param('sss', $email, $status, $username);
    $u->execute();
}

$t = $conn->prepare("UPDATE teachers SET fname = ?, mname = ?, lname = ?, date_of_birth = NULLIF(?, ''), age = ?, sex = ?, address = ?, department = ?, subject = ?, office_room = ?, office_phone = ? WHERE username = ?");
if (!$t) {
    echo json_encode(['success' => false, 'message' => 'DB error', 'data' => null]);
    exit;
}
$t->bind_param('ssssisssssss', $fname, $mname, $lname, $dob, $age, $sex, $address, $department, $subject, $officeRoom, $officePhone, $username);
$t->execute();

echo json_encode(['success' => true, 'message' => 'Teacher profile updated', 'data' => ['username' => $username]]);
?>
