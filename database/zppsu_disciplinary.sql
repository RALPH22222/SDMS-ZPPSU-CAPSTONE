-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 03, 2025 at 09:07 PM
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
-- Database: `zppsu_disciplinary`
--

-- --------------------------------------------------------

--
-- Table structure for table `appeals`
--

CREATE TABLE `appeals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `case_id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `appeal_text` text DEFAULT NULL,
  `attachment_evidence_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status_id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `decision_text` text DEFAULT NULL,
  `decision_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appeals`
--

INSERT INTO `appeals` (`id`, `case_id`, `student_id`, `appeal_text`, `attachment_evidence_id`, `status_id`, `submitted_at`, `reviewed_by_user_id`, `decision_text`, `decision_at`) VALUES
(1, 7, 10, 'ters', NULL, 1, '2025-10-17 23:12:26', NULL, NULL, NULL),
(2, 8, 10, 'twest', NULL, 1, '2025-10-18 01:17:52', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` varchar(100) NOT NULL,
  `action` varchar(30) NOT NULL,
  `performed_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_trail`
--

INSERT INTO `audit_trail` (`id`, `table_name`, `record_id`, `action`, `performed_by_user_id`, `old_values`, `new_values`, `created_at`) VALUES
(1, 'auth', '1', 'LOGOUT', 1, NULL, NULL, '2025-10-10 20:13:33'),
(2, 'auth', '17', 'LOGOUT', NULL, NULL, NULL, '2025-10-10 20:14:07'),
(3, 'auth', '1', 'LOGOUT', 1, NULL, NULL, '2025-10-17 21:22:18'),
(4, 'auth', '2', 'LOGOUT', 2, NULL, NULL, '2025-10-17 21:27:54'),
(5, 'auth', '1', 'LOGOUT', 1, NULL, NULL, '2025-10-17 23:10:39'),
(6, 'auth', '13', 'LOGOUT', 13, NULL, NULL, '2025-10-17 23:12:30'),
(7, 'auth', '1', 'LOGOUT', 1, NULL, NULL, '2025-10-17 23:26:22'),
(8, 'auth', '13', 'LOGOUT', 13, NULL, NULL, '2025-10-18 01:18:19'),
(9, 'auth', '1', 'LOGOUT', 1, NULL, NULL, '2025-10-18 01:41:27'),
(10, 'auth', '2', 'LOGOUT', 2, NULL, NULL, '2025-10-18 02:06:25'),
(11, 'auth', '1', 'LOGOUT', 1, NULL, NULL, '2025-10-18 03:59:47'),
(12, 'auth', '2', 'LOGOUT', 2, NULL, NULL, '2025-10-18 04:00:35'),
(13, 'auth', '7', 'LOGOUT', 7, NULL, NULL, '2025-10-18 04:01:17'),
(14, 'auth', '17', 'LOGOUT', NULL, NULL, NULL, '2025-10-18 04:02:04'),
(15, 'auth', '2', 'LOGOUT', 2, NULL, NULL, '2025-10-18 04:02:46'),
(16, 'auth', '2', 'LOGOUT', 2, NULL, NULL, '2025-10-18 04:09:17'),
(17, 'auth', '2', 'LOGOUT', 2, NULL, NULL, '2025-10-18 04:44:09'),
(18, 'auth', '2', 'LOGOUT', 2, NULL, NULL, '2025-10-18 05:03:19'),
(19, 'auth', '1', 'LOGOUT', 1, NULL, NULL, '2025-10-21 14:05:11'),
(20, 'auth', '9', 'LOGOUT', 9, NULL, NULL, '2025-10-21 14:06:34'),
(21, 'auth', '1', 'LOGOUT', 1, NULL, NULL, '2025-10-21 14:07:16'),
(22, 'auth', '19', 'LOGOUT', NULL, NULL, NULL, '2025-10-21 14:10:22'),
(23, 'auth', '9', 'LOGOUT', 9, NULL, NULL, '2025-10-21 14:20:05'),
(24, 'auth', '1', 'LOGOUT', 1, NULL, NULL, '2025-10-21 14:24:49'),
(25, 'users', '26', 'CREATE', NULL, NULL, '{\"username\":\"parent@gmail.com\",\"email\":\"parent@gmail.com\",\"role_id\":4}', '2025-10-21 14:29:53'),
(26, 'parent_student', '26-1', 'LINK', NULL, NULL, '{\"parent_user_id\":26,\"student_id\":1,\"relationship\":\"Mother\"}', '2025-10-21 14:29:53'),
(27, 'auth', '26', 'LOGOUT', NULL, NULL, NULL, '2025-10-21 14:30:02'),
(28, 'auth', '1', 'LOGOUT', 1, NULL, NULL, '2025-10-21 14:30:54'),
(29, 'auth', '1', 'LOGOUT', 1, NULL, NULL, '2025-10-21 14:32:59'),
(30, 'auth', '2', 'LOGOUT', 2, NULL, NULL, '2025-10-21 14:38:13'),
(31, 'auth', '1', 'LOGOUT', 1, NULL, NULL, '2025-10-21 23:16:32'),
(32, 'auth', '2', 'LOGOUT', 2, NULL, NULL, '2025-10-21 23:18:54'),
(33, 'auth', '7', 'LOGOUT', 7, NULL, NULL, '2025-10-21 23:21:19'),
(34, 'auth', '17', 'LOGOUT', NULL, NULL, NULL, '2025-10-21 23:26:43');

-- --------------------------------------------------------

--
-- Table structure for table `cases`
--

