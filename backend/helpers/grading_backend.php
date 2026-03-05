<?php
require_once __DIR__ . '/functions.php';

function ensureAssessmentTables(mysqli $conn): void {
    $sql = [];

    $sql[] = "
        CREATE TABLE IF NOT EXISTS assessment_structures (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            teacher_username VARCHAR(20) NOT NULL,
            grade_level VARCHAR(30) NOT NULL,
            class_id INT UNSIGNED NOT NULL,
            subject_id INT UNSIGNED NOT NULL,
            term VARCHAR(20) NOT NULL,
            academic_year_id INT UNSIGNED NOT NULL,
            title VARCHAR(120) DEFAULT NULL,
            total_points DECIMAL(7,2) NOT NULL DEFAULT 100.00,
            status ENUM('draft','active','closed') NOT NULL DEFAULT 'active',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_assessment_structure (teacher_username, class_id, subject_id, term, academic_year_id),
            KEY idx_as_subject (subject_id),
            KEY idx_as_year (academic_year_id),
            KEY idx_as_class (class_id),
            CONSTRAINT fk_as_teacher FOREIGN KEY (teacher_username) REFERENCES teachers(username) ON DELETE CASCADE,
            CONSTRAINT fk_as_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            CONSTRAINT fk_as_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
            CONSTRAINT fk_as_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $sql[] = "
        CREATE TABLE IF NOT EXISTS assessment_structure_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            structure_id INT UNSIGNED NOT NULL,
            item_name VARCHAR(100) NOT NULL,
            max_points DECIMAL(7,2) NOT NULL,
            item_order INT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_item_name (structure_id, item_name),
            UNIQUE KEY uk_item_order (structure_id, item_order),
            CONSTRAINT fk_asi_structure FOREIGN KEY (structure_id) REFERENCES assessment_structures(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $sql[] = "
        CREATE TABLE IF NOT EXISTS student_assessment_scores (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            structure_item_id INT UNSIGNED NOT NULL,
            student_username VARCHAR(20) NOT NULL,
            class_id INT UNSIGNED NOT NULL,
            score DECIMAL(7,2) NOT NULL,
            entered_by_teacher_username VARCHAR(20) NOT NULL,
            entered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_student_item_class (structure_item_id, student_username, class_id),
            KEY idx_sas_student (student_username),
            KEY idx_sas_class (class_id),
            CONSTRAINT fk_sas_item FOREIGN KEY (structure_item_id) REFERENCES assessment_structure_items(id) ON DELETE CASCADE,
            CONSTRAINT fk_sas_student FOREIGN KEY (student_username) REFERENCES students(username) ON DELETE CASCADE,
            CONSTRAINT fk_sas_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            CONSTRAINT fk_sas_teacher FOREIGN KEY (entered_by_teacher_username) REFERENCES teachers(username) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $sql[] = "
        CREATE TABLE IF NOT EXISTS student_assessment_compact_scores (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            structure_id INT UNSIGNED NOT NULL,
            class_id INT UNSIGNED NOT NULL,
            student_username VARCHAR(20) NOT NULL,
            scores_json LONGTEXT NOT NULL,
            total_score DECIMAL(7,2) NOT NULL DEFAULT 0.00,
            letter_grade VARCHAR(5) NOT NULL,
            grading_scale_id INT UNSIGNED DEFAULT NULL,
            entered_by_teacher_username VARCHAR(20) NOT NULL,
            entered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_sacs_unique (structure_id, class_id, student_username),
            KEY idx_sacs_student (student_username),
            KEY idx_sacs_class (class_id),
            KEY idx_sacs_scale (grading_scale_id),
            CONSTRAINT fk_sacs_structure FOREIGN KEY (structure_id) REFERENCES assessment_structures(id) ON DELETE CASCADE,
            CONSTRAINT fk_sacs_student FOREIGN KEY (student_username) REFERENCES students(username) ON DELETE CASCADE,
            CONSTRAINT fk_sacs_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            CONSTRAINT fk_sacs_teacher FOREIGN KEY (entered_by_teacher_username) REFERENCES teachers(username) ON DELETE RESTRICT,
            CONSTRAINT fk_sacs_scale FOREIGN KEY (grading_scale_id) REFERENCES grading_scales(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $sql[] = "
        CREATE TABLE IF NOT EXISTS assessment_structure_snapshots (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            structure_id INT UNSIGNED DEFAULT NULL,
            teacher_username VARCHAR(20) NOT NULL,
            grade_level VARCHAR(30) NOT NULL,
            class_id INT UNSIGNED NOT NULL,
            subject_id INT UNSIGNED NOT NULL,
            term VARCHAR(20) NOT NULL,
            academic_year_id INT UNSIGNED NOT NULL,
            total_points DECIMAL(7,2) NOT NULL DEFAULT 0.00,
            status ENUM('draft','active','closed') NOT NULL DEFAULT 'closed',
            snapshot_reason VARCHAR(120) DEFAULT 'structure_update',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ass_snap_lookup (teacher_username, class_id, subject_id, term, academic_year_id),
            KEY idx_ass_snap_structure (structure_id),
            CONSTRAINT fk_ass_snap_teacher FOREIGN KEY (teacher_username) REFERENCES teachers(username) ON DELETE CASCADE,
            CONSTRAINT fk_ass_snap_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            CONSTRAINT fk_ass_snap_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
            CONSTRAINT fk_ass_snap_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $sql[] = "
        CREATE TABLE IF NOT EXISTS assessment_structure_snapshot_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            snapshot_id INT UNSIGNED NOT NULL,
            source_item_id INT UNSIGNED DEFAULT NULL,
            item_name VARCHAR(100) NOT NULL,
            max_points DECIMAL(7,2) NOT NULL,
            item_order INT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ass_snap_items (snapshot_id),
            CONSTRAINT fk_ass_snap_items_snapshot FOREIGN KEY (snapshot_id) REFERENCES assessment_structure_snapshots(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $sql[] = "
        CREATE TABLE IF NOT EXISTS assessment_structure_snapshot_scores (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            snapshot_id INT UNSIGNED NOT NULL,
            class_id INT UNSIGNED NOT NULL,
            student_username VARCHAR(20) NOT NULL,
            scores_json LONGTEXT NOT NULL,
            total_score DECIMAL(7,2) NOT NULL DEFAULT 0.00,
            letter_grade VARCHAR(5) NOT NULL,
            grading_scale_id INT UNSIGNED DEFAULT NULL,
            entered_by_teacher_username VARCHAR(20) NOT NULL,
            entered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_ass_snap_score (snapshot_id, class_id, student_username),
            KEY idx_ass_snap_score_student (student_username),
            KEY idx_ass_snap_score_class (class_id),
            CONSTRAINT fk_ass_snap_score_snapshot FOREIGN KEY (snapshot_id) REFERENCES assessment_structure_snapshots(id) ON DELETE CASCADE,
            CONSTRAINT fk_ass_snap_score_student FOREIGN KEY (student_username) REFERENCES students(username) ON DELETE CASCADE,
            CONSTRAINT fk_ass_snap_score_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            CONSTRAINT fk_ass_snap_score_teacher FOREIGN KEY (entered_by_teacher_username) REFERENCES teachers(username) ON DELETE RESTRICT,
            CONSTRAINT fk_ass_snap_score_scale FOREIGN KEY (grading_scale_id) REFERENCES grading_scales(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    foreach ($sql as $query) {
        if (!$conn->query($query)) {
            throw new Exception('Failed to ensure assessment tables: ' . $conn->error);
        }
    }

    // Backward compatibility for older schemas.
    ensureColumnExists($conn, 'assessment_structures', 'grade_level', "VARCHAR(30) NOT NULL DEFAULT '' AFTER teacher_username");
    ensureColumnExists($conn, 'assessment_structures', 'class_id', "INT UNSIGNED NULL AFTER grade_level");
    ensureColumnExists($conn, 'assessment_structures', 'status', "ENUM('draft','active','closed') NOT NULL DEFAULT 'active' AFTER total_points");
    ensureColumnExists($conn, 'assessment_structure_snapshot_items', 'source_item_id', "INT UNSIGNED NULL AFTER snapshot_id");

    if (tableColumnExists($conn, 'assessment_structures', 'class_id')) {
        $conn->query("
            UPDATE assessment_structures a
            JOIN classes c ON c.id = a.class_id
            SET a.grade_level = c.grade_level
            WHERE (a.grade_level IS NULL OR a.grade_level = '')
        ");
    }
}

function tableColumnExists(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND column_name = ?
        LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception('Failed to prepare column-exists query: ' . $conn->error);
    }
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function ensureColumnExists(mysqli $conn, string $table, string $column, string $definition): void {
    if (tableColumnExists($conn, $table, $column)) {
        return;
    }

    $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
    if (!$conn->query($sql)) {
        throw new Exception("Failed to add column $table.$column: " . $conn->error);
    }
}

function normalizeScoresForStorage(array $scores, array $itemMax): array {
    $normalized = [];
    foreach ($scores as $itemIdRaw => $scoreRaw) {
        $itemId = (int)$itemIdRaw;
        if (!isset($itemMax[$itemId])) {
            continue;
        }
        $score = (float)$scoreRaw;
        $normalized[(string)$itemId] = $score;
    }
    ksort($normalized);
    return $normalized;
}

function decodeStoredScores(?string $scoresJson): array {
    if ($scoresJson === null || $scoresJson === '') {
        return [];
    }

    $decoded = json_decode($scoresJson, true);
    if (!is_array($decoded)) {
        return [];
    }

    $scores = [];
    foreach ($decoded as $itemId => $score) {
        $id = (string)((int)$itemId);
        $scores[$id] = (float)$score;
    }
    return $scores;
}

function normalizeGradeLevelKey(string $raw): string {
    $trimmed = trim($raw);
    if ($trimmed === '') {
        return '';
    }
    if (preg_match('/\d+/', $trimmed, $m)) {
        return $m[0];
    }
    return $trimmed;
}

function getActiveAcademicYearId(mysqli $conn): int {
    $result = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        return (int)$row['id'];
    }
    throw new Exception('No active academic year configured');
}

function getClassAcademicYearId(mysqli $conn, int $classId): int {
    $stmt = $conn->prepare("SELECT academic_year_id FROM classes WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $classId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row && !empty($row['academic_year_id'])) {
        return (int)$row['academic_year_id'];
    }
    return getActiveAcademicYearId($conn);
}

function resolveSubjectId(mysqli $conn, string $subjectInput): int {
    $subjectInput = trim($subjectInput);
    if ($subjectInput === '') {
        throw new Exception('Subject is required');
    }

    if (ctype_digit($subjectInput)) {
        $subjectId = (int)$subjectInput;
        $stmt = $conn->prepare("SELECT id FROM subjects WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $subjectId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 1) {
            return $subjectId;
        }
    }

    $stmt = $conn->prepare("SELECT id FROM subjects WHERE subject_name = ? LIMIT 1");
    $stmt->bind_param("s", $subjectInput);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        return (int)$row['id'];
    }

    throw new Exception('Subject not found: ' . $subjectInput);
}

function resolveGradeScale(mysqli $conn, float $marks): array {
    $stmt = $conn->prepare("
        SELECT id, grade
        FROM grading_scales
        WHERE ? BETWEEN min_marks AND max_marks
        ORDER BY max_marks DESC
        LIMIT 1
    ");
    $stmt->bind_param("d", $marks);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        return ['id' => (int)$row['id'], 'grade' => (string)$row['grade']];
    }

    if ($marks >= 90) return ['id' => 1, 'grade' => 'A+'];
    if ($marks >= 80) return ['id' => 2, 'grade' => 'A'];
    if ($marks >= 70) return ['id' => 3, 'grade' => 'B'];
    if ($marks >= 60) return ['id' => 4, 'grade' => 'C'];
    if ($marks >= 50) return ['id' => 5, 'grade' => 'D'];
    return ['id' => 6, 'grade' => 'F'];
}

function teacherAssignedToClass(mysqli $conn, string $teacherUsername, int $classId): bool {
    ensureAssignmentBlockColumn($conn);
    $stmt = $conn->prepare("
        SELECT id
        FROM assignments
        WHERE class_id = ? AND teacher_username = ? AND assignment_type = 'teacher' AND is_blocked = 0
        LIMIT 1
    ");
    $stmt->bind_param("is", $classId, $teacherUsername);
    $stmt->execute();
    return $stmt->get_result()->num_rows === 1;
}

function isStudentInClass(mysqli $conn, string $studentUsername, int $classId): bool {
    $stmt = $conn->prepare("SELECT id FROM class_enrollments WHERE student_username = ? AND class_id = ? LIMIT 1");
    $stmt->bind_param("si", $studentUsername, $classId);
    $stmt->execute();
    return $stmt->get_result()->num_rows === 1;
}

?>
