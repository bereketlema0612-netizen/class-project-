<?php
require_once '../config/db_config.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

session_start();
if (!isset($_SESSION['username'])) {
    sendResponse(false, 'Unauthorized', null, 403);
}
$sessionUsername = (string)$_SESSION['username'];
$sessionRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
$sessionRole = str_replace(['-', ' '], '_', $sessionRole);
$isTeacher = ($sessionRole === 'teacher');
if (!$isTeacher) {
    $authStmt = $conn->prepare("SELECT role FROM users WHERE username = ? LIMIT 1");
    if ($authStmt) {
        $authStmt->bind_param('s', $sessionUsername);
        $authStmt->execute();
        $authRow = $authStmt->get_result()->fetch_assoc();
        if ($authRow && strtolower((string)$authRow['role']) === 'teacher') {
            $isTeacher = true;
        }
        $authStmt->close();
    }
}
if (!$isTeacher) {
    sendResponse(false, 'Unauthorized', null, 403);
}

function hasColumn(mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $res && $res->num_rows > 0;
}

function ensureAnnouncementAttachmentColumns(mysqli $conn): void {
    $changes = [];
    if (!hasColumn($conn, 'announcements', 'attachment_path')) {
        $changes[] = "ADD COLUMN attachment_path VARCHAR(255) NULL";
    }
    if (!hasColumn($conn, 'announcements', 'attachment_name')) {
        $changes[] = "ADD COLUMN attachment_name VARCHAR(255) NULL";
    }
    if (!hasColumn($conn, 'announcements', 'attachment_mime')) {
        $changes[] = "ADD COLUMN attachment_mime VARCHAR(120) NULL";
    }
    if (!hasColumn($conn, 'announcements', 'attachment_size')) {
        $changes[] = "ADD COLUMN attachment_size INT UNSIGNED NULL";
    }
    if (!empty($changes)) {
        $sql = "ALTER TABLE announcements " . implode(', ', $changes);
        if (!$conn->query($sql)) {
            throw new Exception('Failed to update announcements table for attachments: ' . $conn->error);
        }
    }
}

function ensureAnnouncementTargetColumns(mysqli $conn): void {
    $changes = [];
    if (!hasColumn($conn, 'announcements', 'target_mode')) {
        $changes[] = "ADD COLUMN target_mode VARCHAR(30) NULL AFTER audience";
    }
    if (!hasColumn($conn, 'announcements', 'target_class_ids')) {
        $changes[] = "ADD COLUMN target_class_ids TEXT NULL AFTER target_mode";
    }
    if (!empty($changes)) {
        $sql = "ALTER TABLE announcements " . implode(', ', $changes);
        if (!$conn->query($sql)) {
            throw new Exception('Failed to update announcements table for target classes: ' . $conn->error);
        }
    }
}

function parseClassIdList(string $raw): array {
    if ($raw === '') return [];
    $parts = preg_split('/[,\s]+/', $raw);
    $ids = [];
    foreach ($parts as $p) {
        $n = (int)$p;
        if ($n > 0) $ids[$n] = $n;
    }
    return array_values($ids);
}

$teacherUsername = $sessionUsername;
$title = trim((string)($_POST['title'] ?? ''));
$content = trim((string)($_POST['content'] ?? ''));
$classId = (int)($_POST['class_id'] ?? 0);
$targetModeRaw = strtolower(trim((string)($_POST['target_mode'] ?? 'single')));
$targetMode = in_array($targetModeRaw, ['single', 'multiple', 'all_assigned'], true) ? $targetModeRaw : 'single';
$classIdsRaw = trim((string)($_POST['class_ids'] ?? ''));
$classIds = parseClassIdList($classIdsRaw);

if ($title === '' || $content === '') {
    sendResponse(false, 'Title and content are required', null, 400);
}

$attachmentPath = null;
$attachmentName = null;
$attachmentMime = null;
$attachmentSize = null;

