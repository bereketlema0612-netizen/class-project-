-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 06, 2026 at 12:31 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `school_management_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `id` int(10) UNSIGNED NOT NULL,
  `academic_year` varchar(30) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`id`, `academic_year`, `start_date`, `end_date`, `is_active`) VALUES
(1, '2025/2026', '2025-09-01', '2026-06-30', 1);

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(20) NOT NULL,
  `fname` varchar(60) NOT NULL,
  `mname` varchar(60) DEFAULT NULL,
  `lname` varchar(60) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `fname`, `mname`, `lname`, `created_at`) VALUES
(1, 'ADM001', 'Marta', NULL, 'Alemu', '2026-03-05 09:54:15'),
(2, 'ADM002', 'Samuel', NULL, 'Kebede', '2026-03-05 09:54:15');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text DEFAULT NULL,
  `content` text DEFAULT NULL,
  `audience` enum('all','students','teachers') NOT NULL,
  `target_mode` varchar(30) DEFAULT NULL,
  `target_class_ids` text DEFAULT NULL,
  `priority` enum('Normal','High','Urgent') NOT NULL DEFAULT 'Normal',
  `created_by_username` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_name` varchar(255) DEFAULT NULL,
  `attachment_mime` varchar(120) DEFAULT NULL,
  `attachment_size` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `message`, `content`, `audience`, `target_mode`, `target_class_ids`, `priority`, `created_by_username`, `status`, `created_at`, `updated_at`, `attachment_path`, `attachment_name`, `attachment_mime`, `attachment_size`) VALUES
