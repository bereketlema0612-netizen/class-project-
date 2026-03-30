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

$conn->query("CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    fname VARCHAR(50) NOT NULL,
    lname VARCHAR(50) NOT NULL,
    department VARCHAR(100) NULL,
    phone VARCHAR(50) NULL
)");
$adminCols = ["department VARCHAR(100) NULL", "phone VARCHAR(50) NULL"];
foreach ($adminCols as $colDef) {
    $col = explode(' ', $colDef)[0];
    $chk = $conn->query("SHOW COLUMNS FROM admins LIKE '" . $conn->real_escape_string($col) . "'");
    if ($chk && $chk->num_rows === 0) $conn->query("ALTER TABLE admins ADD COLUMN $colDef");
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) $data = $_POST;

$fullName = trim((string)($data['adminName'] ?? $data['name'] ?? ''));
$email = trim((string)($data['adminEmail'] ?? $data['email'] ?? ''));
$dept = trim((string)($data['adminDept'] ?? $data['department'] ?? ''));
$phone = trim((string)($data['adminPhone'] ?? $data['phone'] ?? ''));

if ($fullName === '' || $email === '') {
    echo json_encode(['success' => false, 'message' => 'Name and email required', 'data' => null]);
    exit;
}

$parts = preg_split('/\s+/', $fullName);
$fname = trim((string)($parts[0] ?? 'Admin'));
$lname = trim((string)($parts[count($parts) - 1] ?? 'User'));

function random_admin_password($len = 5) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) $out .= $chars[random_int(0, strlen($chars) - 1)];
    return $out;
}

$username = '';
for ($i = 1; $i <= 100; $i++) {
    $candidate = 'adm' . str_pad((string)random_int(1, 999), 3, '0', STR_PAD_LEFT);
    $st = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    if ($st) {
        $st->bind_param('s', $candidate);
        $st->execute();
        $exists = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$exists) {
            $username = $candidate;
            break;
        }
    }
}

if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'Could not generate username', 'data' => null]);
    exit;
}

$password = random_admin_password(5);
$u = $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')");
if (!$u) {
    echo json_encode(['success' => false, 'message' => 'DB error', 'data' => null]);
    exit;
}
$u->bind_param('sss', $username, $email, $password);
if (!$u->execute()) {
    echo json_encode(['success' => false, 'message' => 'Email already exists', 'data' => null]);
    exit;
}

$a = $conn->prepare("INSERT INTO admins (username, fname, lname, department, phone) VALUES (?, ?, ?, ?, ?)");
if ($a) {
    $a->bind_param('sssss', $username, $fname, $lname, $dept, $phone);
    $a->execute();
}

echo json_encode([
    'success' => true,
    'message' => 'Registration admin created',
    'data' => [
        'username' => $username,
        'password' => $password,
        'email' => $email,
        'name' => trim($fname . ' ' . $lname)
    ]
]);
?>
