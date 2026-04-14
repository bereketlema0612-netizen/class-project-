-- Demo seed data for class project
-- Run this AFTER backend/sql/init_login_db.sql

USE bensa_school;

-- 1) CLASSES (4 classes = 20 students, 5 each)
INSERT INTO classes (id, name, grade_level, section) VALUES
(1, 'Grade 9 - A', '9', 'A'),
(2, 'Grade 10 - A', '10', 'A'),
(3, 'Grade 11 - Natural', '11', 'Natural'),
(4, 'Grade 12 - Social', '12', 'Social')
ON DUPLICATE KEY UPDATE
name = VALUES(name),
grade_level = VALUES(grade_level),
section = VALUES(section);

-- 2) SUBJECTS (unique list)
INSERT IGNORE INTO subjects (subject_name) VALUES
('Physics'),
('Chemistry'),
('Biology'),
('English'),
('Mathematics'),
('Civic'),
('Sport'),
('IT'),
('Economics');

-- 3) TEACHERS (10 teachers)
INSERT INTO users (username, email, password, role, status) VALUES
('tch101', 'tch101@school.test', '1234', 'teacher', 'active'),
('tch102', 'tch102@school.test', '1234', 'teacher', 'active'),
('tch103', 'tch103@school.test', '1234', 'teacher', 'active'),
('tch104', 'tch104@school.test', '1234', 'teacher', 'active'),
('tch105', 'tch105@school.test', '1234', 'teacher', 'active'),
('tch106', 'tch106@school.test', '1234', 'teacher', 'active'),
('tch107', 'tch107@school.test', '1234', 'teacher', 'active'),
('tch108', 'tch108@school.test', '1234', 'teacher', 'active'),
('tch109', 'tch109@school.test', '1234', 'teacher', 'active'),
('tch110', 'tch110@school.test', '1234', 'teacher', 'active');

INSERT INTO teachers (username, fname, lname, department, subject) VALUES
('tch101', 'James', 'Brown', 'Science', 'Physics'),
('tch102', 'Sara', 'Mamo', 'Science', 'Chemistry'),
('tch103', 'David', 'Alem', 'Science', 'Biology'),
('tch104', 'Helen', 'Kebede', 'Languages', 'English'),
('tch105', 'Ruth', 'Tola', 'Math', 'Mathematics'),
('tch106', 'Samuel', 'Hailu', 'Civics', 'Civic'),
('tch107', 'Abel', 'Worku', 'Sports', 'Sport'),
('tch108', 'Lily', 'Mekdes', 'ICT', 'IT'),
('tch109', 'Tesfaye', 'Bekele', 'Social', 'Economics'),
('tch110', 'Mimi', 'Solomon', 'Languages', 'English');

-- 4) STUDENTS (20 students)
INSERT INTO users (username, email, password, role, status) VALUES
('std101', 'std101@school.test', '1234', 'student', 'active'),
('std102', 'std102@school.test', '1234', 'student', 'active'),
('std103', 'std103@school.test', '1234', 'student', 'active'),
('std104', 'std104@school.test', '1234', 'student', 'active'),
('std105', 'std105@school.test', '1234', 'student', 'active'),
('std106', 'std106@school.test', '1234', 'student', 'active'),
('std107', 'std107@school.test', '1234', 'student', 'active'),
('std108', 'std108@school.test', '1234', 'student', 'active'),
('std109', 'std109@school.test', '1234', 'student', 'active'),
('std110', 'std110@school.test', '1234', 'student', 'active'),
('std111', 'std111@school.test', '1234', 'student', 'active'),
('std112', 'std112@school.test', '1234', 'student', 'active'),
('std113', 'std113@school.test', '1234', 'student', 'active'),
('std114', 'std114@school.test', '1234', 'student', 'active'),
('std115', 'std115@school.test', '1234', 'student', 'active'),
('std116', 'std116@school.test', '1234', 'student', 'active'),
('std117', 'std117@school.test', '1234', 'student', 'active'),
('std118', 'std118@school.test', '1234', 'student', 'active'),
('std119', 'std119@school.test', '1234', 'student', 'active'),
('std120', 'std120@school.test', '1234', 'student', 'active');