(1, 'file', '[Class: Grade 9 - A] gfb', 'gfb', 'all', NULL, NULL, 'Normal', 'TCH101', 'active', '2026-03-05 21:47:31', '2026-03-05 21:47:31', 'uploads/announcements/20260305_194731_7f2b242f_05.html', '05.html', 'text/html', 1021),
(2, 'file', '[Class: Grade 9 - A] fefv', 'fefv', 'all', NULL, NULL, 'Normal', 'TCH101', 'active', '2026-03-05 22:01:20', '2026-03-05 22:01:20', NULL, NULL, NULL, NULL),
(3, 'file', '[Class: Grade 9 - A] jknk', 'jknk', 'students', 'single', '1', 'Normal', 'TCH101', 'active', '2026-03-06 01:43:48', '2026-03-06 01:43:48', 'uploads/announcements/20260305_234348_5fd8a279_Worksheet_on_HTML.pdf', 'Worksheet on HTML.pdf', 'application/pdf', 796421),
(4, 'class', '[Class: Grade 9 - A] no class to day', 'no class to day', 'students', 'single', '1', 'Normal', 'TCH101', 'active', '2026-03-06 02:20:40', '2026-03-06 02:20:40', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `assessment_structures`
--

CREATE TABLE `assessment_structures` (
  `id` int(10) UNSIGNED NOT NULL,
  `teacher_username` varchar(20) NOT NULL,
  `grade_level` varchar(30) NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `term` varchar(20) NOT NULL,
  `academic_year_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(120) DEFAULT NULL,
  `total_points` decimal(7,2) NOT NULL DEFAULT 100.00,
  `status` enum('draft','active','closed') NOT NULL DEFAULT 'active',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assessment_structures`
--

INSERT INTO `assessment_structures` (`id`, `teacher_username`, `grade_level`, `class_id`, `subject_id`, `term`, `academic_year_id`, `title`, `total_points`, `status`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'TCH101', '9', 1, 6, 'Term1', 1, 'Biology Term1', 100.00, 'active', 1, '2026-03-05 09:54:15', '2026-03-05 20:21:20'),
(2, 'TCH103', '10', 3, 2, 'Term1', 1, 'English Term1', 100.00, 'active', 1, '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(3, 'TCH105', '11', 5, 6, 'Term1', 1, NULL, 100.00, 'active', 1, '2026-03-05 21:08:03', '2026-03-05 21:08:03');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_structure_items`
--

CREATE TABLE `assessment_structure_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `structure_id` int(10) UNSIGNED NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `max_points` decimal(7,2) NOT NULL,
  `item_order` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assessment_structure_items`
--

INSERT INTO `assessment_structure_items` (`id`, `structure_id`, `item_name`, `max_points`, `item_order`, `created_at`) VALUES
(4, 2, 'Quiz', 10.00, 1, '2026-03-05 09:54:15'),
(5, 2, 'Project', 15.00, 2, '2026-03-05 09:54:15'),
(6, 2, 'Mid Exam', 25.00, 3, '2026-03-05 09:54:15'),
(7, 2, 'Final', 50.00, 4, '2026-03-05 09:54:15'),
(17, 1, 'Quiz', 20.00, 1, '2026-03-05 20:21:20'),
(18, 1, 'Mid Exam', 40.00, 2, '2026-03-05 20:21:20'),
(19, 1, 'Final', 40.00, 3, '2026-03-05 20:21:20'),
(20, 3, '100', 100.00, 1, '2026-03-05 21:08:03');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_structure_snapshots`
--

CREATE TABLE `assessment_structure_snapshots` (
  `id` int(10) UNSIGNED NOT NULL,
  `structure_id` int(10) UNSIGNED DEFAULT NULL,
  `teacher_username` varchar(20) NOT NULL,
  `grade_level` varchar(30) NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `term` varchar(20) NOT NULL,
  `academic_year_id` int(10) UNSIGNED NOT NULL,
  `total_points` decimal(7,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','active','closed') NOT NULL DEFAULT 'closed',
  `snapshot_reason` varchar(120) DEFAULT 'structure_update',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assessment_structure_snapshots`
--

INSERT INTO `assessment_structure_snapshots` (`id`, `structure_id`, `teacher_username`, `grade_level`, `class_id`, `subject_id`, `term`, `academic_year_id`, `total_points`, `status`, `snapshot_reason`, `created_at`) VALUES
(2, 1, 'TCH101', '9', 1, 6, 'Term1', 1, 100.00, 'active', 'structure_update', '2026-03-05 20:21:20');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_structure_snapshot_items`
--

CREATE TABLE `assessment_structure_snapshot_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `snapshot_id` int(10) UNSIGNED NOT NULL,
  `source_item_id` int(10) UNSIGNED DEFAULT NULL,
  `item_name` varchar(100) NOT NULL,
  `max_points` decimal(7,2) NOT NULL,
  `item_order` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assessment_structure_snapshot_items`
--

INSERT INTO `assessment_structure_snapshot_items` (`id`, `snapshot_id`, `source_item_id`, `item_name`, `max_points`, `item_order`, `created_at`) VALUES
(4, 2, 14, 'Quiz', 20.00, 1, '2026-03-05 20:21:20'),
(5, 2, 15, 'Mid Exam', 30.00, 2, '2026-03-05 20:21:20'),
(6, 2, 16, 'Final', 50.00, 3, '2026-03-05 20:21:20');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_structure_snapshot_scores`
--

CREATE TABLE `assessment_structure_snapshot_scores` (
  `id` int(10) UNSIGNED NOT NULL,
  `snapshot_id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `student_username` varchar(20) NOT NULL,
  `scores_json` longtext NOT NULL,
  `total_score` decimal(7,2) NOT NULL DEFAULT 0.00,
  `letter_grade` varchar(5) NOT NULL,
  `grading_scale_id` int(10) UNSIGNED DEFAULT NULL,
  `entered_by_teacher_username` varchar(20) NOT NULL,
  `entered_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assessment_structure_snapshot_scores`
--

INSERT INTO `assessment_structure_snapshot_scores` (`id`, `snapshot_id`, `class_id`, `student_username`, `scores_json`, `total_score`, `letter_grade`, `grading_scale_id`, `entered_by_teacher_username`, `entered_at`, `updated_at`) VALUES
(8, 2, 1, 'STU201', '{\"14\":7,\"15\":23,\"16\":45}', 75.00, 'B', 3, 'TCH101', '2026-03-05 09:54:15', '2026-03-05 20:15:29'),
(9, 2, 1, 'STU202', '{\"14\":7,\"15\":23,\"16\":45}', 75.00, 'B', 3, 'TCH101', '2026-03-05 19:50:21', '2026-03-05 20:15:29'),
(10, 2, 1, 'STU203', '{\"14\":7,\"15\":23,\"16\":45}', 75.00, 'B', 3, 'TCH101', '2026-03-05 19:50:21', '2026-03-05 20:15:29'),
(11, 2, 1, 'STU204', '{\"14\":7,\"15\":23,\"16\":45}', 75.00, 'B', 3, 'TCH101', '2026-03-05 19:50:21', '2026-03-05 20:15:29'),
(12, 2, 1, 'STU205', '{\"14\":7,\"15\":23,\"16\":50}', 80.00, 'A', 2, 'TCH101', '2026-03-05 19:50:21', '2026-03-05 20:15:29');

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `teacher_username` varchar(20) DEFAULT NULL,
  `assignment_type` enum('teacher','homework','exam','project') NOT NULL DEFAULT 'teacher',
  `is_blocked` tinyint(1) NOT NULL DEFAULT 0,
  `subject_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `name` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `assignment_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`id`, `class_id`, `teacher_username`, `assignment_type`, `is_blocked`, `subject_id`, `title`, `name`, `description`, `assignment_date`, `due_date`, `created_at`) VALUES
(1, 1, 'TCH101', 'teacher', 0, 6, 'Teacher-Class Assignment', 'Teacher-Class Assignment', 'Teacher assigned to class', '2026-03-05', NULL, '2026-03-05 19:20:04'),
(3, 5, 'TCH105', 'teacher', 0, 6, 'Teacher-Class Assignment', 'Teacher-Class Assignment', 'Teacher assigned to class', '2026-03-05', NULL, '2026-03-05 21:06:01'),
(4, 5, 'TCH101', 'teacher', 1, 6, 'Teacher-Class Assignment', 'Teacher-Class Assignment', 'Teacher assigned to class', '2026-03-05', NULL, '2026-03-05 21:26:48');

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_username` varchar(20) NOT NULL,
  `certificate_number` varchar(80) NOT NULL,
  `type` varchar(100) DEFAULT NULL,
  `certificate_type` varchar(100) DEFAULT NULL,
  `issued_date` date NOT NULL,
  `academic_year_id` int(10) UNSIGNED DEFAULT NULL,
  `issued_by_username` varchar(20) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificates`
--

INSERT INTO `certificates` (`id`, `student_username`, `certificate_number`, `type`, `certificate_type`, `issued_date`, `academic_year_id`, `issued_by_username`, `remarks`, `created_at`) VALUES
(1, 'STU211', 'REGI-2026-0001', 'Registration Slip', 'Registration Slip', '2026-03-09', 1, 'ADM001', 'Registration slip issued', '2026-03-05 13:35:22');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(10) UNSIGNED NOT NULL,
  `grade_level` varchar(30) NOT NULL,
  `section` varchar(30) NOT NULL,
  `stream` enum('natural','social') DEFAULT NULL,
  `teacher_username` varchar(20) DEFAULT NULL,
  `academic_year_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `grade_level`, `section`, `stream`, `teacher_username`, `academic_year_id`, `created_at`) VALUES
(1, '9', 'A', NULL, 'TCH101', 1, '2026-03-05 09:54:15'),
(2, '9', 'B', NULL, 'TCH102', 1, '2026-03-05 09:54:15'),
(3, '10', 'A', NULL, NULL, 1, '2026-03-05 09:54:15'),
(4, '10', 'B', NULL, 'TCH104', 1, '2026-03-05 09:54:15'),
(5, '11', 'A', 'natural', 'TCH105', 1, '2026-03-05 09:54:15'),
(6, '12', 'A', 'natural', NULL, 1, '2026-03-05 09:54:15');

-- --------------------------------------------------------

--
-- Table structure for table `class_enrollments`
--

CREATE TABLE `class_enrollments` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_username` varchar(20) NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `enrollment_date` date NOT NULL DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class_enrollments`
--

INSERT INTO `class_enrollments` (`id`, `student_username`, `class_id`, `enrollment_date`) VALUES
(6, 'STU211', 3, '2026-03-05'),
(7, 'STU212', 3, '2026-03-05'),
(8, 'STU213', 3, '2026-03-05'),
(9, 'STU214', 3, '2026-03-05'),
(10, 'STU215', 3, '2026-03-05'),
(78, 'STU216', 1, '2026-03-05'),
(79, 'STU201', 1, '2026-03-05'),
(80, 'STU202', 1, '2026-03-05'),
(81, 'STU203', 1, '2026-03-05'),
(82, 'STU204', 1, '2026-03-05'),
(83, 'STU205', 1, '2026-03-05'),
(84, 'STU217', 6, '2026-03-05'),
(89, 'STU218', 5, '2026-03-05'),
(90, 'STU219', 5, '2026-03-05');

-- --------------------------------------------------------

--
-- Table structure for table `class_schedules`
--

CREATE TABLE `class_schedules` (
  `id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED DEFAULT NULL,
  `teacher_username` varchar(20) DEFAULT NULL,
  `day` varchar(20) DEFAULT NULL,
  `day_of_week` varchar(20) DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `subject` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class_schedules`
--

INSERT INTO `class_schedules` (`id`, `class_id`, `subject_id`, `teacher_username`, `day`, `day_of_week`, `start_time`, `end_time`, `room_number`, `subject`, `created_at`) VALUES
(1, 3, 6, 'TCH104', 'Monday', 'Monday', '11:48:00', '17:48:00', '12', 'Biology', '2026-03-05 22:49:29'),
(2, 1, 6, 'TCH101', 'Monday', 'Monday', '08:00:00', '08:55:00', 'room|001', 'Biology', '2026-03-05 23:23:42'),
(3, 1, 15, NULL, 'Tuesday', 'Tuesday', '08:00:00', '08:55:00', 'room|', 'Agriculture', '2026-03-05 23:23:42'),
(4, 1, 15, NULL, 'Wednesday', 'Wednesday', '08:00:00', '08:55:00', 'lab|', 'Agriculture', '2026-03-05 23:23:42'),
(5, 1, 5, NULL, 'Wednesday', 'Wednesday', '09:00:00', '09:55:00', 'room|', 'Chemistry', '2026-03-05 23:23:42'),
(6, 1, 6, 'TCH101', 'Monday', 'Monday', '09:00:00', '09:55:00', 'room|001', 'Biology', '2026-03-05 23:31:09'),
(7, 1, 8, NULL, 'Monday', 'Monday', '10:15:00', '11:10:00', 'lab|', 'History', '2026-03-05 23:31:09'),
(8, 1, 2, NULL, 'Monday', 'Monday', '13:30:00', '14:25:00', 'room|', 'English', '2026-03-05 23:31:09'),
(9, 1, 11, NULL, 'Monday', 'Monday', '14:30:00', '15:25:00', 'room|', 'Introduction to AI', '2026-03-05 23:31:09'),
(10, 1, 6, 'TCH101', 'Monday', 'Monday', '11:15:00', '12:15:00', 'room|001', 'Biology', '2026-03-06 00:10:36');

-- --------------------------------------------------------

--
-- Table structure for table `curriculum_subjects`
--

CREATE TABLE `curriculum_subjects` (
  `id` int(10) UNSIGNED NOT NULL,
  `grade_level` varchar(10) NOT NULL,
  `stream` enum('natural','social') DEFAULT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(10) UNSIGNED NOT NULL,
  `department_name` varchar(120) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `directors`
--

CREATE TABLE `directors` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(20) NOT NULL,
  `fname` varchar(60) NOT NULL,
  `mname` varchar(60) DEFAULT NULL,
  `lname` varchar(60) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `directors`
--

INSERT INTO `directors` (`id`, `username`, `fname`, `mname`, `lname`, `created_at`) VALUES
(1, 'DIR001', 'Bekele', NULL, 'Tadesse', '2026-03-05 09:54:15');

-- --------------------------------------------------------

--
-- Table structure for table `final_grades`
--

CREATE TABLE `final_grades` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_username` varchar(20) NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `term` varchar(20) NOT NULL,
  `academic_year_id` int(10) UNSIGNED NOT NULL,
  `total_marks` decimal(7,2) NOT NULL,
  `letter_grade` varchar(5) NOT NULL,
  `teacher_username` varchar(20) NOT NULL,
  `structure_id` int(10) UNSIGNED DEFAULT NULL,
  `entered_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `final_grades`
--

INSERT INTO `final_grades` (`id`, `student_username`, `class_id`, `subject_id`, `term`, `academic_year_id`, `total_marks`, `letter_grade`, `teacher_username`, `structure_id`, `entered_at`, `updated_at`) VALUES
(1, 'STU201', 1, 6, 'Term1', 1, 91.00, 'A+', 'TCH101', 1, '2026-03-05 09:54:15', '2026-03-05 20:22:53'),
(2, 'STU211', 3, 2, 'Term1', 1, 89.00, 'A', 'TCH103', 2, '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(19, 'STU202', 1, 6, 'Term1', 1, 0.00, 'F', 'TCH101', 1, '2026-03-05 19:50:21', '2026-03-05 20:22:53'),
(20, 'STU203', 1, 6, 'Term1', 1, 0.00, 'F', 'TCH101', 1, '2026-03-05 19:50:21', '2026-03-05 20:22:53'),
(21, 'STU204', 1, 6, 'Term1', 1, 0.00, 'F', 'TCH101', 1, '2026-03-05 19:50:21', '2026-03-05 20:22:53'),
(22, 'STU205', 1, 6, 'Term1', 1, 0.00, 'F', 'TCH101', 1, '2026-03-05 19:50:21', '2026-03-05 20:22:53');

-- --------------------------------------------------------

--
-- Table structure for table `grading_scales`
--

CREATE TABLE `grading_scales` (
  `id` int(10) UNSIGNED NOT NULL,
  `min_marks` decimal(5,2) NOT NULL,
  `max_marks` decimal(5,2) NOT NULL,
  `grade` varchar(5) NOT NULL,
  `remark` varchar(80) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grading_scales`
--

INSERT INTO `grading_scales` (`id`, `min_marks`, `max_marks`, `grade`, `remark`) VALUES
(1, 90.00, 100.00, 'A+', 'Excellent'),
(2, 80.00, 89.99, 'A', 'Very Good'),
(3, 70.00, 79.99, 'B', 'Good'),
(4, 60.00, 69.99, 'C', 'Satisfactory'),
(5, 50.00, 59.99, 'D', 'Pass'),
(6, 0.00, 49.99, 'F', 'Fail');

-- --------------------------------------------------------

--
-- Table structure for table `learning_resources`
--

CREATE TABLE `learning_resources` (
  `id` int(10) UNSIGNED NOT NULL,
  `teacher_username` varchar(20) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `resource_type` varchar(30) NOT NULL DEFAULT 'resource',
  `due_date` date DEFAULT NULL,
  `target_mode` varchar(30) NOT NULL DEFAULT 'single',
  `target_class_ids` text NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_mime` varchar(120) DEFAULT NULL,
  `file_size` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `learning_resources`
--

INSERT INTO `learning_resources` (`id`, `teacher_username`, `title`, `description`, `resource_type`, `due_date`, `target_mode`, `target_class_ids`, `file_path`, `file_name`, `file_mime`, `file_size`, `created_at`, `updated_at`) VALUES
(1, 'TCH101', 'assignment', 'bsdfhxb', 'assignment', '2026-03-10', 'single', '1', 'uploads/resources/20260306_001941_0fe5caab_Web_Programming_Laboratory__Manual.pdf', 'Web Programming Laboratory  Manual.pdf', 'application/pdf', 3013190, '2026-03-06 02:19:41', '2026-03-06 02:19:41');

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_username` varchar(20) NOT NULL,
  `from_grade` varchar(30) NOT NULL,
  `to_grade` varchar(30) NOT NULL,
  `promoted_date` date NOT NULL,
  `promoted_by_username` varchar(20) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(20) NOT NULL,
  `role` enum('student','teacher','admin','director') NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `submitted_by_admin` tinyint(1) NOT NULL DEFAULT 0,
  `approved_at` datetime DEFAULT NULL,
  `approved_by_username` varchar(20) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejected_by_username` varchar(20) DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registrations`
--

INSERT INTO `registrations` (`id`, `username`, `role`, `status`, `submitted_at`, `submitted_by_admin`, `approved_at`, `approved_by_username`, `rejected_at`, `rejected_by_username`, `remarks`) VALUES
(1, 'STU216', 'student', 'approved', '2026-03-05 13:33:08', 0, '2026-03-05 13:33:08', NULL, NULL, NULL, NULL),
(2, 'STU217', 'student', 'approved', '2026-03-05 13:34:04', 0, '2026-03-05 13:34:04', NULL, NULL, NULL, NULL),
(3, 'STU218', 'student', 'approved', '2026-03-05 21:05:07', 0, '2026-03-05 21:05:07', NULL, NULL, NULL, NULL),
(4, 'STU219', 'student', 'approved', '2026-03-06 00:37:38', 0, '2026-03-06 00:37:38', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `registration_admins`
--

CREATE TABLE `registration_admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_counters`
--

CREATE TABLE `role_counters` (
  `role` enum('student','teacher','admin','director') NOT NULL,
  `last_no` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_counters`
--

INSERT INTO `role_counters` (`role`, `last_no`) VALUES
('student', 40),
('teacher', 5),
('admin', 2),
('director', 1);

-- --------------------------------------------------------

--
-- Table structure for table `school_settings`
--

CREATE TABLE `school_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `school_name` varchar(150) NOT NULL,
  `email` varchar(120) NOT NULL,
  `phone` varchar(40) NOT NULL,
  `address` varchar(255) NOT NULL,
  `current_academic_year` varchar(30) NOT NULL,
  `school_opening_date` date DEFAULT NULL,
  `school_closing_date` date DEFAULT NULL,
  `term1_start_date` date DEFAULT NULL,
  `term1_end_date` date DEFAULT NULL,
  `term2_start_date` date DEFAULT NULL,
  `term2_end_date` date DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(20) NOT NULL,
  `fname` varchar(60) NOT NULL,
  `mname` varchar(60) DEFAULT NULL,
  `lname` varchar(60) NOT NULL,
  `DOB` date NOT NULL,
  `age` int(11) NOT NULL,
  `sex` varchar(20) NOT NULL,
  `grade_level` varchar(30) NOT NULL,
  `stream` enum('natural','social') DEFAULT NULL,
  `address` varchar(255) NOT NULL,
  `parent_name` varchar(120) NOT NULL,
  `parent_phone` varchar(30) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `username`, `fname`, `mname`, `lname`, `DOB`, `age`, `sex`, `grade_level`, `stream`, `address`, `parent_name`, `parent_phone`, `created_at`) VALUES
(1, 'STU201', 'Student001', NULL, 'Bensa', '2010-01-02', 16, 'Male', '9', NULL, 'Bensa Town', 'Parent 001', '+251912100001', '2026-03-05 09:54:15'),
(2, 'STU202', 'Student002', NULL, 'Bensa', '2010-01-03', 16, 'Female', '9', NULL, 'Bensa Town', 'Parent 002', '+251912100002', '2026-03-05 09:54:15'),
(3, 'STU203', 'Student003', NULL, 'Bensa', '2010-01-04', 16, 'Male', '9', NULL, 'Bensa Town', 'Parent 003', '+251912100003', '2026-03-05 09:54:15'),
(4, 'STU204', 'Student004', NULL, 'Bensa', '2010-01-05', 16, 'Female', '9', NULL, 'Bensa Town', 'Parent 004', '+251912100004', '2026-03-05 09:54:15'),
(5, 'STU205', 'Student005', NULL, 'Bensa', '2010-01-06', 16, 'Male', '9', NULL, 'Bensa Town', 'Parent 005', '+251912100005', '2026-03-05 09:54:15'),
(6, 'STU211', 'Student011', NULL, 'Bensa', '2010-01-12', 15, 'Male', '10', NULL, 'Bensa Town', 'Parent 011', '+251912100011', '2026-03-05 09:54:15'),
(7, 'STU212', 'Student012', NULL, 'Bensa', '2010-01-13', 15, 'Female', '10', NULL, 'Bensa Town', 'Parent 012', '+251912100012', '2026-03-05 09:54:15'),
(8, 'STU213', 'Student013', NULL, 'Bensa', '2010-01-14', 15, 'Male', '10', NULL, 'Bensa Town', 'Parent 013', '+251912100013', '2026-03-05 09:54:15'),
(9, 'STU214', 'Student014', NULL, 'Bensa', '2010-01-15', 15, 'Female', '10', NULL, 'Bensa Town', 'Parent 014', '+251912100014', '2026-03-05 09:54:15'),
(10, 'STU215', 'Student015', NULL, 'Bensa', '2010-01-16', 15, 'Male', '10', NULL, 'Bensa Town', 'Parent 015', '+251912100015', '2026-03-05 09:54:15'),
(11, 'STU216', 'Beka', 't', 'Beka', '2026-03-11', 19, 'Male', '9', NULL, '1212', 'Beka Beka', '+251982921750', '2026-03-05 13:33:08'),
(12, 'STU217', 'Beka', 'g', 'Beka', '2026-03-11', 19, 'Male', '12', 'natural', '1212', 'Beka Beka', '+251982921750', '2026-03-05 13:34:04'),
(13, 'STU218', 'Beka', 'gfh', 'Beka', '2026-03-10', 23, 'Male', '11', 'natural', '1212', 'Beka Beka', '+251982921750', '2026-03-05 21:05:07'),
(14, 'STU219', 'Bereket', 'dfg', 'Lema', '2026-03-20', 23, 'Male', '11', 'natural', '1212', 'Beka Beka', '+251982921750', '2026-03-06 00:37:38');

-- --------------------------------------------------------

--
-- Table structure for table `student_assessment_compact_scores`
--

CREATE TABLE `student_assessment_compact_scores` (
  `id` int(10) UNSIGNED NOT NULL,
  `structure_id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `student_username` varchar(20) NOT NULL,
  `scores_json` longtext NOT NULL,
  `total_score` decimal(7,2) NOT NULL DEFAULT 0.00,
  `letter_grade` varchar(5) NOT NULL,
  `grading_scale_id` int(10) UNSIGNED DEFAULT NULL,
  `entered_by_teacher_username` varchar(20) NOT NULL,
  `entered_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_assessment_compact_scores`
--

INSERT INTO `student_assessment_compact_scores` (`id`, `structure_id`, `class_id`, `student_username`, `scores_json`, `total_score`, `letter_grade`, `grading_scale_id`, `entered_by_teacher_username`, `entered_at`, `updated_at`) VALUES
(1, 1, 1, 'STU201', '{\"17\":11,\"18\":40,\"19\":40}', 91.00, 'A+', 1, 'TCH101', '2026-03-05 09:54:15', '2026-03-05 20:22:53'),
(2, 2, 3, 'STU211', '{\"4\":5.00,\"5\":9.00,\"6\":26.00,\"7\":49.00}', 89.00, 'A', 2, 'TCH103', '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(19, 1, 1, 'STU202', '{\"17\":0,\"18\":0,\"19\":0}', 0.00, 'F', 6, 'TCH101', '2026-03-05 19:50:21', '2026-03-05 20:22:53'),
(20, 1, 1, 'STU203', '{\"17\":0,\"18\":0,\"19\":0}', 0.00, 'F', 6, 'TCH101', '2026-03-05 19:50:21', '2026-03-05 20:22:53'),
(21, 1, 1, 'STU204', '{\"17\":0,\"18\":0,\"19\":0}', 0.00, 'F', 6, 'TCH101', '2026-03-05 19:50:21', '2026-03-05 20:22:53'),
(22, 1, 1, 'STU205', '{\"17\":0,\"18\":0,\"19\":0}', 0.00, 'F', 6, 'TCH101', '2026-03-05 19:50:21', '2026-03-05 20:22:53');

-- --------------------------------------------------------

--
-- Table structure for table `student_assessment_scores`
--

CREATE TABLE `student_assessment_scores` (
  `id` int(10) UNSIGNED NOT NULL,
  `structure_item_id` int(10) UNSIGNED NOT NULL,
  `student_username` varchar(20) NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `score` decimal(7,2) NOT NULL,
  `entered_by_teacher_username` varchar(20) NOT NULL,
  `entered_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_assessment_scores`
--

INSERT INTO `student_assessment_scores` (`id`, `structure_item_id`, `student_username`, `class_id`, `score`, `entered_by_teacher_username`, `entered_at`, `updated_at`) VALUES
(4, 4, 'STU211', 3, 5.00, 'TCH103', '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(5, 5, 'STU211', 3, 9.00, 'TCH103', '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(6, 6, 'STU211', 3, 26.00, 'TCH103', '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(7, 7, 'STU211', 3, 49.00, 'TCH103', '2026-03-05 09:54:15', '2026-03-05 09:54:15');

-- --------------------------------------------------------

--
-- Table structure for table `student_resource_submissions`
--

CREATE TABLE `student_resource_submissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `resource_id` int(10) UNSIGNED NOT NULL,
  `student_username` varchar(20) NOT NULL,
  `teacher_username` varchar(20) NOT NULL,
  `class_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_mime` varchar(120) DEFAULT NULL,
  `file_size` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('submitted','seen','graded') NOT NULL DEFAULT 'submitted',
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `seen_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_resource_submissions`
--

INSERT INTO `student_resource_submissions` (`id`, `resource_id`, `student_username`, `teacher_username`, `class_id`, `notes`, `file_path`, `file_name`, `file_mime`, `file_size`, `status`, `submitted_at`, `updated_at`, `seen_at`) VALUES
(1, 1, 'STU201', 'TCH101', 1, '', 'uploads/submissions/20260306_002244_bd4a9447_Chapter_2-Lecture_3.pdf', 'Chapter 2-Lecture 3.pdf', 'application/pdf', 1898244, 'submitted', '2026-03-06 02:22:44', '2026-03-06 02:22:44', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(10) UNSIGNED NOT NULL,
  `subject_name` varchar(120) NOT NULL,
  `subject_code` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`) VALUES
(1, 'Mathematics', 'MTH101'),
(2, 'English', 'ENG101'),
(3, 'Civic', 'CVC101'),
(4, 'Physics', 'PHY101'),
(5, 'Chemistry', 'CHM101'),
(6, 'Biology', 'BIO101'),
(7, 'Geography', 'GEO101'),
(8, 'History', 'HIS101'),
(9, 'IT', 'IT101'),
(10, 'Sports', 'SPT101'),
(11, 'Introduction to AI', 'AI101'),
(12, 'Drawing', 'DRW101'),
(13, 'Introduction to Engineering', 'ENGR101'),
(14, 'Economics', 'ECO101'),
(15, 'Agriculture', 'AGR101');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(20) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `status` varchar(30) NOT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `username`, `action`, `description`, `status`, `timestamp`) VALUES
(1, 'ADM001', 'LOGIN', 'User logged in', 'success', '2026-03-05 13:31:05'),
(2, 'ADM001', 'CREATE_CERTIFICATE', 'Created Registration Slip certificate for STU211', 'success', '2026-03-05 13:35:22'),
(3, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-05 13:39:01'),
(4, 'STU201', 'LOGIN', 'User logged in', 'success', '2026-03-05 13:40:00'),
(5, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-05 13:40:54'),
(6, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-05 18:49:36'),
(7, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-05 18:51:39'),
(8, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-05 18:56:21'),
(9, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-05 18:57:43'),
(10, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-05 18:58:05'),
(11, 'ADM001', 'LOGIN', 'User logged in', 'success', '2026-03-05 19:00:21'),
(12, 'ADM001', 'ASSIGN_TEACHER', 'Assigned teacher TCH101 to class 1 for subject Biology', 'success', '2026-03-05 19:20:04'),
(13, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-05 19:20:17'),
(14, 'TCH101', 'ASSESSMENT_STRUCTURE_SAVE', 'Saved assessment structure for grade 9, subject Biology, term Term1', 'success', '2026-03-05 19:23:27'),
(15, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 19:39:21'),
(16, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 19:39:26'),
(17, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 19:39:27'),
(18, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 19:39:27'),
(19, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 19:39:28'),
(20, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 19:39:29'),
(21, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 19:39:30'),
(22, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 19:46:24'),
(23, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 19:46:30'),
(24, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 19:46:34'),
(25, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 19:46:38'),
(26, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 19:46:43'),
(27, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 19:46:48'),
(28, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 19:50:11'),
(29, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 19:50:15'),
(30, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 19:50:21'),
(31, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 19:51:11'),
(32, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-05 19:51:52'),
(33, 'TCH101', 'ASSESSMENT_STRUCTURE_SAVE', 'Saved assessment structure for grade 9, subject Biology, term Term1', 'success', '2026-03-05 19:52:54'),
(34, 'TCH101', 'ASSESSMENT_STRUCTURE_SAVE', 'Saved assessment structure for grade 9, subject Biology, term Term1', 'success', '2026-03-05 20:10:09'),
(35, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 20:15:29'),
(36, 'TCH101', 'ASSESSMENT_STRUCTURE_SAVE', 'Saved assessment structure for grade 9, subject Biology, term Term1', 'success', '2026-03-05 20:21:20'),
(37, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 20:22:14'),
(38, 'TCH101', 'ASSESSMENT_SCORES_SAVE', 'Saved assessment scores for class 1, subject Biology, term Term1', 'success', '2026-03-05 20:22:53'),
(39, 'ADM001', 'LOGIN', 'User logged in', 'success', '2026-03-05 20:23:42'),
(40, 'ADM001', 'ASSIGN_TEACHER', 'Assigned teacher TCH101 to class 3 for subject Biology', 'success', '2026-03-05 20:24:12'),
(41, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-05 20:24:28'),
(42, 'ADM001', 'LOGIN', 'User logged in', 'success', '2026-03-05 21:03:17'),
(43, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU218 to class 5', 'success', '2026-03-05 21:05:23'),
(44, 'ADM001', 'ASSIGN_TEACHER', 'Assigned teacher TCH105 to class 5 for subject Biology', 'success', '2026-03-05 21:06:01'),
(45, 'ADM001', 'REMOVE_TEACHER_ASSIGNMENT', 'Removed teacher from class 3', 'success', '2026-03-05 21:06:20'),
(46, 'TCH105', 'LOGIN', 'User logged in', 'success', '2026-03-05 21:07:08'),
(47, 'TCH105', 'ASSESSMENT_STRUCTURE_SAVE', 'Saved assessment structure for grade 11, subject Biology, term Term1', 'success', '2026-03-05 21:08:03'),
(48, 'ADM001', 'LOGIN', 'User logged in', 'success', '2026-03-05 21:13:49'),
(49, 'ADM001', 'TEACHER_STATUS_UPDATE', 'BLOCK teacher TCH105', 'success', '2026-03-05 21:16:18'),
(50, 'ADM001', 'UPDATE_TEACHER_PROFILE', 'Updated teacher profile TCH103', 'success', '2026-03-05 21:20:10'),
(51, 'ADM001', 'UPDATE_TEACHER_PROFILE', 'Updated teacher profile TCH104', 'success', '2026-03-05 21:20:16'),
(52, 'ADM001', 'LOGIN', 'User logged in', 'success', '2026-03-05 21:25:50'),
(53, 'ADM001', 'ASSIGN_TEACHER', 'Assigned teacher TCH101 to class 5 for subject Biology', 'success', '2026-03-05 21:26:48'),
(54, 'ADM001', 'TEACHER_ASSIGNMENT_BLOCK_UPDATE', 'BLOCK teacher TCH101 for class 5', 'success', '2026-03-05 21:26:58'),
(55, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-05 21:27:13'),
(56, 'ADM001', 'LOGIN', 'User logged in', 'success', '2026-03-05 21:30:36'),
(57, 'ADM001', 'TEACHER_ASSIGNMENT_BLOCK_UPDATE', 'UNBLOCK teacher TCH101 for class 5', 'success', '2026-03-05 21:30:48'),
(58, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-05 21:31:06'),
(59, 'ADM001', 'LOGIN', 'User logged in', 'success', '2026-03-05 21:34:38'),
(60, 'ADM001', 'TEACHER_ASSIGNMENT_BLOCK_UPDATE', 'BLOCK teacher TCH101 for class 5', 'success', '2026-03-05 21:34:49'),
(61, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-05 21:35:16'),
(62, 'TCH101', 'CREATE_ANNOUNCEMENT', 'Teacher created announcement #1', 'success', '2026-03-05 21:47:31'),
(63, 'TCH101', 'GENERATE_REPORT', 'Generated report #1 for class 1', 'success', '2026-03-05 21:51:17'),
(64, 'STU201', 'LOGIN', 'User logged in', 'success', '2026-03-05 21:55:09'),
(65, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-05 22:00:50'),
(66, 'TCH101', 'CREATE_ANNOUNCEMENT', 'Teacher created announcement #2', 'success', '2026-03-05 22:01:20'),
(67, 'STU201', 'LOGIN', 'User logged in', 'success', '2026-03-05 22:03:24'),
(68, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-05 22:21:48'),
(69, 'STU201', 'LOGIN', 'User logged in', 'success', '2026-03-05 22:28:30'),
(70, 'DIR001', 'LOGIN', 'User logged in', 'success', '2026-03-05 22:30:31'),
(71, 'DIR001', 'VIEW_DASHBOARD', 'Viewed dashboard overview', 'success', '2026-03-05 22:30:31'),
(72, 'DIR001', 'USER_ACTIVATE', 'Changed user status to active', 'success', '2026-03-05 22:31:17'),
(73, 'DIR001', 'VIEW_DASHBOARD', 'Viewed dashboard overview', 'success', '2026-03-05 22:31:17'),
(74, 'DIR001', 'UPDATE_SETTINGS', 'Updated school settings', 'success', '2026-03-05 22:32:06'),
(75, 'ADM001', 'LOGIN', 'User logged in', 'success', '2026-03-05 22:33:54'),
(76, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-05 22:45:27'),
(77, 'STU201', 'LOGIN', 'User logged in', 'success', '2026-03-05 22:46:04'),
(78, 'ADM001', 'LOGIN', 'User logged in', 'success', '2026-03-05 22:46:40'),
(79, 'DIR001', 'LOGIN', 'User logged in', 'success', '2026-03-05 22:47:00'),
(80, 'DIR001', 'VIEW_DASHBOARD', 'Viewed dashboard overview', 'success', '2026-03-05 22:47:00'),
(81, 'STU201', 'LOGIN', 'User logged in', 'success', '2026-03-05 23:28:07'),
(82, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-05 23:29:09'),
(83, 'ADM001', 'LOGIN', 'User logged in', 'success', '2026-03-05 23:29:54'),
(84, 'STU201', 'LOGIN', 'User logged in', 'success', '2026-03-05 23:31:26'),
(85, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-06 00:00:22'),
(86, 'ADM001', 'LOGIN', 'User logged in', 'success', '2026-03-06 00:10:06'),
(87, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-06 00:10:50'),
(88, 'ADM001', 'LOGIN', 'User logged in', 'success', '2026-03-06 00:15:08'),
(89, 'ADM001', 'LOGIN', 'User logged in', 'success', '2026-03-06 00:18:28'),
(90, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU218 to class 5', 'success', '2026-03-06 00:35:43'),
(91, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU218 to class 5', 'success', '2026-03-06 00:35:48'),
(92, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU218 to class 5', 'success', '2026-03-06 00:36:12'),
(93, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU218 to class 5', 'success', '2026-03-06 00:36:12'),
(94, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU218 to class 5', 'success', '2026-03-06 00:36:12'),
(95, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU218 to class 5', 'success', '2026-03-06 00:36:13'),
(96, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU218 to class 5', 'success', '2026-03-06 00:36:13'),
(97, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU218 to class 5', 'success', '2026-03-06 00:36:13'),
(98, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU218 to class 5', 'success', '2026-03-06 00:36:14'),
(99, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU218 to class 5', 'success', '2026-03-06 00:36:14'),
(100, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU218 to class 5', 'success', '2026-03-06 00:36:14'),
(101, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU218 to class 5', 'success', '2026-03-06 00:36:14'),
(102, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU216 to class 1', 'success', '2026-03-06 00:36:25'),
(103, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU201 to class 1', 'success', '2026-03-06 00:36:25'),
(104, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU202 to class 1', 'success', '2026-03-06 00:36:25'),
(105, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU203 to class 1', 'success', '2026-03-06 00:36:25'),
(106, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU204 to class 1', 'success', '2026-03-06 00:36:25'),
(107, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU205 to class 1', 'success', '2026-03-06 00:36:25'),
(108, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU216 to class 1', 'success', '2026-03-06 00:36:29'),
(109, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU201 to class 1', 'success', '2026-03-06 00:36:29'),
(110, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU202 to class 1', 'success', '2026-03-06 00:36:29'),
(111, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU203 to class 1', 'success', '2026-03-06 00:36:29'),
(112, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU204 to class 1', 'success', '2026-03-06 00:36:29'),
(113, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU205 to class 1', 'success', '2026-03-06 00:36:29'),
(114, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU216 to class 1', 'success', '2026-03-06 00:36:29'),
(115, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU201 to class 1', 'success', '2026-03-06 00:36:29'),
(116, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU202 to class 1', 'success', '2026-03-06 00:36:29'),
(117, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU203 to class 1', 'success', '2026-03-06 00:36:29'),
(118, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU204 to class 1', 'success', '2026-03-06 00:36:29'),
(119, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU205 to class 1', 'success', '2026-03-06 00:36:29'),
(120, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU216 to class 1', 'success', '2026-03-06 00:36:29'),
(121, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU201 to class 1', 'success', '2026-03-06 00:36:29'),
(122, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU202 to class 1', 'success', '2026-03-06 00:36:29'),
(123, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU203 to class 1', 'success', '2026-03-06 00:36:29'),
(124, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU204 to class 1', 'success', '2026-03-06 00:36:29'),
(125, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU205 to class 1', 'success', '2026-03-06 00:36:29'),
(126, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU216 to class 1', 'success', '2026-03-06 00:36:29'),
(127, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU201 to class 1', 'success', '2026-03-06 00:36:29'),
(128, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU202 to class 1', 'success', '2026-03-06 00:36:29'),
(129, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU203 to class 1', 'success', '2026-03-06 00:36:29'),
(130, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU204 to class 1', 'success', '2026-03-06 00:36:29'),
(131, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU205 to class 1', 'success', '2026-03-06 00:36:29'),
(132, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU216 to class 1', 'success', '2026-03-06 00:36:29'),
(133, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU201 to class 1', 'success', '2026-03-06 00:36:29'),
(134, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU202 to class 1', 'success', '2026-03-06 00:36:29'),
(135, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU203 to class 1', 'success', '2026-03-06 00:36:30'),
(136, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU204 to class 1', 'success', '2026-03-06 00:36:30'),
(137, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU205 to class 1', 'success', '2026-03-06 00:36:30'),
(138, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU216 to class 1', 'success', '2026-03-06 00:36:30'),
(139, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU201 to class 1', 'success', '2026-03-06 00:36:30'),
(140, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU202 to class 1', 'success', '2026-03-06 00:36:30'),
(141, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU203 to class 1', 'success', '2026-03-06 00:36:30'),
(142, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU204 to class 1', 'success', '2026-03-06 00:36:30'),
(143, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU205 to class 1', 'success', '2026-03-06 00:36:30'),
(144, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU216 to class 1', 'success', '2026-03-06 00:36:30'),
(145, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU201 to class 1', 'success', '2026-03-06 00:36:30'),
(146, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU202 to class 1', 'success', '2026-03-06 00:36:30'),
(147, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU203 to class 1', 'success', '2026-03-06 00:36:30'),
(148, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU204 to class 1', 'success', '2026-03-06 00:36:30'),
(149, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU205 to class 1', 'success', '2026-03-06 00:36:30'),
(150, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU216 to class 1', 'success', '2026-03-06 00:36:30'),
(151, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU201 to class 1', 'success', '2026-03-06 00:36:30'),
(152, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU202 to class 1', 'success', '2026-03-06 00:36:30'),
(153, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU203 to class 1', 'success', '2026-03-06 00:36:30'),
(154, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU204 to class 1', 'success', '2026-03-06 00:36:30'),
(155, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU205 to class 1', 'success', '2026-03-06 00:36:30'),
(156, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU216 to class 1', 'success', '2026-03-06 00:36:30'),
(157, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU201 to class 1', 'success', '2026-03-06 00:36:30'),
(158, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU202 to class 1', 'success', '2026-03-06 00:36:30'),
(159, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU203 to class 1', 'success', '2026-03-06 00:36:30'),
(160, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU204 to class 1', 'success', '2026-03-06 00:36:30'),
(161, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU205 to class 1', 'success', '2026-03-06 00:36:30'),
(162, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU217 to class 6', 'success', '2026-03-06 00:36:38'),
(163, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU218 to class 5', 'success', '2026-03-06 00:38:30'),
(164, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU219 to class 5', 'success', '2026-03-06 00:38:30'),
(165, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU218 to class 5', 'success', '2026-03-06 00:38:30'),
(166, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU219 to class 5', 'success', '2026-03-06 00:38:30'),
(167, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU218 to class 5', 'success', '2026-03-06 00:38:31'),
(168, 'ADM001', 'ASSIGN_STUDENT', 'Assigned student STU219 to class 5', 'success', '2026-03-06 00:38:31'),
(169, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-06 01:42:11'),
(170, 'TCH101', 'CREATE_ANNOUNCEMENT', 'Teacher created announcement #3', 'success', '2026-03-06 01:43:48'),
(171, 'STU201', 'LOGIN', 'User logged in', 'success', '2026-03-06 01:44:40'),
(172, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-06 02:18:13'),
(173, 'TCH101', 'UPLOAD_RESOURCE', 'Teacher uploaded resource #1', 'success', '2026-03-06 02:19:41'),
(174, 'TCH101', 'CREATE_ANNOUNCEMENT', 'Teacher created announcement #4', 'success', '2026-03-06 02:20:40'),
(175, 'STU201', 'LOGIN', 'User logged in', 'success', '2026-03-06 02:20:59'),
(176, 'STU201', 'SUBMIT_ASSIGNMENT', 'Student submitted file for resource #1', 'success', '2026-03-06 02:22:44'),
(177, 'TCH101', 'LOGIN', 'User logged in', 'success', '2026-03-06 02:24:15');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(20) NOT NULL,
  `fname` varchar(60) NOT NULL,
  `mname` varchar(60) DEFAULT NULL,
  `lname` varchar(60) NOT NULL,
  `DOB` date NOT NULL,
  `age` int(11) NOT NULL,
  `sex` varchar(20) NOT NULL,
  `department` varchar(100) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `office_room` varchar(50) DEFAULT NULL,
  `office_phone` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `username`, `fname`, `mname`, `lname`, `DOB`, `age`, `sex`, `department`, `subject`, `address`, `office_room`, `office_phone`, `created_at`) VALUES
(1, 'TCH101', 'Sara', NULL, 'Mekonnen', '1991-04-10', 34, 'Female', 'Science', 'Biology', 'Bensa Town', 'B-12', '+251911000101', '2026-03-05 09:54:15'),
(2, 'TCH102', 'John', NULL, 'Tesfaye', '1989-08-22', 36, 'Male', 'Mathematics', 'Mathematics', 'Bensa Town', 'M-08', '+251911000102', '2026-03-05 09:54:15'),
(3, 'TCH103', 'Helen', '', 'Bekele', '1993-01-14', 33, 'Female', 'Language', 'English', 'Bensa Town', 'L-04', '+251911000103', '2026-03-05 09:54:15'),
(4, 'TCH104', 'Dawit', '', 'Abebe', '1990-06-03', 35, 'Male', 'Social', 'History', 'Bensa Town', 'S-02', '+251911000104', '2026-03-05 09:54:15'),
(5, 'TCH105', 'Martha', NULL, 'Kassa', '1992-11-19', 33, 'Female', 'ICT', 'IT', 'Bensa Town', 'I-01', '+251911000105', '2026-03-05 09:54:15');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_class_subjects`
--

CREATE TABLE `teacher_class_subjects` (
  `id` int(10) UNSIGNED NOT NULL,
  `teacher_username` varchar(20) NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `term` varchar(20) NOT NULL,
  `academic_year_id` int(10) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_reports`
--

CREATE TABLE `teacher_reports` (
  `id` int(10) UNSIGNED NOT NULL,
  `teacher_username` varchar(20) NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `section` varchar(30) DEFAULT NULL,
  `report_type` varchar(30) NOT NULL DEFAULT 'grade_summary',
  `status` varchar(20) NOT NULL DEFAULT 'submitted',
  `payload_json` longtext NOT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_reports`
--

INSERT INTO `teacher_reports` (`id`, `teacher_username`, `class_id`, `section`, `report_type`, `status`, `payload_json`, `generated_at`) VALUES
(1, 'TCH101', 1, 'A', 'grade_summary', 'submitted', '{\"teacher_username\":\"TCH101\",\"class_id\":1,\"grade_level\":\"9\",\"section\":\"A\",\"total_students\":5,\"graded_students\":5,\"pending_students\":0,\"class_average\":18.2,\"top_students\":[{\"student_username\":\"STU201\",\"full_name\":\"Student001 Bensa\",\"avg_score\":91},{\"student_username\":\"STU205\",\"full_name\":\"Student005 Bensa\",\"avg_score\":0},{\"student_username\":\"STU202\",\"full_name\":\"Student002 Bensa\",\"avg_score\":0},{\"student_username\":\"STU203\",\"full_name\":\"Student003 Bensa\",\"avg_score\":0},{\"student_username\":\"STU204\",\"full_name\":\"Student004 Bensa\",\"avg_score\":0}],\"generated_at\":\"2026-03-05 19:51:17\"}', '2026-03-05 21:51:17');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(20) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','teacher','admin','director') NOT NULL,
  `status` enum('pending','active','inactive','rejected') NOT NULL DEFAULT 'pending',
  `approved_at` datetime DEFAULT NULL,
  `approved_by_username` varchar(20) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `status`, `approved_at`, `approved_by_username`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'DIR001', 'director@bensa.local', '12345', 'director', 'active', '2026-03-05 09:54:15', NULL, '2026-03-05 22:47:00', '2026-03-05 09:54:15', '2026-03-05 22:47:00'),
(2, 'ADM001', 'admin1@bensa.local', '12345', 'admin', 'active', '2026-03-05 09:54:15', 'DIR001', '2026-03-06 00:18:28', '2026-03-05 09:54:15', '2026-03-06 00:18:28'),
(3, 'ADM002', 'admin2@bensa.local', '12345', 'admin', 'active', '2026-03-05 09:54:15', 'DIR001', NULL, '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(4, 'TCH101', 'tch101@bensa.local', '12345', 'teacher', 'active', '2026-03-05 09:54:15', 'DIR001', '2026-03-06 02:24:15', '2026-03-05 09:54:15', '2026-03-06 02:24:15'),
(5, 'TCH102', 'tch102@bensa.local', 'pass123', 'teacher', 'active', '2026-03-05 09:54:15', 'DIR001', NULL, '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(6, 'TCH103', 'tch103@bensa.local', 'pass123', 'teacher', 'active', '2026-03-05 09:54:15', 'DIR001', NULL, '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(7, 'TCH104', 'tch104@bensa.local', 'pass123', 'teacher', 'active', '2026-03-05 09:54:15', 'DIR001', NULL, '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(8, 'TCH105', 'tch105@bensa.local', '12345', 'teacher', 'active', '2026-03-05 09:54:15', 'DIR001', '2026-03-05 21:07:08', '2026-03-05 09:54:15', '2026-03-05 22:31:17'),
(9, 'STU201', 'stu201@bensa.local', '12345', 'student', 'active', '2026-03-05 09:54:15', 'ADM001', '2026-03-06 02:20:59', '2026-03-05 09:54:15', '2026-03-06 02:20:59'),
(10, 'STU202', 'stu202@bensa.local', 'pass123', 'student', 'active', '2026-03-05 09:54:15', 'ADM001', NULL, '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(11, 'STU203', 'stu203@bensa.local', 'pass123', 'student', 'active', '2026-03-05 09:54:15', 'ADM001', NULL, '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(12, 'STU204', 'stu204@bensa.local', 'pass123', 'student', 'active', '2026-03-05 09:54:15', 'ADM001', NULL, '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(13, 'STU205', 'stu205@bensa.local', 'pass123', 'student', 'active', '2026-03-05 09:54:15', 'ADM001', NULL, '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(14, 'STU211', 'stu211@bensa.local', 'pass123', 'student', 'active', '2026-03-05 09:54:15', 'ADM001', NULL, '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(15, 'STU212', 'stu212@bensa.local', 'pass123', 'student', 'active', '2026-03-05 09:54:15', 'ADM001', NULL, '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(16, 'STU213', 'stu213@bensa.local', 'pass123', 'student', 'active', '2026-03-05 09:54:15', 'ADM001', NULL, '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(17, 'STU214', 'stu214@bensa.local', 'pass123', 'student', 'active', '2026-03-05 09:54:15', 'ADM001', NULL, '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(18, 'STU215', 'stu215@bensa.local', 'pass123', 'student', 'active', '2026-03-05 09:54:15', 'ADM001', NULL, '2026-03-05 09:54:15', '2026-03-05 09:54:15'),
(19, 'STU216', 'bereketlema0612@gmail.com', 'BAVZA', 'student', 'active', '2026-03-05 13:33:08', NULL, NULL, '2026-03-05 13:33:08', '2026-03-05 13:33:08'),
(20, 'STU217', 'bereketlem12@gmail.com', 'FQ9AE', 'student', 'active', '2026-03-05 13:34:04', NULL, NULL, '2026-03-05 13:34:04', '2026-03-05 13:34:04'),
(21, 'STU218', 'bereketlema12@gmail.com', '1DSCI', 'student', 'active', '2026-03-05 21:05:07', NULL, NULL, '2026-03-05 21:05:07', '2026-03-05 21:05:07'),
(22, 'STU219', 'berek612@gmail.com', 'HY64T', 'student', 'active', '2026-03-06 00:37:38', NULL, NULL, '2026-03-06 00:37:38', '2026-03-06 00:37:38');

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `trg_users_auto_username` BEFORE INSERT ON `users` FOR EACH ROW BEGIN
  DECLARE v_no INT;
  DECLARE v_prefix VARCHAR(3);
  IF NEW.username IS NULL OR NEW.username = '' THEN
    UPDATE `role_counters` SET last_no = last_no + 1 WHERE role = NEW.role;
    SELECT last_no INTO v_no FROM `role_counters` WHERE role = NEW.role;
    SET v_prefix = CASE NEW.role
      WHEN 'student' THEN 'STU'
      WHEN 'teacher' THEN 'TCH'
      WHEN 'admin' THEN 'ADM'
      WHEN 'director' THEN 'DIR'
      ELSE 'USR'
    END;
    SET NEW.username = CONCAT(v_prefix, LPAD(v_no,3,'0'));
  END IF;
END
$$
DELIMITER ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `academic_year` (`academic_year`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ann_created_by` (`created_by_username`);

--
-- Indexes for table `assessment_structures`
--
ALTER TABLE `assessment_structures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_structure` (`teacher_username`,`class_id`,`subject_id`,`term`,`academic_year_id`),
  ADD KEY `idx_as_subject` (`subject_id`),
  ADD KEY `idx_as_year` (`academic_year_id`),
  ADD KEY `fk_as_class` (`class_id`);

--
-- Indexes for table `assessment_structure_items`
--
ALTER TABLE `assessment_structure_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_item_name` (`structure_id`,`item_name`),
  ADD UNIQUE KEY `uk_item_order` (`structure_id`,`item_order`);

--
-- Indexes for table `assessment_structure_snapshots`
--
ALTER TABLE `assessment_structure_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ass_snap_lookup` (`teacher_username`,`class_id`,`subject_id`,`term`,`academic_year_id`),
  ADD KEY `idx_ass_snap_structure` (`structure_id`),
  ADD KEY `fk_ass_snap_class` (`class_id`),
  ADD KEY `fk_ass_snap_subject` (`subject_id`),
  ADD KEY `fk_ass_snap_year` (`academic_year_id`);

--
-- Indexes for table `assessment_structure_snapshot_items`
--
ALTER TABLE `assessment_structure_snapshot_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ass_snap_items` (`snapshot_id`);

--
-- Indexes for table `assessment_structure_snapshot_scores`
--
ALTER TABLE `assessment_structure_snapshot_scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ass_snap_score` (`snapshot_id`,`class_id`,`student_username`),
  ADD KEY `idx_ass_snap_score_student` (`student_username`),
  ADD KEY `idx_ass_snap_score_class` (`class_id`),
  ADD KEY `fk_ass_snap_score_teacher` (`entered_by_teacher_username`),
  ADD KEY `fk_ass_snap_score_scale` (`grading_scale_id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assignment_lookup` (`class_id`,`teacher_username`,`assignment_type`),
  ADD KEY `fk_assignment_teacher` (`teacher_username`),
  ADD KEY `fk_assignment_subject` (`subject_id`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `certificate_number` (`certificate_number`),
  ADD KEY `fk_cert_student` (`student_username`),
  ADD KEY `fk_cert_year` (`academic_year_id`),
  ADD KEY `fk_cert_issued_by` (`issued_by_username`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_class` (`grade_level`,`section`,`stream`,`academic_year_id`),
  ADD KEY `fk_classes_teacher` (`teacher_username`),
  ADD KEY `fk_classes_year` (`academic_year_id`);

--
-- Indexes for table `class_enrollments`
--
ALTER TABLE `class_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_student_class` (`student_username`,`class_id`),
  ADD KEY `fk_enroll_class` (`class_id`);

--
-- Indexes for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_schedule_class` (`class_id`),
  ADD KEY `fk_schedule_subject` (`subject_id`),
  ADD KEY `fk_schedule_teacher` (`teacher_username`);

--
-- Indexes for table `curriculum_subjects`
--
ALTER TABLE `curriculum_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_curriculum` (`grade_level`,`stream`,`subject_id`),
  ADD KEY `idx_curriculum_subject` (`subject_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_name` (`department_name`);

--
-- Indexes for table `directors`
--
ALTER TABLE `directors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `final_grades`
--
ALTER TABLE `final_grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_final` (`student_username`,`class_id`,`subject_id`,`term`,`academic_year_id`),
  ADD KEY `idx_fg_subject` (`subject_id`),
  ADD KEY `idx_fg_year` (`academic_year_id`),
  ADD KEY `fk_fg_class` (`class_id`),
  ADD KEY `fk_fg_teacher` (`teacher_username`),
  ADD KEY `fk_fg_structure` (`structure_id`);

--
-- Indexes for table `grading_scales`
--
ALTER TABLE `grading_scales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `grade` (`grade`);

--
-- Indexes for table `learning_resources`
--
ALTER TABLE `learning_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lr_teacher` (`teacher_username`),
  ADD KEY `idx_lr_type` (`resource_type`),
  ADD KEY `idx_lr_due` (`due_date`),
  ADD KEY `idx_lr_created` (`created_at`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_promotions_student` (`student_username`),
  ADD KEY `fk_promotions_by` (`promoted_by_username`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reg_status` (`status`),
  ADD KEY `fk_reg_user` (`username`),
  ADD KEY `fk_reg_approved_by` (`approved_by_username`),
  ADD KEY `fk_reg_rejected_by` (`rejected_by_username`);

--
-- Indexes for table `registration_admins`
--
ALTER TABLE `registration_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `fk_registration_admins_created_by` (`created_by`);

--
-- Indexes for table `role_counters`
--
ALTER TABLE `role_counters`
  ADD PRIMARY KEY (`role`);

--
-- Indexes for table `school_settings`
--
ALTER TABLE `school_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `student_assessment_compact_scores`
--
ALTER TABLE `student_assessment_compact_scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_sacs_unique` (`structure_id`,`class_id`,`student_username`),
  ADD KEY `idx_sacs_student` (`student_username`),
  ADD KEY `idx_sacs_class` (`class_id`),
  ADD KEY `idx_sacs_scale` (`grading_scale_id`),
  ADD KEY `idx_sacs_teacher` (`entered_by_teacher_username`);

--
-- Indexes for table `student_assessment_scores`
--
ALTER TABLE `student_assessment_scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_score` (`structure_item_id`,`student_username`,`class_id`),
  ADD KEY `idx_sas_student` (`student_username`),
  ADD KEY `idx_sas_class` (`class_id`),
  ADD KEY `fk_sas_teacher` (`entered_by_teacher_username`);

--
-- Indexes for table `student_resource_submissions`
--
ALTER TABLE `student_resource_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_srs_one_per_student` (`resource_id`,`student_username`),
  ADD KEY `idx_srs_teacher` (`teacher_username`),
  ADD KEY `idx_srs_class` (`class_id`),
  ADD KEY `idx_srs_status` (`status`),
  ADD KEY `idx_srs_submitted` (`submitted_at`),
  ADD KEY `fk_srs_student` (`student_username`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subject_name` (`subject_name`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_log_time` (`timestamp`),
  ADD KEY `fk_logs_user` (`username`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `teacher_class_subjects`
--
ALTER TABLE `teacher_class_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tcs` (`teacher_username`,`class_id`,`subject_id`,`term`,`academic_year_id`),
  ADD KEY `idx_tcs_class` (`class_id`),
  ADD KEY `idx_tcs_subject` (`subject_id`),
  ADD KEY `idx_tcs_year` (`academic_year_id`);

--
-- Indexes for table `teacher_reports`
--
ALTER TABLE `teacher_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_teacher_reports_teacher` (`teacher_username`),
  ADD KEY `idx_teacher_reports_class` (`class_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role_status` (`role`,`status`),
  ADD KEY `fk_users_approved_by` (`approved_by_username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `assessment_structures`
--
ALTER TABLE `assessment_structures`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `assessment_structure_items`
--
ALTER TABLE `assessment_structure_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `assessment_structure_snapshots`
--
ALTER TABLE `assessment_structure_snapshots`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `assessment_structure_snapshot_items`
--
ALTER TABLE `assessment_structure_snapshot_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `assessment_structure_snapshot_scores`
--
ALTER TABLE `assessment_structure_snapshot_scores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `class_enrollments`
--
ALTER TABLE `class_enrollments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `class_schedules`
--
ALTER TABLE `class_schedules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `curriculum_subjects`
--
ALTER TABLE `curriculum_subjects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `directors`
--
ALTER TABLE `directors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `final_grades`
--
ALTER TABLE `final_grades`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `grading_scales`
--
ALTER TABLE `grading_scales`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `learning_resources`
--
ALTER TABLE `learning_resources`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `registration_admins`
--
ALTER TABLE `registration_admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_settings`
--
ALTER TABLE `school_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `student_assessment_compact_scores`
--
ALTER TABLE `student_assessment_compact_scores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `student_assessment_scores`
--
ALTER TABLE `student_assessment_scores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `student_resource_submissions`
--
ALTER TABLE `student_resource_submissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `teacher_class_subjects`
--
ALTER TABLE `teacher_class_subjects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teacher_reports`
--
ALTER TABLE `teacher_reports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `fk_admins_username` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_ann_created_by` FOREIGN KEY (`created_by_username`) REFERENCES `users` (`username`) ON DELETE SET NULL;

--
-- Constraints for table `assessment_structures`
--
ALTER TABLE `assessment_structures`
  ADD CONSTRAINT `fk_as_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_as_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_as_teacher` FOREIGN KEY (`teacher_username`) REFERENCES `teachers` (`username`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_as_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_structure_items`
--
ALTER TABLE `assessment_structure_items`
  ADD CONSTRAINT `fk_asi_structure` FOREIGN KEY (`structure_id`) REFERENCES `assessment_structures` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_structure_snapshots`
--
ALTER TABLE `assessment_structure_snapshots`
  ADD CONSTRAINT `fk_ass_snap_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ass_snap_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `fk_ass_snap_teacher` FOREIGN KEY (`teacher_username`) REFERENCES `teachers` (`username`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ass_snap_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`);

--
-- Constraints for table `assessment_structure_snapshot_items`
--
ALTER TABLE `assessment_structure_snapshot_items`
  ADD CONSTRAINT `fk_ass_snap_items_snapshot` FOREIGN KEY (`snapshot_id`) REFERENCES `assessment_structure_snapshots` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_structure_snapshot_scores`
--
ALTER TABLE `assessment_structure_snapshot_scores`
  ADD CONSTRAINT `fk_ass_snap_score_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ass_snap_score_scale` FOREIGN KEY (`grading_scale_id`) REFERENCES `grading_scales` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ass_snap_score_snapshot` FOREIGN KEY (`snapshot_id`) REFERENCES `assessment_structure_snapshots` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ass_snap_score_student` FOREIGN KEY (`student_username`) REFERENCES `students` (`username`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ass_snap_score_teacher` FOREIGN KEY (`entered_by_teacher_username`) REFERENCES `teachers` (`username`);

--
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `fk_assignment_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assignment_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_assignment_teacher` FOREIGN KEY (`teacher_username`) REFERENCES `teachers` (`username`) ON DELETE SET NULL;

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `fk_cert_issued_by` FOREIGN KEY (`issued_by_username`) REFERENCES `users` (`username`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cert_student` FOREIGN KEY (`student_username`) REFERENCES `students` (`username`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cert_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `fk_classes_teacher` FOREIGN KEY (`teacher_username`) REFERENCES `teachers` (`username`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_classes_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `class_enrollments`
--
ALTER TABLE `class_enrollments`
  ADD CONSTRAINT `fk_enroll_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_enroll_student` FOREIGN KEY (`student_username`) REFERENCES `students` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD CONSTRAINT `fk_schedule_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_schedule_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_schedule_teacher` FOREIGN KEY (`teacher_username`) REFERENCES `teachers` (`username`) ON DELETE SET NULL;

--
-- Constraints for table `curriculum_subjects`
--
ALTER TABLE `curriculum_subjects`
  ADD CONSTRAINT `fk_curriculum_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `directors`
--
ALTER TABLE `directors`
  ADD CONSTRAINT `fk_directors_username` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `final_grades`
--
ALTER TABLE `final_grades`
  ADD CONSTRAINT `fk_fg_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fg_structure` FOREIGN KEY (`structure_id`) REFERENCES `assessment_structures` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_fg_student` FOREIGN KEY (`student_username`) REFERENCES `students` (`username`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fg_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fg_teacher` FOREIGN KEY (`teacher_username`) REFERENCES `teachers` (`username`),
  ADD CONSTRAINT `fk_fg_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `learning_resources`
--
ALTER TABLE `learning_resources`
  ADD CONSTRAINT `fk_lr_teacher` FOREIGN KEY (`teacher_username`) REFERENCES `teachers` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `promotions`
--
ALTER TABLE `promotions`
  ADD CONSTRAINT `fk_promotions_by` FOREIGN KEY (`promoted_by_username`) REFERENCES `users` (`username`),
  ADD CONSTRAINT `fk_promotions_student` FOREIGN KEY (`student_username`) REFERENCES `students` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `fk_reg_approved_by` FOREIGN KEY (`approved_by_username`) REFERENCES `users` (`username`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reg_rejected_by` FOREIGN KEY (`rejected_by_username`) REFERENCES `users` (`username`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reg_user` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `registration_admins`
--
ALTER TABLE `registration_admins`
  ADD CONSTRAINT `fk_registration_admins_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_registration_admins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_username` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `student_assessment_compact_scores`
--
ALTER TABLE `student_assessment_compact_scores`
  ADD CONSTRAINT `fk_sacs_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sacs_scale` FOREIGN KEY (`grading_scale_id`) REFERENCES `grading_scales` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sacs_structure` FOREIGN KEY (`structure_id`) REFERENCES `assessment_structures` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sacs_student` FOREIGN KEY (`student_username`) REFERENCES `students` (`username`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sacs_teacher` FOREIGN KEY (`entered_by_teacher_username`) REFERENCES `teachers` (`username`);

--
-- Constraints for table `student_assessment_scores`
--
ALTER TABLE `student_assessment_scores`
  ADD CONSTRAINT `fk_sas_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sas_item` FOREIGN KEY (`structure_item_id`) REFERENCES `assessment_structure_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sas_student` FOREIGN KEY (`student_username`) REFERENCES `students` (`username`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sas_teacher` FOREIGN KEY (`entered_by_teacher_username`) REFERENCES `teachers` (`username`);

--
-- Constraints for table `student_resource_submissions`
--
ALTER TABLE `student_resource_submissions`
  ADD CONSTRAINT `fk_srs_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_srs_resource` FOREIGN KEY (`resource_id`) REFERENCES `learning_resources` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_srs_student` FOREIGN KEY (`student_username`) REFERENCES `students` (`username`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_srs_teacher` FOREIGN KEY (`teacher_username`) REFERENCES `teachers` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `fk_logs_user` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON DELETE SET NULL;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `fk_teachers_username` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_class_subjects`
--
ALTER TABLE `teacher_class_subjects`
  ADD CONSTRAINT `fk_tcs_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tcs_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tcs_teacher` FOREIGN KEY (`teacher_username`) REFERENCES `teachers` (`username`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tcs_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_reports`
--
ALTER TABLE `teacher_reports`
  ADD CONSTRAINT `fk_teacher_reports_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_teacher_reports_teacher` FOREIGN KEY (`teacher_username`) REFERENCES `teachers` (`username`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
