<?php

function generateRoleUsername($conn, $role) {
    $prefixMap = [
        'student' => 'STU',
        'teacher' => 'TCH',
        'admin' => 'ADM',
        'director' => 'DIR'
    ];

    if (!isset($prefixMap[$role])) {
        return '';
    }

    $prefix = $prefixMap[$role];
    $stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(username, 4) AS UNSIGNED)) as max_id FROM users WHERE role = ?");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $nextNum = ($row['max_id'] ?? 0) + 1;

    return $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
}

function generateStudentID($conn) {
    return generateRoleUsername($conn, 'student');
}

function generateTeacherID($conn) {
    return generateRoleUsername($conn, 'teacher');
}

function generateUsername($firstName, $lastName, $conn) {
    $baseUsername = strtolower($firstName . $lastName);
    $username = $baseUsername;
    $counter = 1;
    
    while (true) {
        $result = $conn->query("SELECT id FROM users WHERE username = '$username'");
        if ($result->num_rows == 0) {
            break;
        }
        $username = $baseUsername . $counter;
        $counter++;
    }
    
    return $username;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    if (!preg_match('/^[A-Z0-9]{5}$/', $password)) {
        return ['valid' => false, 'message' => 'Password must be exactly 5 characters using A-Z and 0-9 only'];
    }
    return ['valid' => true];
}

function generateTemporaryPassword($length = 5) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }

    return $password;
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function assignmentBlockColumnExists($conn) {
    $res = $conn->query("SHOW COLUMNS FROM assignments LIKE 'is_blocked'");
    return $res && $res->num_rows > 0;
}

function ensureAssignmentBlockColumn($conn) {
    if (assignmentBlockColumnExists($conn)) {
        return;
    }
    $conn->query("ALTER TABLE assignments ADD COLUMN is_blocked TINYINT(1) NOT NULL DEFAULT 0 AFTER assignment_type");
}

function hashPassword($password) {
    return $password;
}

function verifyPassword($password, $hash) {
    return $password === $hash;
}

function checkEmailExists($email, $conn) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function sendResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

function calculateAge($dob) {
    $birth = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birth);
    return $age->y;
}

function logSystemActivity($conn, $username, $action, $description, $status) {
    // Handle both schema variants:
    // 1) system_logs(username, ...)
    // 2) system_logs(user_id, ...)
    $hasUsernameCol = false;
    $hasUserIdCol = false;
    $colRes = $conn->query("SHOW COLUMNS FROM system_logs LIKE 'username'");
    if ($colRes && $colRes->num_rows > 0) {
        $hasUsernameCol = true;
    }
    $colRes = $conn->query("SHOW COLUMNS FROM system_logs LIKE 'user_id'");
    if ($colRes && $colRes->num_rows > 0) {
        $hasUserIdCol = true;
    }

    if ($hasUsernameCol) {
        $stmt = $conn->prepare("INSERT INTO system_logs (username, action, description, status, timestamp) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("ssss", $username, $action, $description, $status);
            $stmt->execute();
        }
        return;
    }

    if ($hasUserIdCol) {
        $userId = null;
        $uStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        if ($uStmt) {
            $uStmt->bind_param("s", $username);
            if ($uStmt->execute()) {
                $row = $uStmt->get_result()->fetch_assoc();
                if ($row && isset($row['id'])) {
                    $userId = (int)$row['id'];
                }
            }
        }
        $stmt = $conn->prepare("INSERT INTO system_logs (user_id, action, description, status, timestamp) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("isss", $userId, $action, $description, $status);
            $stmt->execute();
        }
    }
}

function authenticateUser($conn, $username, $password) {
    $stmt = $conn->prepare("SELECT id, username, email, password, role, status FROM users WHERE username = ? AND status = 'active'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 1) {
        return ['authenticated' => false, 'message' => 'Invalid credentials'];
    }
    
    $user = $result->fetch_assoc();
    
    if (!verifyPassword($password, $user['password'])) {
        return ['authenticated' => false, 'message' => 'Invalid credentials'];
    }
    
    return ['authenticated' => true, 'user' => $user];
}

?>
