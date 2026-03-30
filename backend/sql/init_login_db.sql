-- =====================================================
-- SIMPLE NEW DATABASE FOR LOGIN (BEGINNER VERSION)
-- =====================================================

DROP DATABASE IF EXISTS bensa_school;
CREATE DATABASE bensa_school CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bensa_school;

-- Main users table used by login
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student','teacher','admin','director') NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Profile tables (only for full name display after login)
CREATE TABLE students (
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
    parent_phone VARCHAR(50) NULL,
    FOREIGN KEY (username) REFERENCES users(username) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    fname VARCHAR(50) NOT NULL,
    mname VARCHAR(50) NULL,
    lname VARCHAR(50) NOT NULL,
    department VARCHAR(100) NULL,
    subject VARCHAR(100) NULL,
    FOREIGN KEY (username) REFERENCES users(username) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    fname VARCHAR(50) NOT NULL,
    lname VARCHAR(50) NOT NULL,
    department VARCHAR(100) NULL,
    phone VARCHAR(50) NULL,
    FOREIGN KEY (username) REFERENCES users(username) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE directors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    fname VARCHAR(50) NOT NULL,
    lname VARCHAR(50) NOT NULL,
    FOREIGN KEY (username) REFERENCES users(username) ON DELETE CASCADE ON UPDATE CASCADE
);

-- =====================================================
-- TEST USERS (PASSWORD = 1234 for all)
-- =====================================================
INSERT INTO users (username, email, password, role, status) VALUES
('std001', 'student1@school.test', '1234', 'student', 'active'),
('tch001', 'teacher1@school.test', '1234', 'teacher', 'active'),
('adm001', 'admin1@school.test', '1234', 'admin', 'active'),
('dir001', 'director1@school.test', '1234', 'director', 'active');

INSERT INTO students (username, fname, lname) VALUES ('std001', 'Student', 'One');
INSERT INTO teachers (username, fname, lname) VALUES ('tch001', 'Teacher', 'One');
INSERT INTO admins (username, fname, lname) VALUES ('adm001', 'Admin', 'One');
INSERT INTO directors (username, fname, lname) VALUES ('dir001', 'Director', 'One');

UPDATE students
SET grade_level = '9', stream = 'A'
WHERE username = 'std001';

UPDATE teachers
SET department = 'Academics', subject = 'Mathematics'
WHERE username = 'tch001';

UPDATE admins
SET department = 'Registration', phone = ''
WHERE username = 'adm001';

-- =====================================================
-- MINIMAL TEACHER MODULE TABLES (CLASS PROJECT)
-- =====================================================

CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    grade_level VARCHAR(20) NULL,
    section VARCHAR(20) NULL
);

CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    teacher_username VARCHAR(50) NOT NULL,
    subject_name VARCHAR(100) NULL,
    assignment_type VARCHAR(20) NOT NULL DEFAULT 'teacher',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE class_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_username VARCHAR(50) NOT NULL,
    class_id INT NOT NULL,
    enrollment_date DATE NULL
);

CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_username VARCHAR(50) NOT NULL,
    class_id INT NOT NULL,
    teacher_username VARCHAR(50) NOT NULL,
    term VARCHAR(20) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    marks DECIMAL(6,2) NOT NULL DEFAULT 0,
    letter_grade VARCHAR(3) NOT NULL,
    entered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_grade (student_username, class_id, term, subject)
);

CREATE TABLE resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_username VARCHAR(50) NOT NULL,
    class_id INT NULL,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) NULL,
    description TEXT NULL,
    file_url VARCHAR(255) NULL,
    due_date DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_username VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    class_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE director_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    director_username VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    send_to VARCHAR(40) NOT NULL DEFAULT 'all',
    target_username VARCHAR(50) NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'normal',
    attachment_name VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE school_settings (
    id INT PRIMARY KEY,
    school_name VARCHAR(255) NOT NULL,
    school_email VARCHAR(255) NULL,
    school_phone VARCHAR(50) NULL,
    school_address TEXT NULL,
    academic_year VARCHAR(30) NULL,
    opening_date DATE NULL,
    term1_end DATE NULL,
    closing_date DATE NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- sample class + assignment
INSERT INTO classes (name, grade_level, section) VALUES ('Grade 9 - A', '9', 'A');
INSERT INTO assignments (class_id, teacher_username, subject_name, assignment_type) VALUES
(1, 'tch001', 'Mathematics', 'teacher'),
(1, 'tch001', 'English', 'teacher');
INSERT INTO class_enrollments (student_username, class_id, enrollment_date) VALUES ('std001', 1, CURDATE());
INSERT INTO subjects (subject_name) VALUES ('Mathematics'), ('English'), ('Biology');
INSERT INTO school_settings (id, school_name, school_email, school_phone, school_address, academic_year)
VALUES (1, 'BENSE SECONDARY HIGH SCHOOL', '', '', '', '');

-- Optional: quick check query
-- SELECT id, username, role, status FROM users;
