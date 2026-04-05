-- MediTrack Database Schema
-- Drop existing database if exists and create new one
DROP DATABASE IF EXISTS meditrack;
CREATE DATABASE meditrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE meditrack;

-- Users table (for authentication and role management)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    middle_name VARCHAR(50),
    last_name VARCHAR(50),
    role ENUM('patient', 'reception', 'doctor', 'admin') NOT NULL DEFAULT 'patient',
    status ENUM('active', 'inactive', 'suspended', 'on_leave') DEFAULT 'active',
    phone VARCHAR(20),
    profile_picture VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_name (first_name, last_name)
) ENGINE=InnoDB;

-- Patients table (detailed patient information)
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    address TEXT COMMENT 'Street address, house number',
    barangay VARCHAR(100) COMMENT 'Barangay name',
    region VARCHAR(100) COMMENT 'Philippines region (NCR, CAR, Region I, etc.)',
    province VARCHAR(100) COMMENT 'Province name',
    city VARCHAR(100) COMMENT 'City or municipality',
    zip_code VARCHAR(10),
    blood_group VARCHAR(5),
    allergies TEXT,
    medical_history TEXT,
    emergency_contact_name VARCHAR(100),
    emergency_contact_number VARCHAR(20),
    profile_image VARCHAR(255) COMMENT 'Filename of 2x2 profile picture stored in uploads/',
    profile_image_path VARCHAR(500) COMMENT 'Full path to profile image',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_full_name (full_name),
    INDEX idx_contact (contact_number),
    INDEX idx_region (region),
    INDEX idx_city (city)
) ENGINE=InnoDB;

-- Doctors table (doctor profiles and specializations)
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    first_name VARCHAR(50),
    middle_name VARCHAR(50),
    last_name VARCHAR(50),
    full_name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    qualification VARCHAR(255),
    license_number VARCHAR(50) UNIQUE,
    contact_number VARCHAR(20),
    email VARCHAR(100),
    department VARCHAR(100),
    consultation_fee DECIMAL(10, 2) DEFAULT 0.00,
    experience_years INT DEFAULT 0,
    profile_image VARCHAR(255),
    bio TEXT,
    status ENUM('active', 'on_leave', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_specialization (specialization),
    INDEX idx_department (department),
    INDEX idx_name (first_name, last_name)
) ENGINE=InnoDB;

-- Doctor schedules (availability management)
CREATE TABLE doctor_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_duration INT DEFAULT 30 COMMENT 'Duration in minutes',
    max_patients INT DEFAULT 20,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    INDEX idx_doctor_day (doctor_id, day_of_week)
) ENGINE=InnoDB;

-- Appointments table
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_number VARCHAR(20) UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('scheduled', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
    reason_for_visit TEXT,
    notes TEXT,
    priority ENUM('normal', 'urgent', 'emergency') DEFAULT 'normal',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    checked_in_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_appointment_date (appointment_date),
    INDEX idx_patient (patient_id),
    INDEX idx_doctor (doctor_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Visits table (medical records for each appointment)
CREATE TABLE visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    visit_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    chief_complaint TEXT,
    symptoms TEXT,
    vital_signs JSON COMMENT 'BP, temp, pulse, weight, height, etc.',
    diagnosis TEXT,
    prescription TEXT,
    lab_tests_ordered TEXT,
    follow_up_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    INDEX idx_patient_visit (patient_id, visit_date),
    INDEX idx_doctor_visit (doctor_id, visit_date)
) ENGINE=InnoDB;

-- QR Tokens table (for secure QR code generation and validation)
CREATE TABLE qr_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNIQUE NOT NULL,
    qr_payload TEXT NOT NULL,
    signature VARCHAR(255) NOT NULL,
    token_hash VARCHAR(64) UNIQUE NOT NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    used_at TIMESTAMP NULL,
    used_by INT NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires (expires_at),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Notifications table (for real-time notifications)
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('appointment', 'reminder', 'cancellation', 'update', 'system') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_id INT COMMENT 'Related appointment or visit ID',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Audit logs table (for tracking all system activities)
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    target_table VARCHAR(50),
    target_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_action (user_id, action),
    INDEX idx_timestamp (timestamp),
    INDEX idx_target (target_table, target_id)
) ENGINE=InnoDB;

