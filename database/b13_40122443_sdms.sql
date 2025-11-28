-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql100.byethost13.com
-- Generation Time: Nov 05, 2025 at 08:13 AM
-- Server version: 10.6.22-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `b13_40122443_sdms`
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
(1, 1, 1, 'di ko po ginawa real', NULL, 1, '2025-11-05 12:49:39', NULL, NULL, NULL),
(2, 2, 1, 'di ko nga ginawa ano ba. my need pa ba patunayan???', NULL, 1, '2025-11-05 12:51:08', NULL, NULL, NULL);

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
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
) ;

--
-- Dumping data for table `audit_trail`
--

INSERT INTO `audit_trail` (`id`, `table_name`, `record_id`, `action`, `performed_by_user_id`, `old_values`, `new_values`, `created_at`) VALUES
(1, 'auth', '1', 'LOGOUT', 1, NULL, NULL, '2025-11-05 02:52:10'),
(2, 'auth', '31', 'LOGOUT', 31, NULL, NULL, '2025-11-05 02:54:56'),
(3, 'auth', '1', 'LOGOUT', 1, NULL, NULL, '2025-11-05 03:00:03'),
(4, 'auth', '45', 'LOGOUT', 45, NULL, NULL, '2025-11-05 12:46:06'),
(5, 'auth', '31', 'LOGOUT', 31, NULL, NULL, '2025-11-05 12:48:08'),
(6, 'auth', '31', 'LOGOUT', 31, NULL, NULL, '2025-11-05 12:48:26'),
(7, 'auth', '44', 'LOGOUT', 44, NULL, NULL, '2025-11-05 12:48:56'),
(8, 'auth', '50', 'LOGOUT', 50, NULL, NULL, '2025-11-05 12:50:42'),
(9, 'auth', '45', 'LOGOUT', 45, NULL, NULL, '2025-11-05 12:51:25'),
(10, 'auth', '1', 'LOGOUT', 1, NULL, NULL, '2025-11-05 12:52:29'),
(11, 'auth', '33', 'LOGOUT', 33, NULL, NULL, '2025-11-05 12:54:00'),
(12, 'auth', '1', 'LOGOUT', 1, NULL, NULL, '2025-11-05 12:56:58'),
(13, 'auth', '31', 'LOGOUT', 31, NULL, NULL, '2025-11-05 13:11:19');

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
(1, 'CASE-20251105-074103-852', 1, 1, 18, 'test', 'test', 'test', '2025-11-05 20:40:00', 5, NULL, NULL, 0, '2025-11-05 12:41:03', '2025-11-05 12:49:39'),
(2, 'CASE-20251105-074648-804', 1, 1, 24, 'test', 'test', NULL, '2025-11-05 20:46:00', 5, NULL, NULL, 0, '2025-11-05 12:46:48', '2025-11-05 12:51:08');

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
(1, 1, 45, 'APPEAL_SUBMITTED', '1', '5', 'Appeal ID #1', '2025-11-05 12:49:39'),
(2, 2, 45, 'APPEAL_SUBMITTED', '1', '5', 'Appeal ID #2', '2025-11-05 12:51:08');

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
(1, 'BSIT-4A', NULL, '2025-10-10 20:00:08'),
(2, 'BSIT-4B', NULL, '2025-10-10 20:00:08'),
(4, 'DT-IT 3A', NULL, '2025-10-16 03:38:19'),
(6, 'DT-IT 2A', NULL, '2025-10-18 02:00:05');

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_name`, `course_code`, `department_id`, `created_at`) VALUES
(1, 'Senior High School - STEM', 'SHS-STEM', 1, '2025-11-05 02:46:40'),
(2, 'Senior High School - ABM', 'SHS-ABM', 1, '2025-11-05 02:46:40'),
(3, 'Bachelor of Elementary Education', 'BEEd', 2, '2025-11-05 02:46:40'),
(4, 'Bachelor of Secondary Education', 'BSEd', 2, '2025-11-05 02:46:40'),
(5, 'Bachelor of Science in Civil Engineering', 'BSCE', 3, '2025-11-05 02:46:40'),
(6, 'Bachelor of Science in Mechanical Engineering', 'BSME', 3, '2025-11-05 02:46:40'),
(7, 'Bachelor of Arts in Communication', 'AB Comm', 4, '2025-11-05 02:46:40'),
(8, 'Bachelor of Arts in Psychology', 'AB Psych', 4, '2025-11-05 02:46:40'),
(9, 'Bachelor of Science in Marine Transportation', 'BSMT', 5, '2025-11-05 02:46:40'),
(10, 'Bachelor of Science in Marine Engineering', 'BSMarE', 5, '2025-11-05 02:46:40'),
(11, 'Bachelor of Science in Information Technology', 'BSIT', 6, '2025-11-05 02:46:40'),
(12, 'Bachelor of Science in Computer Science', 'BSCS', 6, '2025-11-05 02:46:40'),
(13, 'Bachelor of Science in Business Administration', 'BSBA', 7, '2025-11-05 02:46:40'),
(14, 'Bachelor of Science in Accountancy', 'BSA', 7, '2025-11-05 02:46:40'),
(15, 'Bachelor of Technical Teacher Education', 'BTTE', 8, '2025-11-05 02:46:40'),
(16, 'Bachelor of Physical Education', 'BPEd', 9, '2025-11-05 02:46:40');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `department_name` varchar(150) NOT NULL,
  `abbreviation` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_name`, `abbreviation`, `created_at`) VALUES