CREATE TABLE `cases` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `case_number` varchar(50) NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `reported_by_marshal_id` bigint(20) UNSIGNED NOT NULL,
  `violation_type_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `incident_date` datetime NOT NULL,
  `status_id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `resolution` text DEFAULT NULL,
  `resolution_date` datetime DEFAULT NULL,
  `is_confidential` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cases`
--

INSERT INTO `cases` (`id`, `case_number`, `student_id`, `reported_by_marshal_id`, `violation_type_id`, `title`, `description`, `location`, `incident_date`, `status_id`, `resolution`, `resolution_date`, `is_confidential`, `created_at`, `updated_at`) VALUES
(1, 'CASE-20251017-232244-364', 10, 1, 20, 'terst', NULL, NULL, '2025-10-16 05:22:00', 1, NULL, NULL, 0, '2025-10-17 21:22:44', NULL),
(7, 'CASE-20251017-232749-507', 10, 1, 19, 'testuu', NULL, NULL, '2025-10-18 05:27:00', 5, NULL, NULL, 0, '2025-10-17 21:27:49', '2025-10-17 23:12:26'),
(8, 'CASE-20251018-012842-818', 10, 1, 19, 'test', 'test', NULL, '2025-10-18 07:28:00', 5, NULL, NULL, 0, '2025-10-17 23:28:42', '2025-10-18 01:17:52'),
(9, 'CASE-20251018-012916-122', 7, 1, 22, 'test', 'test', NULL, '2025-10-18 07:29:00', 1, NULL, NULL, 0, '2025-10-17 23:29:16', NULL),
(10, 'CASE-20251018-060028-580', 4, 1, 29, 'test', 'test', NULL, '2025-10-18 12:00:00', 1, NULL, NULL, 0, '2025-10-18 04:00:28', NULL),
(11, 'CASE-20251018-060242-107', 4, 1, 20, 'test', 'test', NULL, '2025-10-18 12:02:00', 1, NULL, NULL, 0, '2025-10-18 04:02:42', NULL),
(12, 'CASE-20251018-060914-826', 4, 1, 19, 'test', 'twst', NULL, '2025-10-18 12:09:00', 1, NULL, NULL, 0, '2025-10-18 04:09:14', NULL),
(13, 'CASE-20251018-064344-528', 4, 1, 23, 'ttest-parenmtr', NULL, NULL, '2025-10-18 12:43:00', 1, NULL, NULL, 0, '2025-10-18 04:43:44', NULL),
(14, 'CASE-20251018-064405-512', 4, 1, 21, 'testparent-s', NULL, NULL, '2025-10-18 12:44:00', 1, NULL, NULL, 0, '2025-10-18 04:44:05', NULL),
(15, 'CASE-20251018-064933-509', 4, 1, 19, 'test', NULL, NULL, '2025-10-18 12:49:00', 1, NULL, NULL, 0, '2025-10-18 04:49:33', NULL),
(16, 'CASE-20251018-070239-356', 4, 1, 21, 'test', NULL, NULL, '2025-10-18 13:02:00', 1, NULL, NULL, 0, '2025-10-18 05:02:39', NULL),
(21, 'CASE-20251018-075955-921', 4, 1, 19, 'test', NULL, NULL, '2025-10-18 13:58:00', 1, NULL, NULL, 0, '2025-10-18 05:59:55', NULL),
(22, 'CASE-20251018-080030-601', 4, 1, 18, 'llll', NULL, NULL, '2025-10-18 14:00:00', 1, NULL, NULL, 0, '2025-10-18 06:00:30', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `case_evidence`
--

CREATE TABLE `case_evidence` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `case_id` bigint(20) UNSIGNED NOT NULL,
  `uploaded_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `case_logs`
--

CREATE TABLE `case_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `case_id` bigint(20) UNSIGNED NOT NULL,
  `performed_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `from_value` varchar(255) DEFAULT NULL,
  `to_value` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `case_logs`
--

INSERT INTO `case_logs` (`id`, `case_id`, `performed_by_user_id`, `action`, `from_value`, `to_value`, `note`, `created_at`) VALUES
(1, 7, 13, 'APPEAL_SUBMITTED', '1', '5', 'Appeal ID #1', '2025-10-17 23:12:26'),
(2, 8, 13, 'APPEAL_SUBMITTED', '1', '5', 'Appeal ID #2', '2025-10-18 01:17:52');

-- --------------------------------------------------------

--
-- Table structure for table `case_status`
--

CREATE TABLE `case_status` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `case_status`
--

INSERT INTO `case_status` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Filed', 'Case initially filed', '2025-09-21 20:49:16'),
(2, 'Under Review', 'Being reviewed', '2025-09-21 20:49:16'),
(3, 'Investigation', 'Under investigation', '2025-09-21 20:49:16'),
(4, 'Resolved', 'Resolved', '2025-09-21 20:49:16'),
(5, 'Appealed', 'Under appeal', '2025-09-21 20:49:16'),
(6, 'Rejected', 'Rejected', '2025-09-21 20:49:16');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `adviser_staff_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `class_name`, `adviser_staff_id`, `created_at`) VALUES
(1, 'BSIT-4A', 1, '2025-10-10 19:58:09'),
(2, 'BSIT-4B', 2, '2025-10-10 19:58:09');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `course_name` varchar(150) NOT NULL,
  `course_code` varchar(50) DEFAULT NULL,
  `department_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_name`, `course_code`, `department_id`, `created_at`) VALUES
(1, 'Bachelor of Science in Information Technology', 'BSIT', 1, '2025-10-21 23:07:54'),
(2, 'Bachelor of Science in Business Administration', 'BSBA', 2, '2025-10-21 23:07:54'),
(3, 'Bachelor of Elementary Education', 'BEEd', 3, '2025-10-21 23:07:54'),
(4, 'Bachelor of Science in Civil Engineering', 'BSCE', 4, '2025-10-21 23:07:54'),
(6, 'Bachelor of Science in Psychology', 'BSpsy', 5, '2025-10-22 01:11:54');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `department_name` varchar(150) NOT NULL,
  `abbreviation` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_name`, `abbreviation`, `created_at`) VALUES