-- Departments table
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    head_doctor_id INT,
    contact_number VARCHAR(20),
    email VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (head_doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
    INDEX idx_name (name)
) ENGINE=InnoDB;

-- Settings table (for system configuration)
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password_hash, role) VALUES
('admin', 'admin@meditrack.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('reception', 'reception@meditrack.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'reception');

-- Insert sample departments
INSERT INTO departments (name, description) VALUES
('General Medicine', 'General health checkups and common ailments'),
('Cardiology', 'Heart and cardiovascular system'),
('Orthopedics', 'Bones, joints, and muscles'),
('Pediatrics', 'Child healthcare'),
('Dermatology', 'Skin, hair, and nail conditions'),
('ENT', 'Ear, Nose, and Throat'),
('Gynecology', 'Women\'s health'),
('Neurology', 'Brain and nervous system'),
('Ophthalmology', 'Eye care'),
('Dentistry', 'Dental care');

-- Insert sample doctors
INSERT INTO users (username, email, password_hash, role) VALUES
('dr.smith', 'dr.smith@meditrack.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor'),
('dr.johnson', 'dr.johnson@meditrack.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor'),
('dr.williams', 'dr.williams@meditrack.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor');

INSERT INTO doctors (user_id, first_name, middle_name, last_name, full_name, specialization, qualification, license_number, contact_number, email, department, consultation_fee, experience_years) VALUES
(3, 'John', '', 'Smith', 'Dr. John Smith', 'Cardiologist', 'MD, DM Cardiology', 'MED123456', '+1234567890', 'dr.smith@meditrack.com', 'Cardiology', 150.00, 15),
(4, 'Sarah', '', 'Johnson', 'Dr. Sarah Johnson', 'General Physician', 'MBBS, MD', 'MED123457', '+1234567891', 'dr.johnson@meditrack.com', 'General Medicine', 100.00, 10),
(5, 'Michael', '', 'Williams', 'Dr. Michael Williams', 'Orthopedic Surgeon', 'MS Orthopedics', 'MED123458', '+1234567892', 'dr.williams@meditrack.com', 'Orthopedics', 200.00, 12);

-- Insert sample doctor schedules
INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, max_patients) VALUES
(1, 'Monday', '09:00:00', '17:00:00', 30, 16),
(1, 'Tuesday', '09:00:00', '17:00:00', 30, 16),
(1, 'Wednesday', '09:00:00', '17:00:00', 30, 16),
(1, 'Thursday', '09:00:00', '17:00:00', 30, 16),
(1, 'Friday', '09:00:00', '17:00:00', 30, 16),
(2, 'Monday', '08:00:00', '16:00:00', 20, 24),
(2, 'Tuesday', '08:00:00', '16:00:00', 20, 24),
(2, 'Wednesday', '08:00:00', '16:00:00', 20, 24),
(2, 'Thursday', '08:00:00', '16:00:00', 20, 24),
(2, 'Friday', '08:00:00', '16:00:00', 20, 24),
(3, 'Monday', '10:00:00', '18:00:00', 30, 16),
(3, 'Wednesday', '10:00:00', '18:00:00', 30, 16),
(3, 'Friday', '10:00:00', '18:00:00', 30, 16);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'MediTrack', 'string', 'Application name'),
('appointment_advance_days', '30', 'number', 'How many days in advance appointments can be booked'),
('qr_expiry_hours', '24', 'number', 'QR code expiry time in hours'),
('enable_email_notifications', 'true', 'boolean', 'Enable email notifications'),
('enable_sms_notifications', 'false', 'boolean', 'Enable SMS notifications'),
('appointment_reminder_hours', '24', 'number', 'Send reminder X hours before appointment');