(1, 'Senior High School', 'SHS', '2025-11-05 02:46:40'),
(2, 'College of Teacher Education', 'CTE', '2025-11-05 02:46:40'),
(3, 'College of Engineering Technology', 'CET', '2025-11-05 02:46:40'),
(4, 'College of Arts, Humanities and Social Science', 'CAHSS', '2025-11-05 02:46:40'),
(5, 'College of Maritime Education', 'CME', '2025-11-05 02:46:40'),
(6, 'College of Information and Computing Science', 'CICS', '2025-11-05 02:46:40'),
(7, 'School of Business Administration', 'SBA', '2025-11-05 02:46:40'),
(8, 'Institute of Technology Education', 'ITE', '2025-11-05 02:46:40'),
(9, 'Physical Education and Sport', 'PES', '2025-11-05 02:46:40'),
(10, 'EPDU Vitali', 'EPDU-VITALI', '2025-11-05 02:46:40'),
(11, 'EPDU Kabasalan', 'EPDU-KABASALAN', '2025-11-05 02:46:40'),
(12, 'EPDU Siay', 'EPDU-SIAY', '2025-11-05 02:46:40'),
(13, 'EPDU Bayog', 'EPDU-BAYOG', '2025-11-05 02:46:40'),
(14, 'EPDU Malangas', 'EPDU-MALANGAS', '2025-11-05 02:46:40');

-- --------------------------------------------------------

--
-- Table structure for table `marshal`
--

