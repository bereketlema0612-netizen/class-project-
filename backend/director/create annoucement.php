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

function columnExists(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND column_name = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function ensureAnnouncementsSchema(mysqli $conn): void {
    $conn->query("ALTER TABLE announcements MODIFY audience ENUM('all','students','teachers','admins','individual') NOT NULL");
    if (!columnExists($conn, 'announcements', 'target_usernames')) {
        if (!$conn->query("ALTER TABLE announcements ADD COLUMN target_usernames TEXT NULL AFTER target_class_ids")) {
            throw new Exception('Failed to add target_usernames column: ' . $conn->error);
        }
    }
}

function uploadDirectorAnnouncementFile(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [
            'path' => null,
            'name' => null,
            'mime' => null,
            'size' => null
        ];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }

    $uploadDir = dirname(__DIR__) . '/uploads/announcements';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new Exception('Failed to create announcement upload directory');
    }

    $originalName = (string)($file['name'] ?? 'attachment');
    $safeOriginal = preg_replace('/[^A-Za-z0-9._ -]/', '_', $originalName);
    $ext = pathinfo($safeOriginal, PATHINFO_EXTENSION);
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . ($ext !== '' ? '.' . $ext : '');
    $targetPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to move uploaded file');
    }

    $mime = mime_content_type($targetPath) ?: 'application/octet-stream';

    return [
        'path' => 'uploads/announcements/' . $filename,
        'name' => $safeOriginal,
        'mime' => $mime,
        'size' => (int)filesize($targetPath)
    ];
}

try {
    ensureAnnouncementsSchema($conn);
} catch (Exception $e) {
    sendResponse(false, $e->getMessage(), null, 500);
}

$title = sanitizeInput($_POST['title'] ?? '');
$message = sanitizeInput($_POST['message'] ?? '');
$audienceInput = sanitizeInput($_POST['audience'] ?? '');
$priority = sanitizeInput($_POST['priority'] ?? 'Normal');
$targetUsername = sanitizeInput($_POST['target_username'] ?? '');

if ($title === '' || $message === '' || $audienceInput === '') {
    sendResponse(false, 'All required fields must be filled', null, 400);
}

$audienceMap = [
    'all' => ['audience' => 'all', 'target_usernames' => null],
    'students' => ['audience' => 'students', 'target_usernames' => null],
    'teachers' => ['audience' => 'teachers', 'target_usernames' => null],
    'admins' => ['audience' => 'admins', 'target_usernames' => null],
    'individual_student' => ['audience' => 'individual', 'target_usernames' => $targetUsername, 'role' => 'student'],
    'individual_teacher' => ['audience' => 'individual', 'target_usernames' => $targetUsername, 'role' => 'teacher'],
    'individual_admin' => ['audience' => 'individual', 'target_usernames' => $targetUsername, 'role' => 'admin']
];

if (!isset($audienceMap[$audienceInput])) {
    sendResponse(false, 'Invalid audience', null, 400);
}

if (!in_array($priority, ['Normal', 'High', 'Urgent'], true)) {
    sendResponse(false, 'Invalid priority', null, 400);
}

$targetConfig = $audienceMap[$audienceInput];
if (($targetConfig['audience'] ?? '') === 'individual') {
    if ($targetUsername === '') {
        sendResponse(false, 'Please choose a recipient', null, 400);
    }
    $role = (string)($targetConfig['role'] ?? '');
    $checkStmt = $conn->prepare("SELECT username FROM users WHERE username = ? AND role = ? LIMIT 1");
    if (!$checkStmt) {
        sendResponse(false, 'Failed to validate recipient: ' . $conn->error, null, 500);
    }
    $checkStmt->bind_param("ss", $targetUsername, $role);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows === 0) {
        sendResponse(false, 'Selected recipient not found', null, 404);
    }
}

try {
    $attachment = uploadDirectorAnnouncementFile($_FILES['attachment'] ?? []);
} catch (Exception $e) {
    sendResponse(false, $e->getMessage(), null, 500);
}

$directorUsername = $_SESSION['username'];

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("
        INSERT INTO announcements
            (title, message, content, audience, target_mode, target_class_ids, target_usernames, priority, created_by_username, status, attachment_path, attachment_name, attachment_mime, attachment_size, created_at, updated_at)
        VALUES
            (?, ?, ?, ?, ?, NULL, ?, ?, ?, 'active', ?, ?, ?, ?, NOW(), NOW())
    ");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $content = $message;
    $targetMode = ($targetConfig['audience'] ?? '') === 'individual' ? 'individual' : null;
    $targetUsernames = $targetConfig['target_usernames'] ?? null;
    $attachmentPath = $attachment['path'];
    $attachmentName = $attachment['name'];
    $attachmentMime = $attachment['mime'];
    $attachmentSize = $attachment['size'];
    $audience = $targetConfig['audience'];

    $stmt->bind_param(
        "sssssssssssi",
        $title,
        $message,
        $content,
        $audience,
        $targetMode,
        $targetUsernames,
        $priority,
        $directorUsername,
        $attachmentPath,
        $attachmentName,
        $attachmentMime,
        $attachmentSize
    );

    if (!$stmt->execute()) {
        throw new Exception('Announcement creation failed: ' . $stmt->error);
    }

    $announcementId = (int)$conn->insert_id;
    logSystemActivity($conn, $directorUsername, 'CREATE_ANNOUNCEMENT', 'Created announcement: ' . $title . ' (Audience: ' . $audienceInput . ')', 'success');
    $conn->commit();

    sendResponse(true, 'Announcement sent successfully', [
        'announcement_id' => $announcementId,
        'title' => $title,
        'audience' => $audience,
        'priority' => $priority
    ], 201);
} catch (Exception $e) {
    $conn->rollback();
    sendResponse(false, 'Announcement creation failed: ' . $e->getMessage(), null, 500);
}

$conn->close();
?>
