<?php
require_once __DIR__ . '/common.php';
$teacher = require_teacher(false);

$sql = "SELECT u.username, u.email, t.fname, t.lname,
            '' AS department,
            '' AS office_phone,
            '' AS office_room,
            '' AS subject,
            t.username AS employee_id_generated
        FROM users u
        LEFT JOIN teachers t ON t.username = u.username
        WHERE u.username = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    respond(false, 'DB error');
}

$stmt->bind_param('s', $teacher);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    respond(false, 'Teacher not found');
}

respond(true, 'Teacher profile loaded', ['teacher' => $row]);
?>
