-- =====================================================
-- MediTrack Complete Database Schema
-- Healthcare Management System
-- =====================================================
-- Updated: April 1, 2026
-- Version: 2.0.0 (All fixes applied)
-- =====================================================

-- For cPanel: Change 'stjohnba_meditrack' to your actual database name if different
CREATE DATABASE IF NOT EXISTS `stjohnba_meditrack` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `stjohnba_meditrack`;

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. USERS TABLE - Core authentication
-- =====================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `role` enum('patient','reception','doctor','admin') NOT NULL DEFAULT 'patient',
  `status` enum('active','inactive','suspended','on_leave','archived') DEFAULT 'active',
  `phone` varchar(20) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_first_name` (`first_name`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. PATIENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_number` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `profile_image_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_full_name` (`full_name`),
  KEY `idx_contact` (`contact_number`),
  KEY `idx_region` (`region`),
  KEY `idx_city` (`city`),
  CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. DOCTORS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `doctors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `specialization` varchar(100) NOT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `experience_years` int(11) DEFAULT 0,
  `profile_image` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `status` enum('active','on_leave','inactive','archived') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `license_number` (`license_number`),
  KEY `idx_first_name` (`first_name`),
  KEY `idx_specialization` (`specialization`),
  KEY `idx_department` (`department`),
  CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. DEPARTMENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `head_doctor_id` int(11) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_head_doctor` (`head_doctor_id`),
  CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`head_doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. DOCTOR SCHEDULES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `doctor_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_duration` int(11) DEFAULT 30,
  `max_patients` int(11) DEFAULT 20,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_doctor_day` (`doctor_id`, `day_of_week`),
  CONSTRAINT `doctor_schedules_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. APPOINTMENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_number` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('pending','scheduled','confirmed','checked_in','in_progress','completed','cancelled','no_show') DEFAULT 'scheduled',
  `priority_level` enum('low','medium','high') DEFAULT 'low',
  `reason_for_visit` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `priority` enum('normal','urgent','emergency') DEFAULT 'normal',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `checked_in_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `appointment_number` (`appointment_number`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_doctor` (`doctor_id`),
  KEY `idx_date` (`appointment_date`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. VISITS TABLE - Consultation records
-- =====================================================
CREATE TABLE IF NOT EXISTS `visits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `visit_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `chief_complaint` text DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `vital_signs` longtext DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `lab_tests_ordered` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `appointment_id` (`appointment_id`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_doctor` (`doctor_id`),
  CONSTRAINT `visits_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `visits_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `visits_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. MEDICAL RECORDS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `medical_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `chief_complaint` text DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `lab_tests` text DEFAULT NULL,
  `vital_signs` longtext DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_doctor` (`doctor_id`),
  KEY `idx_appointment` (`appointment_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `medical_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_records_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_records_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. QR TOKENS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `qr_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `qr_payload` text NOT NULL,
  `signature` varchar(255) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `used_at` timestamp NULL DEFAULT NULL,
  `used_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `appointment_id` (`appointment_id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_created` (`created_at`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_used_by` (`used_by`),
  CONSTRAINT `qr_tokens_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `qr_tokens_ibfk_2` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 10. NOTIFICATIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('appointment','reminder','cancellation','update','system') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 11. ACTIVITY LOGS TABLE - Detailed audit trail
-- =====================================================
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `user_role` enum('admin','doctor','patient','staff','reception') NOT NULL,
  `action_type` enum('CREATE','READ','UPDATE','DELETE','LOGIN','LOGOUT','VIEW','EXPORT','IMPORT') NOT NULL,
  `module` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `old_data` longtext DEFAULT NULL,
  `new_data` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `status` enum('success','failed','warning') DEFAULT 'success',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action_type`),
  KEY `idx_module` (`module`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 12. AUDIT LOGS TABLE - Simple action log
-- =====================================================
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `target_table` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_table` (`target_table`),
  KEY `idx_timestamp` (`timestamp`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 13. PASSWORD RESETS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `reset_token` varchar(64) NOT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `verified_at` datetime DEFAULT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `used_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_otp` (`otp`),
  KEY `idx_token` (`reset_token`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 14. TRIAGE ASSESSMENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `triage_assessments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `chief_complaint` text NOT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `priority_level` enum('low','medium','high','normal','urgent','critical') DEFAULT 'low',
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_priority` (`priority_level`),
  KEY `idx_recorded_by` (`recorded_by`),
  KEY `idx_recorded_at` (`recorded_at`),
  CONSTRAINT `triage_assessments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `triage_assessments_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 15. SETTINGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- DEFAULT DATA
-- =====================================================

-- Default Admin User (password: admin123)
INSERT INTO `users` (`username`, `email`, `password_hash`, `first_name`, `last_name`, `role`, `status`) VALUES
('admin', 'admin@meditrack.com', '$2y$10$uOw0C0RsxoEhZEpJMrPUx.JoQVfBmmVdRqZg85MZMjHyiQ8h7R4Vq', 'Admin', 'User', 'admin', 'active')
ON DUPLICATE KEY UPDATE `username` = `username`;

-- Default Reception User (password: reception123)
INSERT INTO `users` (`username`, `email`, `password_hash`, `first_name`, `last_name`, `role`, `status`) VALUES
('reception', 'reception@meditrack.com', '$2y$10$DkHIxCMCY85nP5gSJZAT8OhQwSAHKwLoivu/i93dZ7PgwfOEmncNK', 'Reception', 'Staff', 'reception', 'active')
ON DUPLICATE KEY UPDATE `username` = `username`;

-- Default Departments
INSERT INTO `departments` (`name`, `description`) VALUES
('General Medicine', 'Primary care and internal medicine'),
('Cardiology', 'Heart and cardiovascular system'),
('Pediatrics', 'Children and infant healthcare'),
('Orthopedics', 'Bone, joint, and muscle treatment'),
('Neurology', 'Brain and nervous system'),
('Dermatology', 'Skin conditions and treatment'),
('ENT', 'Ear, Nose, and Throat'),
('Emergency', '24/7 emergency medical services'),
('Radiology', 'Medical imaging and diagnostics'),
('Obstetrics & Gynecology', 'Women health and maternity care')
ON DUPLICATE KEY UPDATE `name` = `name`;

-- Default Settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('server_ip', '', 'string', 'Local network IP for QR codes'),
('server_port', '80', 'string', 'Server port number'),
('qr_expiry_hours', '24', 'number', 'QR code validity in hours'),
('qr_size', '300', 'number', 'QR code image size in pixels')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- =====================================================
-- END OF SCHEMA
-- =====================================================