INSERT INTO students (username, fname, lname, grade_level, stream) VALUES
('std101', 'Almaz', 'Kassa', '9', NULL),
('std102', 'Biruk', 'Asefa', '9', NULL),
('std103', 'Chala', 'Teferi', '9', NULL),
('std104', 'Dawit', 'Musa', '9', NULL),
('std105', 'Eleni', 'Getu', '9', NULL),
('std106', 'Fiker', 'Yosef', '10', NULL),
('std107', 'Genet', 'Hagos', '10', NULL),
('std108', 'Hana', 'Tesfa', '10', NULL),
('std109', 'Ismael', 'Wako', '10', NULL),
('std110', 'Jemal', 'Hassan', '10', NULL),
('std111', 'Kalkidan', 'Tsegaye', '11', 'natural'),
('std112', 'Lensa', 'Mikael', '11', 'natural'),
('std113', 'Martha', 'Awol', '11', 'natural'),
('std114', 'Natan', 'Belay', '11', 'natural'),
('std115', 'Omar', 'Farah', '11', 'natural'),
('std116', 'Paulos', 'Kiros', '12', 'social'),
('std117', 'Rahel', 'Daniel', '12', 'social'),
('std118', 'Sami', 'Bekele', '12', 'social'),
('std119', 'Tina', 'Kifle', '12', 'social'),
('std120', 'Yosef', 'Abebe', '12', 'social');

-- 5) ENROLLMENTS (5 students per class)
INSERT INTO class_enrollments (student_username, class_id, enrollment_date) VALUES
('std101', 1, CURDATE()),
('std102', 1, CURDATE()),
('std103', 1, CURDATE()),
('std104', 1, CURDATE()),
('std105', 1, CURDATE()),
('std106', 2, CURDATE()),
('std107', 2, CURDATE()),
('std108', 2, CURDATE()),
('std109', 2, CURDATE()),
('std110', 2, CURDATE()),
('std111', 3, CURDATE()),
('std112', 3, CURDATE()),
('std113', 3, CURDATE()),
('std114', 3, CURDATE()),
('std115', 3, CURDATE()),
('std116', 4, CURDATE()),
('std117', 4, CURDATE()),
('std118', 4, CURDATE()),
('std119', 4, CURDATE()),
('std120', 4, CURDATE());

-- 6) ASSIGNMENTS (subjects per class)
-- Grade 9 and 10 subjects: phy, che, bio, eng, maths, civic, sport
INSERT INTO assignments (class_id, teacher_username, subject_name, assignment_type) VALUES
(1, 'tch101', 'Physics', 'teacher'),
(1, 'tch102', 'Chemistry', 'teacher'),
(1, 'tch103', 'Biology', 'teacher'),
(1, 'tch104', 'English', 'teacher'),
(1, 'tch105', 'Mathematics', 'teacher'),
(1, 'tch106', 'Civic', 'teacher'),
(1, 'tch107', 'Sport', 'teacher'),
(2, 'tch101', 'Physics', 'teacher'),
(2, 'tch102', 'Chemistry', 'teacher'),
(2, 'tch103', 'Biology', 'teacher'),
(2, 'tch110', 'English', 'teacher'),
(2, 'tch105', 'Mathematics', 'teacher'),
(2, 'tch106', 'Civic', 'teacher'),
(2, 'tch107', 'Sport', 'teacher');

-- Grade 11 and 12 natural subjects: phy, che, bio, eng, maths, it
INSERT INTO assignments (class_id, teacher_username, subject_name, assignment_type) VALUES
(3, 'tch101', 'Physics', 'teacher'),
(3, 'tch102', 'Chemistry', 'teacher'),
(3, 'tch103', 'Biology', 'teacher'),
(3, 'tch104', 'English', 'teacher'),
(3, 'tch105', 'Mathematics', 'teacher'),
(3, 'tch108', 'IT', 'teacher');

-- Grade 11 and 12 social subjects: phy, che, bio, eng, maths, eco
INSERT INTO assignments (class_id, teacher_username, subject_name, assignment_type) VALUES
(4, 'tch101', 'Physics', 'teacher'),
(4, 'tch102', 'Chemistry', 'teacher'),
(4, 'tch103', 'Biology', 'teacher'),
(4, 'tch110', 'English', 'teacher'),
(4, 'tch105', 'Mathematics', 'teacher'),
(4, 'tch109', 'Economics', 'teacher');
