-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 23, 2025 at 11:05 PM
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
(1, 'auth', '3', 'LOGOUT', 3, NULL, NULL, '2025-09-23 20:17:40'),
(2, 'auth', '3', 'LOGOUT', 3, NULL, NULL, '2025-09-23 20:24:27');

-- --------------------------------------------------------

--
-- Table structure for table `cases`
--

CREATE TABLE `cases` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `case_number` varchar(50) NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `reported_by_staff_id` bigint(20) UNSIGNED NOT NULL,
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
(2, 'BSIT-4A', NULL, '2025-09-23 21:04:53');

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
(27, NULL, 3, 1, NULL, 'test edit', 0, '2025-09-23 19:53:03'),
(28, NULL, 3, 1, NULL, 'test', 0, '2025-09-23 19:53:03'),
(29, NULL, 3, 1, NULL, 'test', 0, '2025-09-23 19:53:09'),
(31, NULL, 3, 1, NULL, 'test for double message', 0, '2025-09-23 19:54:46'),
(32, NULL, 3, 1, NULL, 'test for double message', 0, '2025-09-23 19:54:46'),
(35, NULL, 3, 1, NULL, 'w', 0, '2025-09-23 19:55:25'),
(38, NULL, 3, 1, NULL, 'w', 0, '2025-09-23 19:55:25');

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
-- Table structure for table `parent_student`
--

CREATE TABLE `parent_student` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_user_id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `relationship` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(4, 'Parent', NULL, '2025-09-23 17:17:11'),
(5, 'Teacher', 'Teacher role', '2025-09-23 18:24:42');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `staff_number` varchar(30) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `address` text DEFAULT NULL,
  `class_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(1, 'Test', '$2y$10$KDeJl4LSpb6fv8LMkzZujeaVvB..OtB53kt6LLeZUXHXnyUvW8loW', 'test@test.com', '09774531022', 5, 1, NULL, '2025-09-23 17:36:58', '2025-09-23 19:20:56'),
(3, 'testadmin', '$2y$10$6uyDfH0zMSZQ8T/aZ.TnW.x7TfNXwtcUmDGeEMtY8IaEJNJXl9ac.', 'test@admin.com', NULL, 1, 1, '2025-09-24 04:23:57', '2025-09-23 19:46:07', '2025-09-23 20:23:57'),
(4, 'testparent', '$2y$19$OTMhmtTGBKai/BxftM2APO.SvDzkeg9LFbtX3DwlYn/8iwlH9aFZm', 'test@parent.com', NULL, 4, 1, '2025-09-24 04:40:39', '2025-09-23 20:38:52', '2025-09-23 20:40:39'),
(5, 'teststudent', '$2y$19$OTMhmtTGBKai/BxftM2APO.SvDzkeg9LFbtX3DwlYn/8iwlH9aFZm', 'test@student.com', NULL, 3, 1, NULL, '2025-09-23 20:38:52', NULL);

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
  ADD KEY `reported_by_staff_id` (`reported_by_staff_id`),
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
-- Indexes for table `parent_student`
--
ALTER TABLE `parent_student`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `parent_user_id` (`parent_user_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `staff_number` (`staff_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `user_id` (`user_id`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cases`
--
ALTER TABLE `cases`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `case_evidence`
--
ALTER TABLE `case_evidence`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `case_logs`
--
ALTER TABLE `case_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_method`
--
ALTER TABLE `notification_method`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `parent_student`
--
ALTER TABLE `parent_student`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `violation_categories`
--
ALTER TABLE `violation_categories`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `violation_penalties`
--
ALTER TABLE `violation_penalties`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=162;

--
-- AUTO_INCREMENT for table `violation_types`
--
ALTER TABLE `violation_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

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
  ADD CONSTRAINT `cases_ibfk_2` FOREIGN KEY (`reported_by_staff_id`) REFERENCES `staff` (`id`),
  ADD CONSTRAINT `cases_ibfk_3` FOREIGN KEY (`violation_type_id`) REFERENCES `violation_types` (`id`),
  ADD CONSTRAINT `cases_ibfk_4` FOREIGN KEY (`status_id`) REFERENCES `case_status` (`id`);

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
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`adviser_staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

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
-- Constraints for table `parent_student`
--
ALTER TABLE `parent_student`
  ADD CONSTRAINT `parent_student_ibfk_1` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `parent_student_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
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