CREATE TABLE `marshal` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `staff_number` varchar(30) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `department_id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `marshal`
--

INSERT INTO `marshal` (`id`, `staff_number`, `user_id`, `department_id`, `first_name`, `middle_name`, `last_name`, `suffix`, `department`, `position`, `created_at`, `updated_at`) VALUES
(1, 'ZPPSU-MAR-2024-001', 31, 1, 'Romenick', 'A', 'Molina', NULL, NULL, 'Marshal', '2025-11-05 02:46:40', NULL),
(2, 'ZPPSU-MAR-2024-002', 32, 2, 'Alfie', 'R', 'Alga', NULL, NULL, 'Marshal', '2025-11-05 02:46:40', NULL),
(3, 'ZPPSU-MAR-2024-003', 33, 3, 'Jayvin', 'R', 'Martinez', NULL, NULL, 'Marshal', '2025-11-05 02:46:40', NULL),
(4, 'ZPPSU-MAR-2024-004', 34, 4, 'Sherwin', 'T', 'Toring', NULL, NULL, 'Marshal', '2025-11-05 02:46:40', NULL),
(5, 'ZPPSU-MAR-2024-005', 35, 5, 'Gensan', 'E', 'Pelin', NULL, NULL, 'Marshal', '2025-11-05 02:46:40', NULL),
(6, 'ZPPSU-MAR-2024-006', 36, 6, 'Rodel', 'R', 'Marquez', NULL, NULL, 'Marshal', '2025-11-05 02:46:40', NULL),
(7, 'ZPPSU-MAR-2024-007', 37, 7, 'Jocelyn', 'T', 'Almonte', NULL, NULL, 'Marshal', '2025-11-05 02:46:40', NULL),
(8, 'ZPPSU-MAR-2024-008', 38, 8, 'Andie', 'F', 'Manso', NULL, NULL, 'Marshal', '2025-11-05 02:46:40', NULL),
(9, 'ZPPSU-MAR-2024-009', 39, 9, 'Sammy', 'C', 'Paringit', 'Jr', NULL, 'Marshal', '2025-11-05 02:46:40', NULL),
(10, 'ZPPSU-MAR-2024-010', 40, 10, 'Carl', 'O', 'Omictin', NULL, NULL, 'Marshal', '2025-11-05 02:46:40', NULL),
(11, 'ZPPSU-MAR-2024-011', 41, 11, 'Timothy', 'Tron T', 'Triveles', NULL, NULL, 'Marshal', '2025-11-05 02:46:40', NULL),
(12, 'ZPPSU-MAR-2024-012', 42, 12, 'Kimberly', 'M', 'Mandi', NULL, NULL, 'Marshal', '2025-11-05 02:46:40', NULL),
(13, 'ZPPSU-MAR-2024-013', 43, 13, 'Herlo', 'D', 'Mertor', NULL, NULL, 'Marshal', '2025-11-05 02:46:40', NULL),
(14, 'ZPPSU-MAR-2024-014', 44, 14, 'Michael Rey', 'B', 'Emoncha', NULL, NULL, 'Marshal', '2025-11-05 02:46:40', NULL);

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

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `case_id`, `sender_user_id`, `recipient_user_id`, `subject`, `body`, `is_read`, `created_at`) VALUES
(1, NULL, 45, 1, NULL, 'hi', 1, '2025-11-05 12:42:28'),
(2, NULL, 45, 32, NULL, 'marcial', 0, '2025-11-05 12:42:41'),
(3, NULL, 45, 31, NULL, 'sorry pu', 1, '2025-11-05 12:50:10'),
(4, NULL, 45, 31, NULL, 'di na mauulit', 1, '2025-11-05 12:50:23');

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
(1, 1, 1, 'New case #CASE-20251105-074103-852 filed for Juan Dela Cruz - Cheating on Exams/Quizzes: test', 1, 0, '2025-11-05 12:41:03'),
(2, 24, 1, 'New case #CASE-20251105-074103-852 filed for Juan Dela Cruz - Cheating on Exams/Quizzes: test', 1, 0, '2025-11-05 12:41:03'),
(3, 45, 1, 'A new case has been filed against you: test', 1, 0, '2025-11-05 12:41:03'),
(4, 1, 2, 'New case #CASE-20251105-074648-804 filed for Juan Dela Cruz - Hazing: test', 1, 0, '2025-11-05 12:46:48'),
(5, 24, 2, 'New case #CASE-20251105-074648-804 filed for Juan Dela Cruz - Hazing: test', 1, 0, '2025-11-05 12:46:48'),
(6, 45, 2, 'A new case has been filed against you: test', 1, 0, '2025-11-05 12:46:48');

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
(5, 'Marshal', 'Marshal per department', '2025-09-23 18:24:42');

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
  `sex` varchar(250) NOT NULL,
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
(1, '25-1001', 45, 'Juan', 'D', 'Dela Cruz', NULL, '2004-03-15', 'Male', '123 Main Street, Zamboanga City', NULL, 1, '2025-11-05 02:46:40', NULL),
(2, '25-1002', 46, 'Maria', 'L', 'Santos', NULL, '2004-07-22', 'Female', '456 Oak Avenue, Zamboanga City', NULL, 2, '2025-11-05 02:46:40', NULL),
(3, '25-1003', 47, 'Pedro', 'M', 'Gonzales', NULL, '2004-01-10', 'Male', '789 Pine Road, Zamboanga City', NULL, 3, '2025-11-05 02:46:40', NULL),
(4, '25-1004', 48, 'Ana', 'S', 'Reyes', NULL, '2004-11-30', 'Female', '321 Elm Street, Zamboanga City', NULL, 4, '2025-11-05 02:46:40', NULL),
(5, '25-1005', 49, 'Luis', 'T', 'Torres', NULL, '2004-05-18', 'Male', '654 Maple Lane, Zamboanga City', NULL, 5, '2025-11-05 02:46:40', NULL),
(6, '25-1006', 50, 'Sofia', 'P', 'Diaz', NULL, '2004-09-12', 'Female', '987 Cedar Drive, Zamboanga City', NULL, 6, '2025-11-05 02:46:40', NULL),
(7, '25-1007', 51, 'Carlos', 'R', 'Lopez', NULL, '2004-02-25', 'Male', '147 Birch Road, Zamboanga City', NULL, 7, '2025-11-05 02:46:40', NULL),
(8, '25-1008', 52, 'Isabel', 'G', 'Martinez', NULL, '2004-12-20', 'Female', '258 Walnut Street, Zamboanga City', NULL, 8, '2025-11-05 02:46:40', NULL),
(9, '25-1009', 53, 'Miguel', 'H', 'Hernandez', NULL, '2004-06-08', 'Male', '369 Spruce Avenue, Zamboanga City', NULL, 9, '2025-11-05 02:46:40', NULL),
(10, '25-1010', 54, 'Elena', 'J', 'Ramirez', NULL, '2004-04-05', 'Female', '741 Palm Boulevard, Zamboanga City', NULL, 10, '2025-11-05 02:46:40', NULL),
(11, '25-1011', 55, 'Antonio', 'K', 'Flores', NULL, '2004-08-28', 'Male', '852 Magnolia Street, Zamboanga City', NULL, 11, '2025-11-05 02:46:40', NULL),
(12, '25-1012', 56, 'Carmen', 'N', 'Villanueva', NULL, '2004-10-14', 'Female', '963 Acacia Lane, Zamboanga City', NULL, 12, '2025-11-05 02:46:40', NULL),
(13, '25-1013', 57, 'Ricardo', 'O', 'Castillo', NULL, '2004-03-03', 'Male', '159 Narra Road, Zamboanga City', NULL, 13, '2025-11-05 02:46:40', NULL),
(14, '25-1014', 58, 'Teresa', 'Q', 'Fernandez', NULL, '2004-07-07', 'Female', '753 Mahogany Drive, Zamboanga City', NULL, 14, '2025-11-05 02:46:40', NULL);

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
(1, 'admin', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'admin@zppsu.edu.ph', '09123456789', 1, 1, '2025-11-05 04:55:00', '2025-10-10 20:00:08', '2025-11-05 12:55:00'),
(24, 'Ralph', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'ralphmonzales665@gmail.com', '09774531011', 1, 1, '2025-10-28 07:10:59', '2025-10-11 05:53:39', '2025-11-05 02:51:53'),
(31, 'romenick.molina', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'romenick.molina@gmail.com', NULL, 5, 1, '2025-11-05 05:08:01', '2025-11-05 02:46:40', '2025-11-05 13:08:01'),
(32, 'alfie.alga', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'alfie.alga@gmail.com', NULL, 5, 1, NULL, '2025-11-05 02:46:40', NULL),
(33, 'jayvin.martinez', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'jayvin.martinez@gmail.com', NULL, 5, 1, '2025-11-05 04:52:57', '2025-11-05 02:46:40', '2025-11-05 12:52:57'),
(34, 'sherwin.toring', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'sherwin.toring@gmail.com', NULL, 5, 1, NULL, '2025-11-05 02:46:40', NULL),
(35, 'gensan.pelin', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'gensan.pelin@gmail.com', NULL, 5, 1, NULL, '2025-11-05 02:46:40', NULL),
(36, 'rodel.marquez', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'rodel.marquez@gmail.com', NULL, 5, 1, NULL, '2025-11-05 02:46:40', NULL),
(37, 'jocelyn.almonte', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'jocelyn.almonte@gmail.com', NULL, 5, 1, NULL, '2025-11-05 02:46:40', NULL),
(38, 'andie.manso', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'andie.manso@gmail.com', NULL, 5, 1, NULL, '2025-11-05 02:46:40', NULL),
(39, 'sammy.paringit', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'sammy.paringit@gmail.com', NULL, 5, 1, NULL, '2025-11-05 02:46:40', NULL),
(40, 'carl.omictin', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'carl.omictin@gmail.com', NULL, 5, 1, NULL, '2025-11-05 02:46:40', NULL),
(41, 'timothy.triveles', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'timothy.triveles@gmail.com', NULL, 5, 1, NULL, '2025-11-05 02:46:40', NULL),
(42, 'kimberly.mandi', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'kimberly.mandi@gmail.com', NULL, 5, 1, NULL, '2025-11-05 02:46:40', NULL),
(43, 'herlo.mertor', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'herlo.mertor@gmail.com', NULL, 5, 1, NULL, '2025-11-05 02:46:40', NULL),
(44, 'michael.emoncha', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'michael.emoncha@gmail.com', NULL, 5, 1, '2025-11-05 04:48:22', '2025-11-05 02:46:40', '2025-11-05 12:48:22'),
(45, 'student.01', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'student01@student.zppsu.edu.ph', NULL, 3, 1, '2025-11-05 04:49:05', '2025-11-05 02:46:40', '2025-11-05 12:49:05'),
(46, 'student.02', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'student02@student.zppsu.edu.ph', NULL, 3, 1, NULL, '2025-11-05 02:46:40', NULL),
(47, 'student.03', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'student03@student.zppsu.edu.ph', NULL, 3, 1, NULL, '2025-11-05 02:46:40', NULL),
(48, 'student.04', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'student04@student.zppsu.edu.ph', NULL, 3, 1, NULL, '2025-11-05 02:46:40', NULL),
(49, 'student.05', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'student05@student.zppsu.edu.ph', NULL, 3, 1, NULL, '2025-11-05 02:46:40', NULL),
(50, 'student.06', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'student06@student.zppsu.edu.ph', NULL, 3, 1, '2025-11-05 04:48:52', '2025-11-05 02:46:40', '2025-11-05 12:48:52'),
(51, 'student.07', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'student07@student.zppsu.edu.ph', NULL, 3, 1, NULL, '2025-11-05 02:46:40', NULL),
(52, 'student.08', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'student08@student.zppsu.edu.ph', NULL, 3, 1, NULL, '2025-11-05 02:46:40', NULL),
(53, 'student.09', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'student09@student.zppsu.edu.ph', NULL, 3, 1, NULL, '2025-11-05 02:46:40', NULL),
(54, 'student.10', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'student10@student.zppsu.edu.ph', NULL, 3, 1, NULL, '2025-11-05 02:46:40', NULL),
(55, 'student.11', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'student11@student.zppsu.edu.ph', NULL, 3, 1, NULL, '2025-11-05 02:46:40', NULL),
(56, 'student.12', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'student12@student.zppsu.edu.ph', NULL, 3, 1, NULL, '2025-11-05 02:46:40', NULL),
(57, 'student.13', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'student13@student.zppsu.edu.ph', NULL, 3, 1, NULL, '2025-11-05 02:46:40', NULL),
(58, 'student.14', '$2y$10$HhaX75K3XJUUtrmv61EEYu.xt9cec6tHLxq3WcRwhUHsHqoYLIrcq', 'student14@student.zppsu.edu.ph', NULL, 3, 1, NULL, '2025-11-05 02:46:40', NULL);

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
(4, 1, 4, 'Suspension for 1 semester', 180, NULL, 0, '2025-09-23 06:53:25'),
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
  ADD KEY `fk_students_course` (`course_id`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cases`
--
ALTER TABLE `cases`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `marshal`
--
ALTER TABLE `marshal`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `violation_categories`
--
ALTER TABLE `violation_categories`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `violation_penalties`
--
ALTER TABLE `violation_penalties`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=170;

--
-- AUTO_INCREMENT for table `violation_types`
--
ALTER TABLE `violation_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

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
-- Constraints for table `marshal`
--
ALTER TABLE `marshal`
  ADD CONSTRAINT `marshal_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_case` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_messages_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
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
  ADD CONSTRAINT `fk_students_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL;

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
