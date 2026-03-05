<?php

function rss_table_exists(mysqli $conn, string $table): bool {
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

function rss_has_column(mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $res && $res->num_rows > 0;
}

function ensure_learning_resources_table(mysqli $conn): void {
    if (rss_table_exists($conn, 'learning_resources')) {
        return;
    }

    $sql = "
        CREATE TABLE learning_resources (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            teacher_username VARCHAR(20) NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT DEFAULT NULL,
            resource_type VARCHAR(30) NOT NULL DEFAULT 'resource',
            due_date DATE DEFAULT NULL,
            target_mode VARCHAR(30) NOT NULL DEFAULT 'single',
            target_class_ids TEXT NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_mime VARCHAR(120) DEFAULT NULL,
            file_size INT(10) UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_lr_teacher (teacher_username),
            KEY idx_lr_type (resource_type),
            KEY idx_lr_due (due_date),
            KEY idx_lr_created (created_at),
            CONSTRAINT fk_lr_teacher FOREIGN KEY (teacher_username) REFERENCES teachers (username) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if (!$conn->query($sql)) {
        throw new Exception('Failed to create learning_resources table: ' . $conn->error);
    }
}

function ensure_student_resource_submissions_table(mysqli $conn): void {
    if (rss_table_exists($conn, 'student_resource_submissions')) {
        return;
    }

    $sql = "
        CREATE TABLE student_resource_submissions (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            resource_id INT(10) UNSIGNED NOT NULL,
            student_username VARCHAR(20) NOT NULL,
            teacher_username VARCHAR(20) NOT NULL,
            class_id INT(10) UNSIGNED DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_mime VARCHAR(120) DEFAULT NULL,
            file_size INT(10) UNSIGNED DEFAULT NULL,
            status ENUM('submitted','seen','graded') NOT NULL DEFAULT 'submitted',
            submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            seen_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_srs_one_per_student (resource_id, student_username),
            KEY idx_srs_teacher (teacher_username),
            KEY idx_srs_class (class_id),
            KEY idx_srs_status (status),
            KEY idx_srs_submitted (submitted_at),
            CONSTRAINT fk_srs_resource FOREIGN KEY (resource_id) REFERENCES learning_resources (id) ON DELETE CASCADE,
            CONSTRAINT fk_srs_student FOREIGN KEY (student_username) REFERENCES students (username) ON DELETE CASCADE,
            CONSTRAINT fk_srs_teacher FOREIGN KEY (teacher_username) REFERENCES teachers (username) ON DELETE CASCADE,
            CONSTRAINT fk_srs_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if (!$conn->query($sql)) {
        throw new Exception('Failed to create student_resource_submissions table: ' . $conn->error);
    }
}

function ensure_resource_submission_schema(mysqli $conn): void {
    ensure_learning_resources_table($conn);
    ensure_student_resource_submissions_table($conn);
}

function parse_csv_ids(string $raw): array {
    $out = [];
    if ($raw === '') return $out;
    $parts = preg_split('/[,\s]+/', $raw);
    foreach ($parts as $part) {
        $id = (int)$part;
        if ($id > 0) $out[$id] = $id;
    }
    return array_values($out);
}

function class_csv_visible_to_student(string $csv, array $studentClassIds): bool {
    $csv = trim($csv);
    if ($csv === '') return true;
    $targets = parse_csv_ids($csv);
    if (count($targets) === 0) return true;
    $studentLookup = array_flip($studentClassIds);
    foreach ($targets as $cid) {
        if (isset($studentLookup[(int)$cid])) {
            return true;
        }
    }
    return false;
}