(1, 'College of Information Technology', 'CIT', '2025-10-21 23:07:54'),
(2, 'College of Business Administration', 'CBA', '2025-10-21 23:07:54'),
(3, 'College of Education', 'COE', '2025-10-21 23:07:54'),
(4, 'College of Engineering', 'COEng', '2025-10-21 23:07:54'),
(5, 'College of Arts and Sciences', 'CAS', '2025-10-21 23:07:54'),
(6, 'test', 'test', '2025-10-22 01:12:08');

-- --------------------------------------------------------

--
-- Table structure for table `marshal`
--

CREATE TABLE `marshal` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `staff_number` varchar(30) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `department_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `marshal`
--

INSERT INTO `marshal` (`id`, `staff_number`, `user_id`, `first_name`, `middle_name`, `last_name`, `suffix`, `position`, `created_at`, `updated_at`, `department_id`) VALUES
(1, 'ZPPSU-TCH-2024-001', 2, 'Maria', 'Reyes', 'Santos', NULL, '', '2025-10-10 19:58:09', NULL, 1),
(2, 'ZPPSU-TCH-2024-002', 3, 'Juan', 'Dela', 'Cruz', NULL, 'Faculty Adviser', '2025-10-10 19:58:09', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `case_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sender_user_id` bigint(20) UNSIGNED NOT NULL,
  `recipient_user_id` bigint(20) UNSIGNED NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `case_id` bigint(20) UNSIGNED DEFAULT NULL,
  `message` varchar(255) NOT NULL,
  `method_id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `case_id`, `message`, `method_id`, `is_read`, `created_at`) VALUES
(1, 1, 1, 'New case #CASE-20251017-232244-364 reported: terst', 1, 1, '2025-10-17 21:22:44'),
(5, 1, 7, 'New case #CASE-20251017-232749-507 filed for Daniel Flores - Obscene/Vulgar Language: testuu', 1, 1, '2025-10-17 21:27:49'),
(6, 2, 7, 'New appeal submitted for case #CASE-20251017-232749-507', 1, 0, '2025-10-17 23:12:26'),
(7, 1, 8, 'New case #CASE-20251018-012842-818 reported: test', 1, 1, '2025-10-17 23:28:42'),
(9, 2, 8, 'New appeal submitted for case #CASE-20251018-012842-818', 1, 0, '2025-10-18 01:17:52'),
(10, 7, 10, 'A new report has been filed against you: test', 1, 0, '2025-10-18 04:00:28'),
(11, 1, 10, 'New case #CASE-20251018-060028-580 reported: test', 1, 0, '2025-10-18 04:00:28'),
(12, 1, 11, 'New case #CASE-20251018-060242-107 filed for Miguel Garcia - Gambling/Betting: test', 1, 0, '2025-10-18 04:02:42'),
(13, 1, 12, 'New case #CASE-20251018-060914-826 filed for Miguel Garcia - Obscene/Vulgar Language: test', 1, 0, '2025-10-18 04:09:14'),
(14, 7, 12, 'A disciplinary report has been filed against you: test (Case #CASE-20251018-060914-826)', 1, 0, '2025-10-18 04:09:14'),
(15, 7, 13, 'A new report has been filed against you: ttest-parenmtr', 1, 0, '2025-10-18 04:43:44'),
(16, 1, 13, 'New case #CASE-20251018-064344-528 reported: ttest-parenmtr', 1, 0, '2025-10-18 04:43:44'),
(17, 1, 14, 'New case #CASE-20251018-064405-512 filed for Miguel Garcia - Scamming/Fraud/Extortion: testparent-s', 1, 0, '2025-10-18 04:44:05'),
(18, 7, 14, 'A disciplinary report has been filed against you: testparent-s (Case #CASE-20251018-064405-512)', 1, 0, '2025-10-18 04:44:05'),
(19, 7, 15, 'A new report has been filed against you: test', 1, 0, '2025-10-18 04:49:33'),
(20, 1, 15, 'New case #CASE-20251018-064933-509 reported: test', 1, 0, '2025-10-18 04:49:33'),
(21, 7, 16, 'A new report has been filed against you: test', 1, 0, '2025-10-18 05:02:39'),
(22, 1, 16, 'New case #CASE-20251018-070239-356 reported: test', 1, 0, '2025-10-18 05:02:39'),
(23, 1, 21, 'New case #CASE-20251018-075955-921 filed for Miguel Garcia - Obscene/Vulgar Language: test', 1, 0, '2025-10-18 05:59:55'),
(24, 7, 21, 'A new case has been filed against you: test', 1, 0, '2025-10-18 05:59:55'),
(26, 1, 22, 'New case #CASE-20251018-080030-601 filed for Miguel Garcia - Cheating on Exams/Quizzes: llll', 1, 0, '2025-10-18 06:00:30'),
(27, 7, 22, 'A new case has been filed against you: llll', 1, 0, '2025-10-18 06:00:30');

-- --------------------------------------------------------

--
-- Table structure for table `notification_method`
--

CREATE TABLE `notification_method` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_method`
--

INSERT INTO `notification_method` (`id`, `name`) VALUES
(1, 'System Notification');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Admin', NULL, '2025-09-23 17:16:31'),
(3, 'Student', NULL, '2025-09-23 17:17:11'),
(5, 'Marshal', 'Marshal per department', '2025-09-23 18:24:42'),
(6, 'Teacher', 'Teacher role', '2025-09-24 04:50:04');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_number` varchar(20) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `sex` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `class_id` bigint(20) UNSIGNED DEFAULT NULL,
  `course_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_number`, `user_id`, `first_name`, `middle_name`, `last_name`, `suffix`, `birthdate`, `sex`, `address`, `class_id`, `course_id`, `created_at`, `updated_at`) VALUES
