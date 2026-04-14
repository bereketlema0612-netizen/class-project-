<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_config.php';

function respond($success, $message, $data = null) {
    echo json_encode([
        'success' => (bool)$success,
        'message' => (string)$message,
        'data' => $data
    ]);
    exit;
}

function require_teacher($postOnly = false) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $username = isset($_SESSION['username']) ? (string)$_SESSION['username'] : '';
    $role = isset($_SESSION['role']) ? (string)$_SESSION['role'] : '';

    if ($username === '' || $role !== 'teacher') {
        respond(false, 'Unauthorized');
    }

    if ($postOnly && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(false, 'POST only');
    }

    return $username;
}

function read_json_body() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }
    return $data;
}

function safe_file_name($name) {
    return preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$name);
}

function save_uploaded_file($fileKey, $folderPath, $publicPrefix) {
    if (!isset($_FILES[$fileKey]) || !is_array($_FILES[$fileKey])) {
        return ['name' => '', 'url' => ''];
    }

    $file = $_FILES[$fileKey];
    if ((int)($file['error'] ?? 1) !== 0) {
        return ['name' => '', 'url' => ''];
    }

    if (!is_dir($folderPath)) {
        @mkdir($folderPath, 0777, true);
    }

    $original = basename((string)($file['name'] ?? ''));
    $safe = safe_file_name($original);
    $newName = date('YmdHis') . '_' . $safe;
    $target = rtrim($folderPath, '/\\') . DIRECTORY_SEPARATOR . $newName;

    if (!@move_uploaded_file((string)$file['tmp_name'], $target)) {
        respond(false, 'File upload failed');
    }

    return [
        'name' => $original,
        'url' => rtrim($publicPrefix, '/') . '/' . $newName
    ];
}
?>
