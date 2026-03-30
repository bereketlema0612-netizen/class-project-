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

$conn->query("CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    fname VARCHAR(50) NOT NULL,
    mname VARCHAR(50) NULL,
    lname VARCHAR(50) NOT NULL,
    date_of_birth DATE NULL,
    age INT NULL,
    sex VARCHAR(20) NULL,
    grade_level VARCHAR(20) NULL,
    stream VARCHAR(20) NULL,
    address TEXT NULL,
    parent_name VARCHAR(120) NULL,
    parent_phone VARCHAR(50) NULL
)");
$studentCols = [
    "mname VARCHAR(50) NULL",
    "date_of_birth DATE NULL",
    "age INT NULL",
    "sex VARCHAR(20) NULL",
    "grade_level VARCHAR(20) NULL",
    "stream VARCHAR(20) NULL",
    "address TEXT NULL",
    "parent_name VARCHAR(120) NULL",
    "parent_phone VARCHAR(50) NULL"
];
foreach ($studentCols as $colDef) {
    $col = explode(' ', $colDef)[0];
    $chk = $conn->query("SHOW COLUMNS FROM students LIKE '" . $conn->real_escape_string($col) . "'");
    if ($chk && $chk->num_rows === 0) $conn->query("ALTER TABLE students ADD COLUMN $colDef");
}

$email = trim((string)($data['email'] ?? ''));
$fname = trim((string)($data['fname'] ?? ''));
$mname = trim((string)($data['mname'] ?? ''));
$lname = trim((string)($data['lname'] ?? ''));
$dob = trim((string)($data['date_of_birth'] ?? ''));
$age = (int)($data['age'] ?? 0);
$sex = trim((string)($data['sex'] ?? ''));
$grade = trim((string)($data['grade_level'] ?? ''));
$stream = trim((string)($data['stream'] ?? ''));
$address = trim((string)($data['address'] ?? ''));
$parentName = trim((string)($data['parent_name'] ?? ''));
$parentPhone = trim((string)($data['parent_phone'] ?? ''));

if ($email !== '') {
    $u = $conn->prepare("UPDATE users SET email = ? WHERE username = ? AND role = 'student'");
    if ($u) {
        $u->bind_param('ss', $email, $username);
        $u->execute();
    }
}

$s = $conn->prepare("UPDATE students SET fname = ?, mname = ?, lname = ?, date_of_birth = NULLIF(?, ''), age = ?, sex = ?, grade_level = ?, stream = ?, address = ?, parent_name = ?, parent_phone = ? WHERE username = ?");
if (!$s) {
    echo json_encode(['success' => false, 'message' => 'DB error', 'data' => null]);
    exit;
}
$s->bind_param('ssssisssssss', $fname, $mname, $lname, $dob, $age, $sex, $grade, $stream, $address, $parentName, $parentPhone, $username);
$s->execute();

echo json_encode(['success' => true, 'message' => 'Student profile updated', 'data' => ['username' => $username]]);
?>