try {
    ensureAnnouncementAttachmentColumns($conn);
    ensureAnnouncementTargetColumns($conn);
    $hasContentColumn = hasColumn($conn, 'announcements', 'content');

    // Collect teacher's currently assigned and unblocked classes.
    $assignedClassIds = [];
    $hasIsBlocked = hasColumn($conn, 'assignments', 'is_blocked');
    $assignSql = "
        SELECT DISTINCT class_id
        FROM assignments
        WHERE teacher_username = ? AND assignment_type = 'teacher'
    ";
    if ($hasIsBlocked) {
        $assignSql .= " AND is_blocked = 0";
    }
    $assignStmt = $conn->prepare($assignSql);
    if ($assignStmt) {
        $assignStmt->bind_param('s', $teacherUsername);
        $assignStmt->execute();
        $assignRes = $assignStmt->get_result();
        while ($r = $assignRes->fetch_assoc()) {
            $cid = (int)($r['class_id'] ?? 0);
            if ($cid > 0) {
                $assignedClassIds[$cid] = $cid;
            }
        }
        $assignStmt->close();
    }
    $assignedClassIds = array_values($assignedClassIds);
    if (count($assignedClassIds) === 0) {
        throw new Exception('No assigned classes found for this teacher');
    }

    $selectedClassIds = [];
    if ($targetMode === 'all_assigned') {
        $selectedClassIds = $assignedClassIds;
    } elseif ($targetMode === 'multiple') {
        $selectedClassIds = $classIds;
    } else {
        if ($classId > 0) {
            $selectedClassIds = [$classId];
        } elseif (count($classIds) > 0) {
            $selectedClassIds = [(int)$classIds[0]];
        }
    }

    if (count($selectedClassIds) === 0) {
        throw new Exception('Please select at least one class');
    }

    // Keep only classes assigned to this teacher.
    $allowed = array_flip($assignedClassIds);
    $filteredClassIds = [];
    foreach ($selectedClassIds as $cid) {
        $cid = (int)$cid;
        if ($cid > 0 && isset($allowed[$cid])) {
            $filteredClassIds[$cid] = $cid;
        }
    }
    $selectedClassIds = array_values($filteredClassIds);
    if (count($selectedClassIds) === 0) {
        throw new Exception('Selected classes are not assigned to this teacher');
    }

    $targetClassCsv = implode(',', $selectedClassIds);
    $audience = 'students';
    $targetModeDb = $targetMode === 'all_assigned' ? 'all_assigned' : (count($selectedClassIds) > 1 ? 'multiple' : 'single');

    if (isset($_FILES['attachment']) && is_array($_FILES['attachment']) && (int)($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['attachment'];
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Attachment upload failed');
        }

        $maxBytes = 10 * 1024 * 1024;
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            throw new Exception('Attachment size must be between 1 byte and 10 MB');
        }

        $originalName = (string)($file['name'] ?? '');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($originalName === '') {
            throw new Exception('Invalid file name');
        }

        $uploadDirFs = dirname(__DIR__) . '/uploads/announcements';
        if (!is_dir($uploadDirFs) && !mkdir($uploadDirFs, 0775, true)) {
            throw new Exception('Failed to create upload directory');
        }

        $safeBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        if ($safeBase === '' || $safeBase === null) {
            $safeBase = 'file';
        }
        $storedName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeBase . ($ext !== '' ? '.' . $ext : '');
        $targetFs = $uploadDirFs . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $targetFs)) {
            throw new Exception('Failed to save uploaded file');
        }

        $attachmentPath = 'uploads/announcements/' . $storedName;
        $attachmentName = $originalName;
        $attachmentMime = (string)($file['type'] ?? 'application/octet-stream');
        $attachmentSize = $size;
    }

    $message = $content;
    $classLabels = [];
    $classLabelSql = "SELECT id, grade_level, section FROM classes WHERE id IN (" . implode(',', array_fill(0, count($selectedClassIds), '?')) . ")";
    $classLabelStmt = $conn->prepare($classLabelSql);
    if ($classLabelStmt) {
        $types = str_repeat('i', count($selectedClassIds));
        $classLabelStmt->bind_param($types, ...$selectedClassIds);
        $classLabelStmt->execute();
        $classLabelRes = $classLabelStmt->get_result();
        while ($cl = $classLabelRes->fetch_assoc()) {
            $classLabels[] = 'Grade ' . $cl['grade_level'] . ' - ' . $cl['section'];
        }
        $classLabelStmt->close();
    }
    if (count($classLabels) > 0) {
        $message = '[Class: ' . implode(', ', $classLabels) . '] ' . $content;
    }

    $insertSql = $hasContentColumn
        ? "INSERT INTO announcements
            (title, message, content, audience, target_mode, target_class_ids, priority, created_by_username, status, attachment_path, attachment_name, attachment_mime, attachment_size, created_at, updated_at)
            VALUES
            (?, ?, ?, ?, ?, ?, 'Normal', ?, 'active', ?, ?, ?, ?, NOW(), NOW())"
        : "INSERT INTO announcements
            (title, message, audience, target_mode, target_class_ids, priority, created_by_username, status, attachment_path, attachment_name, attachment_mime, attachment_size, created_at, updated_at)
            VALUES
            (?, ?, ?, ?, ?, 'Normal', ?, 'active', ?, ?, ?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($insertSql);
    if (!$stmt) {
        throw new Exception('Failed to prepare insert: ' . $conn->error);
    }
    if ($hasContentColumn) {
        $stmt->bind_param(
            "ssssssssssi",
            $title,
            $message,
            $content,
            $audience,
            $targetModeDb,
            $targetClassCsv,
            $teacherUsername,
            $attachmentPath,
            $attachmentName,
            $attachmentMime,
            $attachmentSize
        );
    } else {
        $stmt->bind_param(
            "sssssssssi",
            $title,
            $message,
            $audience,
            $targetModeDb,
            $targetClassCsv,
            $teacherUsername,
            $attachmentPath,
            $attachmentName,
            $attachmentMime,
            $attachmentSize
        );
    }
    if (!$stmt->execute()) {
        throw new Exception('Failed to save announcement: ' . $stmt->error);
    }

    $announcementId = (int)$stmt->insert_id;
    logSystemActivity($conn, $teacherUsername, 'CREATE_ANNOUNCEMENT', 'Teacher created announcement #' . $announcementId, 'success');

    sendResponse(true, 'Announcement posted successfully', [
        'announcement_id' => $announcementId,
        'attachment_path' => $attachmentPath,
        'attachment_name' => $attachmentName
    ], 201);
} catch (Exception $e) {
    sendResponse(false, 'Failed to post announcement: ' . $e->getMessage(), null, 500);
}

$conn->close();
?>