(1, '25-0001', 4, 'Anna', 'M', 'Reyes', NULL, '2003-05-15', '', '123 Main Street, Zamboanga City', 1, 1, '2025-10-10 19:58:09', '2025-10-21 23:07:54'),
(2, '25-0002', 5, 'Carlos', 'J', 'Lopez', 'Jr', '2003-08-22', '', '456 Oak Avenue, Zamboanga City', 1, 1, '2025-10-10 19:58:09', '2025-10-21 23:07:54'),
(3, '25-0003', 6, 'Sophia', 'L', 'Diaz', NULL, '2003-03-10', '', '789 Pine Road, Zamboanga City', 1, 2, '2025-10-10 19:58:09', '2025-10-21 23:07:54'),
(4, '25-0004', 7, 'Miguel', 'R', 'Garcia', NULL, '2003-11-30', '', '321 Elm Street, Zamboanga City', 1, 2, '2025-10-10 19:58:09', '2025-10-21 23:07:54'),
(5, '25-0005', 8, 'Isabella', 'S', 'Martinez', NULL, '2003-07-18', '', '654 Maple Lane, Zamboanga City', 1, 3, '2025-10-10 19:58:09', '2025-10-21 23:07:54'),
(6, '25-0006', 9, 'David', 'T', 'Hernandez', NULL, '2003-02-25', '', '987 Cedar Drive, Zamboanga City', 2, 3, '2025-10-10 19:58:09', '2025-10-21 23:07:54'),
(7, '25-0007', 10, 'Emily', 'P', 'Gonzales', NULL, '2003-09-12', '', '147 Birch Road, Zamboanga City', 2, 4, '2025-10-10 19:58:09', '2025-10-21 23:07:54'),
(8, '25-0008', 11, 'James', 'K', 'Torres', 'III', '2003-04-05', '', '258 Walnut Street, Zamboanga City', 2, 4, '2025-10-10 19:58:09', '2025-10-21 23:07:54'),
(9, '25-0009', 12, 'Olivia', 'M', 'Ramirez', NULL, '2003-12-20', '', '369 Spruce Avenue, Zamboanga City', 2, NULL, '2025-10-10 19:58:09', '2025-10-22 01:01:57'),
(10, '25-0010', 13, 'Daniel', 'A', 'Flores', NULL, '2003-06-08', '', '741 Palm Boulevard, Zamboanga City', 2, NULL, '2025-10-10 19:58:09', '2025-10-22 01:01:57');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `role_id` tinyint(3) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `email`, `contact_number`, `role_id`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$Yad4LUKLUCBaCAZhpVV41OJ/HtlUrclsq7sEhxAffWyDOlyjCF5/K', 'admin@zppsu.edu.ph', '09123456789', 1, 1, '2025-10-22 07:26:58', '2025-10-10 19:58:09', '2025-10-21 23:26:58'),
(2, 'teacher.maria', '$2y$10$Yad4LUKLUCBaCAZhpVV41OJ/HtlUrclsq7sEhxAffWyDOlyjCF5/K', 'maria.santos@zppsu.edu.ph', '09198765432', 6, 1, '2025-11-03 21:47:03', '2025-10-10 19:58:09', '2025-11-03 13:47:03'),
(3, 'teacher.juan', '$2y$10$Yad4LUKLUCBaCAZhpVV41OJ/HtlUrclsq7sEhxAffWyDOlyjCF5/K', 'juan.cruz@zppsu.edu.ph', '09234567890', 6, 1, NULL, '2025-10-10 19:58:09', '2025-10-10 20:12:59'),
(4, 'student.anna', '$2y$10$Yad4LUKLUCBaCAZhpVV41OJ/HtlUrclsq7sEhxAffWyDOlyjCF5/K', 'anna.reyes@student.zppsu.edu.ph', '09345678901', 3, 1, NULL, '2025-10-10 19:58:09', '2025-10-10 20:12:59'),
(5, 'student.carlos', '$2y$10$Yad4LUKLUCBaCAZhpVV41OJ/HtlUrclsq7sEhxAffWyDOlyjCF5/K', 'carlos.lopez@student.zppsu.edu.ph', '09456789012', 3, 1, NULL, '2025-10-10 19:58:09', '2025-10-10 20:12:59'),
(6, 'student.sophia', '$2y$10$Yad4LUKLUCBaCAZhpVV41OJ/HtlUrclsq7sEhxAffWyDOlyjCF5/K', 'sophia.diaz@student.zppsu.edu.ph', '09567890123', 3, 1, NULL, '2025-10-10 19:58:09', '2025-10-10 20:12:59'),
(7, 'student.miguel', '$2y$10$Yad4LUKLUCBaCAZhpVV41OJ/HtlUrclsq7sEhxAffWyDOlyjCF5/K', 'miguel.garcia@student.zppsu.edu.ph', '09678901234', 3, 1, '2025-10-22 07:19:11', '2025-10-10 19:58:09', '2025-10-21 23:19:11'),
(8, 'student.isabella', '$2y$10$Yad4LUKLUCBaCAZhpVV41OJ/HtlUrclsq7sEhxAffWyDOlyjCF5/K', 'isabella.martinez@student.zppsu.edu.ph', '09789012345', 3, 1, NULL, '2025-10-10 19:58:09', '2025-10-10 20:12:59'),
(9, 'student.david', '$2y$10$Yad4LUKLUCBaCAZhpVV41OJ/HtlUrclsq7sEhxAffWyDOlyjCF5/K', 'david.hernandez@student.zppsu.edu.ph', '09890123456', 3, 1, '2025-10-21 22:10:48', '2025-10-10 19:58:09', '2025-10-21 14:10:48'),
(10, 'student.emily', '$2y$10$Yad4LUKLUCBaCAZhpVV41OJ/HtlUrclsq7sEhxAffWyDOlyjCF5/K', 'emily.gonzales@student.zppsu.edu.ph', '09901234567', 3, 1, NULL, '2025-10-10 19:58:09', '2025-10-10 20:12:59'),
(11, 'student.james', '$2y$10$Yad4LUKLUCBaCAZhpVV41OJ/HtlUrclsq7sEhxAffWyDOlyjCF5/K', 'james.torres@student.zppsu.edu.ph', '09112345678', 3, 1, NULL, '2025-10-10 19:58:09', '2025-10-10 20:12:59'),
(12, 'student.olivia', '$2y$10$Yad4LUKLUCBaCAZhpVV41OJ/HtlUrclsq7sEhxAffWyDOlyjCF5/K', 'olivia.ramirez@student.zppsu.edu.ph', '09223456789', 3, 1, NULL, '2025-10-10 19:58:09', '2025-10-10 20:12:59'),
(13, 'student.daniel', '$2y$10$Yad4LUKLUCBaCAZhpVV41OJ/HtlUrclsq7sEhxAffWyDOlyjCF5/K', 'daniel.flores@student.zppsu.edu.ph', '09334567890', 3, 1, '2025-10-18 09:17:34', '2025-10-10 19:58:09', '2025-10-18 01:17:34'),
(25, 'testcandido', '$2y$10$dcMIjffN9ZfdzBr3ffAxX.ywfgVIRw.sXyAxKsMsliFKTEpZC3hoe', 'ralphmonzales665@gmail.com', '09774531011', 3, 1, NULL, '2025-10-21 14:24:46', '2025-10-21 14:28:08'),
(27, 'chexcandido', '$2y$10$cT5Q4ZGktQMOMRhlC/bmzeyALllGx2bcfY6CC8XXrZg3zS6KZvMuu', 'ralphmonzales@gmail.com', '09774531011', 3, 1, NULL, '2025-10-22 00:33:55', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `violation_categories`
--

CREATE TABLE `violation_categories` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `violation_categories`
--

INSERT INTO `violation_categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Light Offenses', 'Minor violations with progressive penalties', '2025-09-23 06:09:38'),
(2, 'Less Grave Offenses', 'Moderate violations with stricter penalties', '2025-09-23 06:09:38'),
(3, 'Grave Offenses', 'Serious violations with severe consequences', '2025-09-23 06:09:38');

-- --------------------------------------------------------

--
-- Table structure for table `violation_penalties`
--

CREATE TABLE `violation_penalties` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `violation_type_id` int(10) UNSIGNED NOT NULL,
  `offense_number` tinyint(3) UNSIGNED NOT NULL COMMENT '1=First, 2=Second, 3=Third, 4=Fourth',
  `penalty_description` text NOT NULL,
  `suspension_days` int(11) DEFAULT NULL COMMENT 'Duration in days for suspension',
  `community_service_days` int(11) DEFAULT NULL COMMENT 'Duration in days for community service',
  `is_expulsion` tinyint(1) DEFAULT 0 COMMENT '1 if penalty leads to expulsion',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `violation_penalties`
--

INSERT INTO `violation_penalties` (`id`, `violation_type_id`, `offense_number`, `penalty_description`, `suspension_days`, `community_service_days`, `is_expulsion`, `created_at`) VALUES
(1, 1, 1, 'Reprimand + 1 day community service', NULL, 1, 0, '2025-09-23 06:53:25'),
(2, 1, 2, '3 days suspension + 2 days community service', 3, 2, 0, '2025-09-23 06:53:25'),
(3, 1, 3, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(4, 1, 4, 'Suspension for 1 Semester', 180, NULL, 0, '2025-09-23 06:53:25'),
(5, 2, 1, 'Reprimand + 1 day community service', NULL, 1, 0, '2025-09-23 06:53:25'),
(6, 2, 2, '3 days suspension + 2 days community service', 3, 2, 0, '2025-09-23 06:53:25'),
(7, 2, 3, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(8, 2, 4, 'Suspension for 1 Semester', 180, NULL, 0, '2025-09-23 06:53:25'),
(9, 3, 1, 'Reprimand + 1 day community service', NULL, 1, 0, '2025-09-23 06:53:25'),
(10, 3, 2, '3 days suspension + 2 days community service', 3, 2, 0, '2025-09-23 06:53:25'),
(11, 3, 3, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(12, 3, 4, 'Suspension for 1 Semester', 180, NULL, 0, '2025-09-23 06:53:25'),
(13, 4, 1, 'Reprimand + 1 day community service', NULL, 1, 0, '2025-09-23 06:53:25'),
(14, 4, 2, '3 days suspension + 2 days community service', 3, 2, 0, '2025-09-23 06:53:25'),
(15, 4, 3, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(16, 4, 4, 'Suspension for 1 Semester', 180, NULL, 0, '2025-09-23 06:53:25'),
(17, 5, 1, 'Reprimand + 1 day community service', NULL, 1, 0, '2025-09-23 06:53:25'),
(18, 5, 2, '3 days suspension + 2 days community service', 3, 2, 0, '2025-09-23 06:53:25'),
(19, 5, 3, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(20, 5, 4, 'Suspension for 1 Semester', 180, NULL, 0, '2025-09-23 06:53:25'),
(21, 6, 1, 'Reprimand + 1 day community service', NULL, 1, 0, '2025-09-23 06:53:25'),
(22, 6, 2, '3 days suspension + 2 days community service', 3, 2, 0, '2025-09-23 06:53:25'),
(23, 6, 3, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(24, 6, 4, 'Suspension for 1 Semester', 180, NULL, 0, '2025-09-23 06:53:25'),
(25, 7, 1, 'Reprimand + 1 day community service', NULL, 1, 0, '2025-09-23 06:53:25'),
(26, 7, 2, '3 days suspension + 2 days community service', 3, 2, 0, '2025-09-23 06:53:25'),
(27, 7, 3, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(28, 7, 4, 'Suspension for 1 Semester', 180, NULL, 0, '2025-09-23 06:53:25'),
(29, 8, 1, 'Reprimand + 1 day community service', NULL, 1, 0, '2025-09-23 06:53:25'),
(30, 8, 2, '3 days suspension + 2 days community service', 3, 2, 0, '2025-09-23 06:53:25'),
(31, 8, 3, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(32, 8, 4, 'Suspension for 1 Semester', 180, NULL, 0, '2025-09-23 06:53:25'),
(33, 9, 1, 'Reprimand + 1 day community service', NULL, 1, 0, '2025-09-23 06:53:25'),
(34, 9, 2, '3 days suspension + 2 days community service', 3, 2, 0, '2025-09-23 06:53:25'),
(35, 9, 3, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(36, 9, 4, 'Suspension for 1 Semester', 180, NULL, 0, '2025-09-23 06:53:25'),
(37, 10, 1, 'Reprimand + 1 day community service', NULL, 1, 0, '2025-09-23 06:53:25'),
(38, 10, 2, '3 days suspension + 2 days community service', 3, 2, 0, '2025-09-23 06:53:25'),
(39, 10, 3, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(40, 10, 4, 'Suspension for 1 Semester', 180, NULL, 0, '2025-09-23 06:53:25'),
(41, 11, 1, 'Reprimand + 1 day community service', NULL, 1, 0, '2025-09-23 06:53:25'),
(42, 11, 2, '3 days suspension + 2 days community service', 3, 2, 0, '2025-09-23 06:53:25'),
(43, 11, 3, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(44, 11, 4, 'Suspension for 1 Semester', 180, NULL, 0, '2025-09-23 06:53:25'),
(45, 12, 1, 'Reprimand + 1 day community service', NULL, 1, 0, '2025-09-23 06:53:25'),
(46, 12, 2, '3 days suspension + 2 days community service', 3, 2, 0, '2025-09-23 06:53:25'),
(47, 12, 3, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(48, 12, 4, 'Suspension for 1 Semester', 180, NULL, 0, '2025-09-23 06:53:25'),
(49, 13, 1, 'Reprimand + 1 day community service', NULL, 1, 0, '2025-09-23 06:53:25'),
(50, 13, 2, '3 days suspension + 2 days community service', 3, 2, 0, '2025-09-23 06:53:25'),
(51, 13, 3, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(52, 13, 4, 'Suspension for 1 Semester', 180, NULL, 0, '2025-09-23 06:53:25'),
(53, 14, 1, 'Reprimand + 1 day community service', NULL, 1, 0, '2025-09-23 06:53:25'),
(54, 14, 2, '3 days suspension + 2 days community service', 3, 2, 0, '2025-09-23 06:53:25'),
(55, 14, 3, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(56, 14, 4, 'Suspension for 1 Semester', 180, NULL, 0, '2025-09-23 06:53:25'),
(57, 15, 1, 'Reprimand + 1 day community service', NULL, 1, 0, '2025-09-23 06:53:25'),
(58, 15, 2, '3 days suspension + 2 days community service', 3, 2, 0, '2025-09-23 06:53:25'),
(59, 15, 3, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(60, 15, 4, 'Suspension for 1 Semester', 180, NULL, 0, '2025-09-23 06:53:25'),
(61, 16, 1, 'Reprimand + 1 day community service', NULL, 1, 0, '2025-09-23 06:53:25'),
(62, 16, 2, '3 days suspension + 2 days community service', 3, 2, 0, '2025-09-23 06:53:25'),
(63, 16, 3, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(64, 16, 4, 'Suspension for 1 Semester', 180, NULL, 0, '2025-09-23 06:53:25'),
(65, 17, 1, 'Reprimand + 1 day community service', NULL, 1, 0, '2025-09-23 06:53:25'),
(66, 17, 2, '3 days suspension + 2 days community service', 3, 2, 0, '2025-09-23 06:53:25'),
(67, 17, 3, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(68, 17, 4, 'Suspension for 1 Semester', 180, NULL, 0, '2025-09-23 06:53:25'),
(69, 18, 1, '1 week suspension + 3 days community service', 7, 3, 0, '2025-09-23 06:53:25'),
(70, 18, 2, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(71, 18, 3, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(72, 18, 4, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(73, 19, 1, '1 week suspension + 3 days community service', 7, 3, 0, '2025-09-23 06:53:25'),
(74, 19, 2, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(75, 19, 3, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(76, 19, 4, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(77, 20, 1, '1 week suspension + 3 days community service', 7, 3, 0, '2025-09-23 06:53:25'),
(78, 20, 2, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(79, 20, 3, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(80, 20, 4, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(81, 21, 1, '1 week suspension + 3 days community service', 7, 3, 0, '2025-09-23 06:53:25'),
(82, 21, 2, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(83, 21, 3, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(84, 21, 4, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(85, 22, 1, '1 week suspension + 3 days community service', 7, 3, 0, '2025-09-23 06:53:25'),
(86, 22, 2, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(87, 22, 3, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(88, 22, 4, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(89, 23, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(90, 23, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(91, 23, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(92, 24, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(93, 24, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(94, 24, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(95, 25, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(96, 25, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(97, 25, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(98, 26, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(99, 26, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(100, 26, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(101, 27, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(102, 27, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(103, 27, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(104, 28, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(105, 28, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(106, 28, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(107, 29, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(108, 29, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(109, 29, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(110, 30, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(111, 30, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(112, 30, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(113, 31, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(114, 31, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(115, 31, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(116, 32, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(117, 32, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(118, 32, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(119, 33, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(120, 33, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(121, 33, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(122, 34, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(123, 34, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(124, 34, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(125, 35, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(126, 35, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(127, 35, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(128, 36, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(129, 36, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(130, 36, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(131, 37, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(132, 37, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(133, 37, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(134, 38, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(135, 38, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(136, 38, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(137, 39, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(138, 39, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(139, 39, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(140, 40, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(141, 40, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(142, 40, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(143, 41, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(144, 41, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(145, 41, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(146, 42, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(147, 42, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(148, 42, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(149, 43, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(150, 43, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(151, 43, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(152, 44, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(153, 44, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(154, 44, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25'),
(155, 45, 1, '1 month suspension + 1 week community service', 30, 7, 0, '2025-09-23 06:53:25'),
(156, 45, 2, '1 semester suspension', 180, NULL, 0, '2025-09-23 06:53:25'),
(157, 45, 3, 'Dismissal/Expulsion', NULL, NULL, 1, '2025-09-23 06:53:25');

-- --------------------------------------------------------

--
-- Table structure for table `violation_types`
--

CREATE TABLE `violation_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `points` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `violation_types`
--

INSERT INTO `violation_types` (`id`, `category_id`, `code`, `name`, `description`, `points`, `created_at`) VALUES
(1, 1, 'LIGHT-001', 'Borrowing/Lending of School Uniform', 'Unauthorized sharing of school uniforms', 1, '2025-09-23 06:09:38'),
(2, 1, 'LIGHT-002', 'Dress Code Violation', 'Violation of dress code on wash days/Saturdays', 1, '2025-09-23 06:09:38'),
(3, 1, 'LIGHT-003', 'Disrespect to University Officials', 'Showing disrespect to university personnel', 1, '2025-09-23 06:09:38'),
(4, 1, 'LIGHT-004', 'Excessive Vehicle Noise', 'Creating excessive noise with vehicles', 1, '2025-09-23 06:09:38'),
(5, 1, 'LIGHT-005', 'Illegal Entry to Campus', 'Unauthorized entry to campus premises', 1, '2025-09-23 06:09:38'),
(6, 1, 'LIGHT-006', 'Illegal Parking', 'Parking in unauthorized areas', 1, '2025-09-23 06:09:38'),
(7, 1, 'LIGHT-007', 'Improper Haircut/Hairstyle', 'Violation of grooming standards', 1, '2025-09-23 06:09:38'),
(8, 1, 'LIGHT-008', 'Inappropriate Attire', 'Wearing inappropriate clothing', 1, '2025-09-23 06:09:38'),
(9, 1, 'LIGHT-009', 'Unauthorized Use of Facilities', 'Using facilities without permission', 1, '2025-09-23 06:09:38'),
(10, 1, 'LIGHT-010', 'Littering/Improper Disposal', 'Improper waste disposal', 1, '2025-09-23 06:09:38'),
(11, 1, 'LIGHT-011', 'Loitering', 'Unauthorized lingering in areas', 1, '2025-09-23 06:09:38'),
(12, 1, 'LIGHT-012', 'Not Wearing Prescribed Uniform', 'Failure to wear required uniform', 1, '2025-09-23 06:09:38'),
(13, 1, 'LIGHT-013', 'Not Wearing University ID', 'Failure to display university identification', 1, '2025-09-23 06:09:38'),
(14, 1, 'LIGHT-014', 'Over Speeding', 'Exceeding speed limits on campus', 1, '2025-09-23 06:09:38'),
(15, 1, 'LIGHT-015', 'Smoking/Vaping', 'Smoking or vaping in prohibited areas', 1, '2025-09-23 06:09:38'),
(16, 1, 'LIGHT-016', 'Curfew Violation', 'Violating curfew after 10 PM', 1, '2025-09-23 06:09:38'),
(17, 1, 'LIGHT-017', 'Wearing Earrings/Piercings (Male)', 'Male students wearing prohibited jewelry', 1, '2025-09-23 06:09:38'),
(18, 2, 'GRAVE-001', 'Cheating on Exams/Quizzes', 'Academic dishonesty during assessments', 5, '2025-09-23 06:09:38'),
(19, 2, 'GRAVE-002', 'Obscene/Vulgar Language', 'Using inappropriate language', 5, '2025-09-23 06:09:38'),
(20, 2, 'GRAVE-003', 'Gambling/Betting', 'Participating in gambling activities', 5, '2025-09-23 06:09:38'),
(21, 2, 'GRAVE-004', 'Scamming/Fraud/Extortion', 'Engaging in fraudulent activities', 5, '2025-09-23 06:09:38'),
(22, 2, 'GRAVE-005', 'Theft/Robbery', 'Stealing or robbery incidents', 5, '2025-09-23 06:09:38'),
(23, 3, 'GRAVE-101', 'Bullying/Oppression/Discrimination', 'Harassment or discrimination', 10, '2025-09-23 06:09:38'),
(24, 3, 'GRAVE-102', 'Hazing', 'Participating in hazing activities', 10, '2025-09-23 06:09:38'),
(25, 3, 'GRAVE-103', 'Cyber Bullying', 'Online harassment', 10, '2025-09-23 06:09:38'),
(26, 3, 'GRAVE-104', 'Cyber Libel', 'Defamation through digital means', 10, '2025-09-23 06:09:38'),
(27, 3, 'GRAVE-105', 'Damage to School Property', 'Vandalism or property damage', 10, '2025-09-23 06:09:38'),
(28, 3, 'GRAVE-106', 'Defamation (Libel/Slander)', 'Defamatory statements', 10, '2025-09-23 06:09:38'),
(29, 3, 'GRAVE-107', 'Dishonesty to Officials', 'Lying to university authorities', 10, '2025-09-23 06:09:38'),
(30, 3, 'GRAVE-108', 'Assault on Officials/Employees', 'Physical assault on staff', 10, '2025-09-23 06:09:38'),
(31, 3, 'GRAVE-109', 'Drunkenness/Alcohol Possession', 'Alcohol-related violations', 10, '2025-09-23 06:09:38'),
(32, 3, 'GRAVE-110', 'Fighting/Physical Assault', 'Physical altercations', 10, '2025-09-23 06:09:38'),
(33, 3, 'GRAVE-111', 'Forgery/Falsification of Records', 'Document tampering', 10, '2025-09-23 06:09:38'),
(34, 3, 'GRAVE-112', 'Grave Threats/Coercion', 'Threatening behavior', 10, '2025-09-23 06:09:38'),
(35, 3, 'GRAVE-113', 'Identity Theft/Hacking', 'Unauthorized access to information', 10, '2025-09-23 06:09:38'),
(36, 3, 'GRAVE-114', 'Illegal Weapons Possession', 'Possession of weapons', 10, '2025-09-23 06:09:38'),
(37, 3, 'GRAVE-115', 'Drug Possession/Use/Selling', 'Drug-related violations', 10, '2025-09-23 06:09:38'),
(38, 3, 'GRAVE-116', 'Immorality', 'Acts against moral standards', 10, '2025-09-23 06:09:38'),
(39, 3, 'GRAVE-117', 'Malversation of Funds', 'Misappropriation of funds', 10, '2025-09-23 06:09:38'),
(40, 3, 'GRAVE-118', 'Physical Injuries/Assault', 'Causing physical harm', 10, '2025-09-23 06:09:38'),
(41, 3, 'GRAVE-119', 'Pornography/Cyberporn', 'Pornography violations', 10, '2025-09-23 06:09:38'),
(42, 3, 'GRAVE-120', 'Sexual Acts of Lasciviousness', 'Inappropriate sexual behavior', 10, '2025-09-23 06:09:38'),
(43, 3, 'GRAVE-121', 'Sexual Assault/Rape', 'Sexual violence', 10, '2025-09-23 06:09:38'),
(44, 3, 'GRAVE-122', 'Stalking', 'Unwanted pursuit or harassment', 10, '2025-09-23 06:09:38'),
(45, 3, 'GRAVE-123', 'Vandalism', 'Willful property destruction', 10, '2025-09-23 06:09:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appeals`
--
ALTER TABLE `appeals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attachment_evidence_id` (`attachment_evidence_id`),
  ADD KEY `status_id` (`status_id`),
  ADD KEY `reviewed_by_user_id` (`reviewed_by_user_id`),
  ADD KEY `case_id` (`case_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `table_name` (`table_name`),
  ADD KEY `performed_by_user_id` (`performed_by_user_id`);

--
-- Indexes for table `cases`
--
ALTER TABLE `cases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `case_number` (`case_number`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `reported_by_staff_id` (`reported_by_marshal_id`),
  ADD KEY `violation_type_id` (`violation_type_id`),
  ADD KEY `status_id` (`status_id`);

--
-- Indexes for table `case_evidence`
--
ALTER TABLE `case_evidence`
  ADD PRIMARY KEY (`id`),
  ADD KEY `case_id` (`case_id`),
  ADD KEY `uploaded_by_user_id` (`uploaded_by_user_id`);

--
-- Indexes for table `case_logs`
--
ALTER TABLE `case_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `case_id` (`case_id`),
  ADD KEY `performed_by_user_id` (`performed_by_user_id`);

--
-- Indexes for table `case_status`
--
ALTER TABLE `case_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `adviser_staff_id` (`adviser_staff_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `marshal`
--
ALTER TABLE `marshal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `staff_number` (`staff_number`),
  ADD UNIQUE KEY `department_id` (`department_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_user_id` (`sender_user_id`),
  ADD KEY `recipient_user_id` (`recipient_user_id`),
  ADD KEY `case_id` (`case_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `method_id` (`method_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `case_id` (`case_id`);

--
-- Indexes for table `notification_method`
--
ALTER TABLE `notification_method`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `violation_categories`
--
ALTER TABLE `violation_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `violation_penalties`
--
ALTER TABLE `violation_penalties`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `violation_offense_unique` (`violation_type_id`,`offense_number`);

--
-- Indexes for table `violation_types`
--
ALTER TABLE `violation_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `category_id` (`category_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appeals`
--
ALTER TABLE `appeals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `cases`
--
ALTER TABLE `cases`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `case_evidence`
--
ALTER TABLE `case_evidence`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `case_logs`
--
ALTER TABLE `case_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `case_status`
--
ALTER TABLE `case_status`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `marshal`
--
ALTER TABLE `marshal`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `notification_method`
--
ALTER TABLE `notification_method`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `violation_categories`
--
ALTER TABLE `violation_categories`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `violation_penalties`
--
ALTER TABLE `violation_penalties`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=166;

--
-- AUTO_INCREMENT for table `violation_types`
--
ALTER TABLE `violation_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appeals`
--
ALTER TABLE `appeals`
  ADD CONSTRAINT `appeals_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appeals_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `appeals_ibfk_3` FOREIGN KEY (`attachment_evidence_id`) REFERENCES `case_evidence` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appeals_ibfk_4` FOREIGN KEY (`status_id`) REFERENCES `case_status` (`id`),
  ADD CONSTRAINT `appeals_ibfk_5` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD CONSTRAINT `audit_trail_ibfk_1` FOREIGN KEY (`performed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cases`
--
ALTER TABLE `cases`
  ADD CONSTRAINT `cases_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `cases_ibfk_2` FOREIGN KEY (`reported_by_marshal_id`) REFERENCES `marshal` (`id`),
  ADD CONSTRAINT `cases_ibfk_3` FOREIGN KEY (`violation_type_id`) REFERENCES `violation_types` (`id`),
  ADD CONSTRAINT `cases_ibfk_4` FOREIGN KEY (`status_id`) REFERENCES `case_status` (`id`),
  ADD CONSTRAINT `fk_cases_marshal` FOREIGN KEY (`reported_by_marshal_id`) REFERENCES `marshal` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `case_evidence`
--
ALTER TABLE `case_evidence`
  ADD CONSTRAINT `case_evidence_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `case_evidence_ibfk_2` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `case_logs`
--
ALTER TABLE `case_logs`
  ADD CONSTRAINT `case_logs_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `case_logs_ibfk_2` FOREIGN KEY (`performed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`adviser_staff_id`) REFERENCES `marshal` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `marshal`
--
ALTER TABLE `marshal`
  ADD CONSTRAINT `fk_marshal_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `marshal_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_ibfk_3` FOREIGN KEY (`method_id`) REFERENCES `notification_method` (`id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `students_ibfk_3` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `violation_penalties`
--
ALTER TABLE `violation_penalties`
  ADD CONSTRAINT `violation_penalties_ibfk_1` FOREIGN KEY (`violation_type_id`) REFERENCES `violation_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `violation_types`
--
ALTER TABLE `violation_types`
  ADD CONSTRAINT `violation_types_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `violation_categories` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
