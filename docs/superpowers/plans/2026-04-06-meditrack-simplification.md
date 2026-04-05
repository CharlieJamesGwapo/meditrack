# MediTrack Simplification Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Simplify MediTrack into a focused single-doctor Internal Medicine clinic system with QR-based appointment booking and printable medical records.

**Architecture:** Replace the existing multi-department, multi-doctor system with a streamlined 3-role (patient, doctor, admin) application. Keep the existing PHP+MySQL+Tailwind stack. Rewrite all pages and APIs from scratch to eliminate dead code from removed features (departments, reception, triage, notifications, settings, email).

**Tech Stack:** PHP 8+ with PDO, MySQL (utf8mb4), Tailwind CSS (CDN), Vanilla JavaScript, SweetAlert2, Google Charts QR API, XAMPP.

---

## File Structure

```
meditrack/
├── index.html                          # Landing page (rewrite)
├── env.php                             # Environment config (keep)
├── config/
│   ├── config.php                      # App config (simplify)
│   └── database.php                    # DB connection (keep as-is)
├── database/
│   └── schema.sql                      # New simplified schema (rewrite)
├── utils/
│   └── QRCodeGenerator.php             # QR generation (keep, minor edits)
├── api/
│   ├── auth/
│   │   ├── login.php                   # Login (rewrite)
│   │   ├── register.php                # Patient registration (rewrite)
│   │   ├── logout.php                  # Logout (rewrite)
│   │   ├── request-otp.php             # Forgot password OTP (rewrite)
│   │   └── reset-password.php          # Reset password (rewrite)
│   ├── patient/
│   │   ├── get-profile.php             # Get patient profile (rewrite)
│   │   ├── update-profile.php          # Update profile (rewrite)
│   │   ├── change-password.php         # Change password (rewrite)
│   │   ├── get-appointments.php        # List appointments (rewrite)
│   │   ├── book-appointment.php        # Book appointment (rewrite)
│   │   ├── cancel-appointment.php      # Cancel appointment (rewrite)
│   │   ├── get-medical-records.php     # List medical records (rewrite)
│   │   └── get-available-slots.php     # Get time slots (rewrite)
│   ├── doctor/
│   │   ├── get-profile.php             # Doctor profile (rewrite)
│   │   ├── change-password.php         # Change password (rewrite)
│   │   ├── get-appointments.php        # List appointments (rewrite)
│   │   ├── checkin-patient.php         # QR check-in (new)
│   │   ├── save-medical-record.php     # Save record (rewrite)
│   │   └── stats.php                   # Dashboard stats (rewrite)
│   ├── admin/
│   │   ├── stats.php                   # Dashboard stats (rewrite)
│   │   ├── appointments.php            # List appointments (rewrite)
│   │   ├── cancel-appointment.php      # Cancel appointment (new)
│   │   ├── get-all-patients.php        # List patients (rewrite)
│   │   ├── update-patient-status.php   # Activate/deactivate (new)
│   │   ├── get-doctor-schedule.php     # Get schedule (new)
│   │   ├── update-doctor-schedule.php  # Update schedule (new)
│   │   ├── reports-data.php            # Reports (rewrite)
│   │   ├── activity.php                # Activity logs (rewrite)
│   │   └── get-doctor-profile.php      # Doctor info (new)
│   └── appointments/
│       ├── generate-qr.php             # Generate QR (rewrite)
│       └── checkin.php                 # Validate QR token (rewrite)
├── js/
│   ├── auth.js                         # Login/register/reset logic (new)
│   ├── patient-dashboard.js            # Patient dashboard (new)
│   ├── doctor-dashboard.js             # Doctor dashboard (new)
│   └── admin-dashboard.js              # Admin dashboard (new)
├── pages/
│   ├── login.html                      # Login page (rewrite)
│   ├── register.html                   # Register page (rewrite)
│   ├── forgot-password.html            # Forgot password (rewrite)
│   ├── reset-password.html             # Reset password (rewrite)
│   ├── patient-dashboard.html          # Patient dashboard (rewrite)
│   ├── doctor-dashboard.html           # Doctor dashboard (rewrite)
│   ├── admin-dashboard.html            # Admin dashboard (rewrite)
│   ├── qr-booking.html                 # QR entry page (new)
│   └── print-record.html              # Printable record (rewrite)
└── uploads/                            # Profile pictures (keep)
```

**Files/directories to DELETE** (removed features):
- `api/departments/` (all files)
- `api/search/` (all files)
- `api/triage/` (all files)
- `api/visits/` (all files)
- `api/notifications/` (all files)
- `api/activity/` (all files — replaced by admin/activity.php)
- `api/doctors/` (all files — no doctor CRUD)
- `api/admin/get-all-doctors.php`, `get-archived-*`, `restore-*`, `archive-*`, `users.php`, `users-simple.php`, `recent-appointments.php`, `confirm-appointment.php`, `get-settings.php`, `save-settings.php`, `detect-ip.php`, `generate-report.php`
- `config/activity-logger.php`, `config/email.php`
- `utils/EmailSender.php`, `utils/EmailService.php`
- `js/admin-dashboard-enhanced.js`, `js/auth-check.js`, `js/departments.js`, `js/doctor-dashboard-complete.js`, `js/patient-dashboard-complete.js`, `js/reception-dashboard-complete.js`, `js/reports.js`
- `pages/appointments.html`, `pages/departments.html`, `pages/doctors.html`, `pages/history-log.html`, `pages/patients.html`, `pages/qr-display.html`, `pages/qr-lookup.html`, `pages/reception-dashboard.html`, `pages/reports.html`, `pages/settings.html`, `pages/triage.html`, `pages/mobile-search.html`, `pages/booking-qr.html`, `pages/qr-checkin.html`, `pages/verify-otp.html`
- `docs/` (old docs)
- `PHPMailer/` (email library — no longer needed)

---

### Task 1: Clean Up — Delete Removed Files and Directories

**Files:**
- Delete: all files/directories listed in the "Files/directories to DELETE" section above

- [ ] **Step 1: Delete removed API directories**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/meditrack
rm -rf api/departments api/search api/triage api/visits api/notifications api/activity api/doctors
```

- [ ] **Step 2: Delete removed admin API files**

```bash
rm -f api/admin/get-all-doctors.php api/admin/get-archived-doctors.php api/admin/get-archived-patients.php
rm -f api/admin/restore-doctor.php api/admin/restore-patient.php api/admin/archive-doctor.php api/admin/archive-patient.php
rm -f api/admin/users.php api/admin/users-simple.php api/admin/recent-appointments.php api/admin/confirm-appointment.php
rm -f api/admin/get-settings.php api/admin/save-settings.php api/admin/detect-ip.php api/admin/generate-report.php
```

- [ ] **Step 3: Delete removed config, utils, and library files**

```bash
rm -f config/activity-logger.php config/email.php
rm -f utils/EmailSender.php utils/EmailService.php
rm -rf PHPMailer docs
```

- [ ] **Step 4: Delete old JS files**

```bash
rm -f js/admin-dashboard-enhanced.js js/auth-check.js js/departments.js
rm -f js/doctor-dashboard-complete.js js/patient-dashboard-complete.js
rm -f js/reception-dashboard-complete.js js/reports.js
```

- [ ] **Step 5: Delete old page files**

```bash
rm -f pages/appointments.html pages/departments.html pages/doctors.html pages/history-log.html
rm -f pages/patients.html pages/qr-display.html pages/qr-lookup.html pages/reception-dashboard.html
rm -f pages/reports.html pages/settings.html pages/triage.html pages/mobile-search.html
rm -f pages/booking-qr.html pages/qr-checkin.html pages/verify-otp.html
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "chore: remove unused files for MediTrack simplification"
```

---

### Task 2: Database Schema and Seed Data

**Files:**
- Rewrite: `database/schema.sql`

- [ ] **Step 1: Write the new schema**

Write `database/schema.sql` with the complete schema and seed data:

```sql
-- MediTrack Simplified Schema
-- Single doctor, Internal Medicine clinic

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS qr_tokens;
DROP TABLE IF EXISTS medical_records;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS doctor_schedules;
DROP TABLE IF EXISTS doctors;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- Users table (auth for all roles)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) UNIQUE NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('patient', 'doctor', 'admin') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Patients table
CREATE TABLE patients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NULL,
    gender ENUM('Male', 'Female', 'Other') NULL,
    contact_number VARCHAR(20) NULL,
    address TEXT NULL,
    region VARCHAR(100) NULL,
    city VARCHAR(100) NULL,
    barangay VARCHAR(100) NULL,
    blood_group VARCHAR(5) NULL,
    allergies TEXT NULL,
    emergency_contact_name VARCHAR(100) NULL,
    emergency_contact_number VARCHAR(20) NULL,
    profile_picture VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Doctors table (single doctor)
CREATE TABLE doctors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100) DEFAULT 'Internal Medicine',
    license_number VARCHAR(50) NULL,
    consultation_fee DECIMAL(10,2) DEFAULT 0.00,
    experience_years INT DEFAULT 0,
    bio TEXT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Doctor schedules (weekly availability)
CREATE TABLE doctor_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    doctor_id INT NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=Sunday, 6=Saturday',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_duration INT DEFAULT 30 COMMENT 'minutes',
    max_patients INT DEFAULT 20,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Appointments
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_number VARCHAR(20) UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('scheduled', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
    reason_for_visit TEXT NULL,
    checked_in_at DATETIME NULL,
    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Medical records (one per appointment)
CREATE TABLE medical_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id INT UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    chief_complaint TEXT NULL,
    symptoms TEXT NULL,
    vital_signs JSON NULL COMMENT '{"bp":"120/80","temp":"36.5","heart_rate":"72","weight":"70","height":"170"}',
    diagnosis TEXT NULL,
    prescription TEXT NULL,
    lab_tests_ordered TEXT NULL,
    notes TEXT NULL,
    follow_up_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- QR tokens (one per appointment)
CREATE TABLE qr_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id INT UNIQUE NOT NULL,
    qr_payload JSON NOT NULL,
    signature VARCHAR(255) NOT NULL,
    token_hash VARCHAR(255) UNIQUE NOT NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    used_at DATETIME NULL,
    used_by INT NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password resets
CREATE TABLE password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    reset_token VARCHAR(255) NULL,
    verified TINYINT(1) DEFAULT 0,
    used TINYINT(1) DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity logs (audit trail)
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    username VARCHAR(50) NULL,
    user_role VARCHAR(20) NULL,
    action_type ENUM('CREATE', 'READ', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'CHECKIN') NOT NULL,
    module VARCHAR(50) NULL,
    record_id INT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================
-- SEED DATA
-- =====================

-- Admin account (password: admin123)
INSERT INTO users (email, username, password_hash, role, status) VALUES
('admin@meditrack.com', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');

-- Doctor account (password: doctor123)
INSERT INTO users (email, username, password_hash, role, status) VALUES
('doctor@meditrack.com', 'doctor', '$2y$10$YourHashHere', 'doctor', 'active');

-- Doctor profile
INSERT INTO doctors (user_id, full_name, specialization, license_number, consultation_fee, experience_years, bio) VALUES
(2, 'Dr. Maria Santos', 'Internal Medicine', 'PRC-IM-2024-001', 500.00, 10, 'Board-certified Internal Medicine specialist with 10 years of clinical experience.');

-- Doctor schedule (Monday-Friday, 8AM-5PM, 30-min slots)
INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, max_patients, is_active) VALUES
(1, 1, '08:00:00', '17:00:00', 30, 20, 1),
(1, 2, '08:00:00', '17:00:00', 30, 20, 1),
(1, 3, '08:00:00', '17:00:00', 30, 20, 1),
(1, 4, '08:00:00', '17:00:00', 30, 20, 1),
(1, 5, '08:00:00', '17:00:00', 30, 20, 1);
```

- [ ] **Step 2: Generate proper bcrypt hashes for seed passwords**

Create a temporary PHP script to generate the correct password hashes, then update the schema:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/meditrack
php -r "echo 'admin123: ' . password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 10]) . PHP_EOL; echo 'doctor123: ' . password_hash('doctor123', PASSWORD_BCRYPT, ['cost' => 10]) . PHP_EOL;"
```

Replace the placeholder hash values in `schema.sql` with the real output.

- [ ] **Step 3: Import the schema into MySQL**

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack < /Applications/XAMPP/xamppfiles/htdocs/meditrack/database/schema.sql
```

If the database doesn't exist:
```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root -e "CREATE DATABASE IF NOT EXISTS meditrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
/Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack < /Applications/XAMPP/xamppfiles/htdocs/meditrack/database/schema.sql
```

- [ ] **Step 4: Verify seed data**

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root meditrack -e "SELECT id, email, role FROM users; SELECT id, full_name, specialization FROM doctors; SELECT doctor_id, day_of_week, start_time, end_time FROM doctor_schedules;"
```

Expected: 2 users (admin + doctor), 1 doctor profile, 5 schedule entries (Mon-Fri).

- [ ] **Step 5: Commit**

```bash
git add database/schema.sql
git commit -m "feat: simplified database schema with seed data for single-doctor clinic"
```

---

### Task 3: Config Simplification

**Files:**
- Modify: `config/config.php`
- Keep: `config/database.php` (no changes needed)

- [ ] **Step 1: Simplify config.php**

Remove email constants, simplify audit logging to use `activity_logs` table instead of `audit_logs`, remove unused settings. Write the updated `config/config.php`:

```php
<?php
/**
 * MediTrack Application Configuration
 * Internal Medicine Clinic — Single Doctor System
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment config
$env = require __DIR__ . '/../env.php';

// Error reporting
if (($env['ENVIRONMENT'] ?? 'production') === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Timezone
date_default_timezone_set('Asia/Manila');

// Application settings
define('APP_NAME', 'MediTrack');
define('APP_URL', $env['APP_URL'] ?? 'http://localhost/meditrack');
define('APP_VERSION', '2.0.0');

// Security settings
if (!defined('SECRET_KEY')) {
    $envKey = getenv('MEDITRACK_SECRET_KEY');
    define('SECRET_KEY', $envKey ?: 'mt-' . md5(__DIR__ . php_uname('n')));
}
define('PASSWORD_HASH_ALGO', PASSWORD_BCRYPT);
define('PASSWORD_HASH_COST', 10);

// QR Code settings
define('QR_EXPIRY_HOURS', 24);
define('QR_SIZE', 300);

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png']);

// Pagination
define('ITEMS_PER_PAGE', 20);

// CORS settings
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ---- Helper Functions ----

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function logActivity($db, $userId, $username, $role, $actionType, $module, $recordId, $description) {
    try {
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, username, user_role, action_type, module, record_id, description, ip_address) VALUES (:user_id, :username, :user_role, :action_type, :module, :record_id, :description, :ip_address)");
        $stmt->execute([
            ':user_id' => $userId,
            ':username' => $username,
            ':user_role' => $role,
            ':action_type' => $actionType,
            ':module' => $module,
            ':record_id' => $recordId,
            ':description' => $description,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add config/config.php
git commit -m "feat: simplify config for single-doctor clinic system"
```

---

### Task 4: Auth API Endpoints

**Files:**
- Rewrite: `api/auth/login.php`
- Rewrite: `api/auth/register.php`
- Rewrite: `api/auth/logout.php`
- Rewrite: `api/auth/request-otp.php`
- Rewrite: `api/auth/reset-password.php`

- [ ] **Step 1: Write login.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$email = sanitizeInput($data['email'] ?? '');
$password = $data['password'] ?? '';

if (empty($email) || empty($password)) {
    sendJSON(['success' => false, 'message' => 'Email and password are required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT id, email, username, password_hash, role, status FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        sendJSON(['success' => false, 'message' => 'Invalid email or password'], 401);
    }

    if ($user['status'] !== 'active') {
        sendJSON(['success' => false, 'message' => 'Your account has been deactivated'], 403);
    }

    // Get profile ID based on role
    $profileId = null;
    $fullName = $user['username'];

    if ($user['role'] === 'patient') {
        $profileStmt = $db->prepare("SELECT id, full_name FROM patients WHERE user_id = :user_id");
        $profileStmt->execute([':user_id' => $user['id']]);
        $profile = $profileStmt->fetch();
        $profileId = $profile['id'] ?? null;
        $fullName = $profile['full_name'] ?? $user['username'];
    } elseif ($user['role'] === 'doctor') {
        $profileStmt = $db->prepare("SELECT id, full_name FROM doctors WHERE user_id = :user_id");
        $profileStmt->execute([':user_id' => $user['id']]);
        $profile = $profileStmt->fetch();
        $profileId = $profile['id'] ?? null;
        $fullName = $profile['full_name'] ?? $user['username'];
    }

    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $fullName;
    $_SESSION['profile_id'] = $profileId;

    // Update last login
    $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id")->execute([':id' => $user['id']]);

    // Log activity
    logActivity($db, $user['id'], $user['username'], $user['role'], 'LOGIN', 'auth', $user['id'], 'User logged in');

    sendJSON([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'username' => $user['username'],
            'role' => $user['role'],
            'full_name' => $fullName,
            'profile_id' => $profileId
        ]
    ]);

} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 2: Write register.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

$fullName = sanitizeInput($data['full_name'] ?? '');
$email = sanitizeInput($data['email'] ?? '');
$username = sanitizeInput($data['username'] ?? '');
$password = $data['password'] ?? '';
$dob = sanitizeInput($data['date_of_birth'] ?? '');
$gender = sanitizeInput($data['gender'] ?? '');
$contact = sanitizeInput($data['contact_number'] ?? '');
$address = sanitizeInput($data['address'] ?? '');
$region = sanitizeInput($data['region'] ?? '');
$city = sanitizeInput($data['city'] ?? '');
$barangay = sanitizeInput($data['barangay'] ?? '');
$bloodGroup = sanitizeInput($data['blood_group'] ?? '');
$allergies = sanitizeInput($data['allergies'] ?? '');
$emergencyName = sanitizeInput($data['emergency_contact_name'] ?? '');
$emergencyNumber = sanitizeInput($data['emergency_contact_number'] ?? '');

// Validation
if (empty($fullName) || empty($email) || empty($username) || empty($password)) {
    sendJSON(['success' => false, 'message' => 'Full name, email, username, and password are required'], 400);
}

if (strlen($password) < 6) {
    sendJSON(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJSON(['success' => false, 'message' => 'Invalid email format'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Check duplicate email/username
    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email OR username = :username");
    $stmt->execute([':email' => $email, ':username' => $username]);
    if ($stmt->rowCount() > 0) {
        sendJSON(['success' => false, 'message' => 'Email or username already exists'], 409);
    }

    $db->beginTransaction();

    // Create user
    $passwordHash = password_hash($password, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
    $stmt = $db->prepare("INSERT INTO users (email, username, password_hash, role) VALUES (:email, :username, :password_hash, 'patient')");
    $stmt->execute([':email' => $email, ':username' => $username, ':password_hash' => $passwordHash]);
    $userId = $db->lastInsertId();

    // Create patient profile
    $stmt = $db->prepare("INSERT INTO patients (user_id, full_name, date_of_birth, gender, contact_number, address, region, city, barangay, blood_group, allergies, emergency_contact_name, emergency_contact_number) VALUES (:user_id, :full_name, :dob, :gender, :contact, :address, :region, :city, :barangay, :blood_group, :allergies, :emergency_name, :emergency_number)");
    $stmt->execute([
        ':user_id' => $userId,
        ':full_name' => $fullName,
        ':dob' => $dob ?: null,
        ':gender' => $gender ?: null,
        ':contact' => $contact ?: null,
        ':address' => $address ?: null,
        ':region' => $region ?: null,
        ':city' => $city ?: null,
        ':barangay' => $barangay ?: null,
        ':blood_group' => $bloodGroup ?: null,
        ':allergies' => $allergies ?: null,
        ':emergency_name' => $emergencyName ?: null,
        ':emergency_number' => $emergencyNumber ?: null
    ]);

    $db->commit();

    logActivity($db, $userId, $username, 'patient', 'CREATE', 'auth', $userId, 'New patient registered');

    sendJSON(['success' => true, 'message' => 'Registration successful. You can now login.']);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Registration error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 3: Write logout.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (isLoggedIn()) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        logActivity($db, $_SESSION['user_id'], $_SESSION['username'] ?? '', $_SESSION['role'] ?? '', 'LOGOUT', 'auth', $_SESSION['user_id'], 'User logged out');
    } catch (Exception $e) {
        error_log("Logout log error: " . $e->getMessage());
    }
}

session_destroy();
sendJSON(['success' => true, 'message' => 'Logged out successfully']);
```

- [ ] **Step 4: Write request-otp.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$email = sanitizeInput($data['email'] ?? '');

if (empty($email)) {
    sendJSON(['success' => false, 'message' => 'Email is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email AND status = 'active'");
    $stmt->execute([':email' => $email]);

    if ($stmt->rowCount() === 0) {
        // Don't reveal whether email exists
        sendJSON(['success' => true, 'message' => 'If this email is registered, an OTP has been generated']);
    }

    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Delete old OTPs for this email
    $db->prepare("DELETE FROM password_resets WHERE email = :email")->execute([':email' => $email]);

    $stmt = $db->prepare("INSERT INTO password_resets (email, otp, expires_at) VALUES (:email, :otp, :expires_at)");
    $stmt->execute([':email' => $email, ':otp' => $otp, ':expires_at' => $expiresAt]);

    // In production, send OTP via email. For now, return it in the response for testing.
    sendJSON([
        'success' => true,
        'message' => 'OTP generated. Check your email.',
        'otp' => $otp // Remove in production
    ]);

} catch (Exception $e) {
    error_log("OTP request error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 5: Write reset-password.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$email = sanitizeInput($data['email'] ?? '');
$otp = sanitizeInput($data['otp'] ?? '');
$newPassword = $data['new_password'] ?? '';

if (empty($email) || empty($otp) || empty($newPassword)) {
    sendJSON(['success' => false, 'message' => 'Email, OTP, and new password are required'], 400);
}

if (strlen($newPassword) < 6) {
    sendJSON(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT id FROM password_resets WHERE email = :email AND otp = :otp AND expires_at > NOW() AND used = 0");
    $stmt->execute([':email' => $email, ':otp' => $otp]);

    if ($stmt->rowCount() === 0) {
        sendJSON(['success' => false, 'message' => 'Invalid or expired OTP'], 400);
    }

    $resetId = $stmt->fetch()['id'];

    $db->beginTransaction();

    // Update password
    $passwordHash = password_hash($newPassword, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
    $db->prepare("UPDATE users SET password_hash = :hash WHERE email = :email")
       ->execute([':hash' => $passwordHash, ':email' => $email]);

    // Mark OTP as used
    $db->prepare("UPDATE password_resets SET used = 1 WHERE id = :id")
       ->execute([':id' => $resetId]);

    $db->commit();

    sendJSON(['success' => true, 'message' => 'Password reset successful. You can now login with your new password.']);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Password reset error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 6: Verify auth endpoints work**

```bash
# Test login
curl -s -X POST http://localhost/meditrack/api/auth/login.php -H "Content-Type: application/json" -d '{"email":"admin@meditrack.com","password":"admin123"}' | python3 -m json.tool

# Test login with doctor
curl -s -X POST http://localhost/meditrack/api/auth/login.php -H "Content-Type: application/json" -d '{"email":"doctor@meditrack.com","password":"doctor123"}' | python3 -m json.tool
```

Expected: both return `success: true` with user data and correct roles.

- [ ] **Step 7: Commit**

```bash
git add api/auth/
git commit -m "feat: auth API endpoints — login, register, logout, OTP, password reset"
```

---

### Task 5: Patient API Endpoints

**Files:**
- Rewrite: `api/patient/get-profile.php`
- Rewrite: `api/patient/update-profile.php`
- Rewrite: `api/patient/change-password.php`
- Rewrite: `api/patient/get-appointments.php`
- Rewrite: `api/patient/book-appointment.php`
- Rewrite: `api/patient/cancel-appointment.php`
- Rewrite: `api/patient/get-medical-records.php`
- Rewrite: `api/patient/get-available-slots.php`

- [ ] **Step 1: Write get-profile.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('patient')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT p.*, u.email, u.username FROM patients p JOIN users u ON p.user_id = u.id WHERE p.user_id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $patient = $stmt->fetch();

    if (!$patient) {
        sendJSON(['success' => false, 'message' => 'Profile not found'], 404);
    }

    unset($patient['user_id']);
    sendJSON(['success' => true, 'patient' => $patient]);

} catch (Exception $e) {
    error_log("Get profile error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 2: Write update-profile.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('patient')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("UPDATE patients SET full_name = :full_name, date_of_birth = :dob, gender = :gender, contact_number = :contact, address = :address, region = :region, city = :city, barangay = :barangay, blood_group = :blood_group, allergies = :allergies, emergency_contact_name = :emergency_name, emergency_contact_number = :emergency_number WHERE user_id = :user_id");
    $stmt->execute([
        ':full_name' => sanitizeInput($data['full_name'] ?? ''),
        ':dob' => $data['date_of_birth'] ?? null,
        ':gender' => $data['gender'] ?? null,
        ':contact' => sanitizeInput($data['contact_number'] ?? ''),
        ':address' => sanitizeInput($data['address'] ?? ''),
        ':region' => sanitizeInput($data['region'] ?? ''),
        ':city' => sanitizeInput($data['city'] ?? ''),
        ':barangay' => sanitizeInput($data['barangay'] ?? ''),
        ':blood_group' => sanitizeInput($data['blood_group'] ?? ''),
        ':allergies' => sanitizeInput($data['allergies'] ?? ''),
        ':emergency_name' => sanitizeInput($data['emergency_contact_name'] ?? ''),
        ':emergency_number' => sanitizeInput($data['emergency_contact_number'] ?? ''),
        ':user_id' => $_SESSION['user_id']
    ]);

    $_SESSION['full_name'] = sanitizeInput($data['full_name'] ?? $_SESSION['full_name']);

    logActivity($db, $_SESSION['user_id'], $_SESSION['username'], 'patient', 'UPDATE', 'patients', $_SESSION['profile_id'], 'Patient updated profile');

    sendJSON(['success' => true, 'message' => 'Profile updated successfully']);

} catch (Exception $e) {
    error_log("Update profile error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 3: Write change-password.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('patient')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$currentPassword = $data['current_password'] ?? '';
$newPassword = $data['new_password'] ?? '';

if (empty($currentPassword) || empty($newPassword)) {
    sendJSON(['success' => false, 'message' => 'Current and new passwords are required'], 400);
}
if (strlen($newPassword) < 6) {
    sendJSON(['success' => false, 'message' => 'New password must be at least 6 characters'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!password_verify($currentPassword, $user['password_hash'])) {
        sendJSON(['success' => false, 'message' => 'Current password is incorrect'], 400);
    }

    $hash = password_hash($newPassword, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
    $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id")->execute([':hash' => $hash, ':id' => $_SESSION['user_id']]);

    logActivity($db, $_SESSION['user_id'], $_SESSION['username'], 'patient', 'UPDATE', 'users', $_SESSION['user_id'], 'Patient changed password');

    sendJSON(['success' => true, 'message' => 'Password changed successfully']);

} catch (Exception $e) {
    error_log("Change password error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 4: Write book-appointment.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/QRCodeGenerator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('patient')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$appointmentDate = sanitizeInput($data['appointment_date'] ?? '');
$appointmentTime = sanitizeInput($data['appointment_time'] ?? '');
$reason = sanitizeInput($data['reason_for_visit'] ?? '');

if (empty($appointmentDate) || empty($appointmentTime)) {
    sendJSON(['success' => false, 'message' => 'Date and time are required'], 400);
}

// Validate date is not in the past
if (strtotime($appointmentDate) < strtotime('today')) {
    sendJSON(['success' => false, 'message' => 'Cannot book appointments in the past'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get the single active doctor
    $doctorStmt = $db->prepare("SELECT d.id, d.full_name FROM doctors d WHERE d.status = 'active' LIMIT 1");
    $doctorStmt->execute();
    $doctor = $doctorStmt->fetch();

    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'No doctor available'], 400);
    }

    // Check doctor has schedule for this day
    $dayOfWeek = date('w', strtotime($appointmentDate));
    $schedStmt = $db->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = :doctor_id AND day_of_week = :day AND is_active = 1");
    $schedStmt->execute([':doctor_id' => $doctor['id'], ':day' => $dayOfWeek]);
    $schedule = $schedStmt->fetch();

    if (!$schedule) {
        sendJSON(['success' => false, 'message' => 'Doctor is not available on this day'], 400);
    }

    // Check time is within schedule
    if ($appointmentTime < $schedule['start_time'] || $appointmentTime >= $schedule['end_time']) {
        sendJSON(['success' => false, 'message' => 'Selected time is outside doctor schedule'], 400);
    }

    // Check for duplicate booking (same patient, same date, not cancelled)
    $dupStmt = $db->prepare("SELECT id FROM appointments WHERE patient_id = :patient_id AND appointment_date = :date AND status NOT IN ('cancelled') ");
    $dupStmt->execute([':patient_id' => $_SESSION['profile_id'], ':date' => $appointmentDate]);
    if ($dupStmt->rowCount() > 0) {
        sendJSON(['success' => false, 'message' => 'You already have an appointment on this date'], 400);
    }

    // Check time slot not taken
    $slotStmt = $db->prepare("SELECT id FROM appointments WHERE doctor_id = :doctor_id AND appointment_date = :date AND appointment_time = :time AND status NOT IN ('cancelled')");
    $slotStmt->execute([':doctor_id' => $doctor['id'], ':date' => $appointmentDate, ':time' => $appointmentTime]);
    if ($slotStmt->rowCount() > 0) {
        sendJSON(['success' => false, 'message' => 'This time slot is already booked'], 400);
    }

    // Check max patients for this day
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = :doctor_id AND appointment_date = :date AND status NOT IN ('cancelled')");
    $countStmt->execute([':doctor_id' => $doctor['id'], ':date' => $appointmentDate]);
    if ($countStmt->fetch()['total'] >= $schedule['max_patients']) {
        sendJSON(['success' => false, 'message' => 'Maximum appointments reached for this day'], 400);
    }

    $db->beginTransaction();

    // Generate appointment number
    $appointmentNumber = 'APT-' . date('Ymd', strtotime($appointmentDate)) . '-' . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

    // Create appointment
    $stmt = $db->prepare("INSERT INTO appointments (appointment_number, patient_id, doctor_id, appointment_date, appointment_time, reason_for_visit) VALUES (:number, :patient_id, :doctor_id, :date, :time, :reason)");
    $stmt->execute([
        ':number' => $appointmentNumber,
        ':patient_id' => $_SESSION['profile_id'],
        ':doctor_id' => $doctor['id'],
        ':date' => $appointmentDate,
        ':time' => $appointmentTime,
        ':reason' => $reason ?: null
    ]);
    $appointmentId = $db->lastInsertId();

    // Generate QR code
    $qrGenerator = new QRCodeGenerator($db);
    $qrData = $qrGenerator->generateQRCode($appointmentId);

    $db->commit();

    logActivity($db, $_SESSION['user_id'], $_SESSION['username'], 'patient', 'CREATE', 'appointments', $appointmentId, "Booked appointment {$appointmentNumber}");

    sendJSON([
        'success' => true,
        'message' => 'Appointment booked successfully',
        'appointment' => [
            'id' => $appointmentId,
            'appointment_number' => $appointmentNumber,
            'doctor_name' => $doctor['full_name'],
            'date' => $appointmentDate,
            'time' => $appointmentTime,
            'status' => 'scheduled',
            'qr_image' => $qrData['qr_image'],
            'qr_token' => $qrData['token_hash']
        ]
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Book appointment error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 5: Write get-appointments.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('patient')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT a.*, d.full_name as doctor_name, d.specialization, qt.token_hash, qt.is_used, qt.expires_at as qr_expires_at FROM appointments a JOIN doctors d ON a.doctor_id = d.id LEFT JOIN qr_tokens qt ON a.id = qt.appointment_id WHERE a.patient_id = :patient_id ORDER BY a.appointment_date DESC, a.appointment_time DESC");
    $stmt->execute([':patient_id' => $_SESSION['profile_id']]);
    $appointments = $stmt->fetchAll();

    sendJSON(['success' => true, 'appointments' => $appointments]);

} catch (Exception $e) {
    error_log("Get appointments error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 6: Write cancel-appointment.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('patient')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$appointmentId = intval($data['appointment_id'] ?? 0);

if ($appointmentId <= 0) {
    sendJSON(['success' => false, 'message' => 'Invalid appointment ID'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verify appointment belongs to patient and is cancellable
    $stmt = $db->prepare("SELECT id, status, appointment_number FROM appointments WHERE id = :id AND patient_id = :patient_id");
    $stmt->execute([':id' => $appointmentId, ':patient_id' => $_SESSION['profile_id']]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        sendJSON(['success' => false, 'message' => 'Appointment not found'], 404);
    }

    if (!in_array($appointment['status'], ['scheduled'])) {
        sendJSON(['success' => false, 'message' => 'Only scheduled appointments can be cancelled'], 400);
    }

    $db->prepare("UPDATE appointments SET status = 'cancelled', cancelled_at = NOW() WHERE id = :id")->execute([':id' => $appointmentId]);

    logActivity($db, $_SESSION['user_id'], $_SESSION['username'], 'patient', 'UPDATE', 'appointments', $appointmentId, "Cancelled appointment {$appointment['appointment_number']}");

    sendJSON(['success' => true, 'message' => 'Appointment cancelled']);

} catch (Exception $e) {
    error_log("Cancel appointment error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 7: Write get-medical-records.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('patient')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT mr.*, a.appointment_number, a.appointment_date, a.appointment_time, d.full_name as doctor_name, d.specialization FROM medical_records mr JOIN appointments a ON mr.appointment_id = a.id JOIN doctors d ON mr.doctor_id = d.id WHERE mr.patient_id = :patient_id ORDER BY mr.created_at DESC");
    $stmt->execute([':patient_id' => $_SESSION['profile_id']]);
    $records = $stmt->fetchAll();

    // Parse vital_signs JSON
    foreach ($records as &$record) {
        if ($record['vital_signs']) {
            $record['vital_signs'] = json_decode($record['vital_signs'], true);
        }
    }

    sendJSON(['success' => true, 'records' => $records]);

} catch (Exception $e) {
    error_log("Get medical records error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 8: Write get-available-slots.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('patient')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$date = sanitizeInput($_GET['date'] ?? '');
if (empty($date)) {
    sendJSON(['success' => false, 'message' => 'Date is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get the single active doctor
    $doctorStmt = $db->prepare("SELECT d.id, d.full_name, d.specialization, d.consultation_fee FROM doctors d WHERE d.status = 'active' LIMIT 1");
    $doctorStmt->execute();
    $doctor = $doctorStmt->fetch();

    if (!$doctor) {
        sendJSON(['success' => true, 'available' => false, 'message' => 'No doctor available', 'slots' => []]);
    }

    // Get schedule for this day of week
    $dayOfWeek = date('w', strtotime($date));
    $schedStmt = $db->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = :doctor_id AND day_of_week = :day AND is_active = 1");
    $schedStmt->execute([':doctor_id' => $doctor['id'], ':day' => $dayOfWeek]);
    $schedule = $schedStmt->fetch();

    if (!$schedule) {
        sendJSON(['success' => true, 'available' => false, 'message' => 'Doctor is not available on this day', 'slots' => [], 'doctor' => $doctor]);
    }

    // Get booked slots for this date
    $bookedStmt = $db->prepare("SELECT appointment_time FROM appointments WHERE doctor_id = :doctor_id AND appointment_date = :date AND status NOT IN ('cancelled')");
    $bookedStmt->execute([':doctor_id' => $doctor['id'], ':date' => $date]);
    $bookedSlots = $bookedStmt->fetchAll(PDO::FETCH_COLUMN);

    // Generate all time slots
    $slots = [];
    $startTime = strtotime($schedule['start_time']);
    $endTime = strtotime($schedule['end_time']);
    $duration = $schedule['slot_duration'] * 60; // Convert to seconds

    while ($startTime < $endTime) {
        $timeStr = date('H:i:s', $startTime);
        $isBooked = in_array($timeStr, $bookedSlots);

        // If date is today, skip past time slots
        $isPast = false;
        if ($date === date('Y-m-d') && $startTime < time()) {
            $isPast = true;
        }

        $slots[] = [
            'time' => $timeStr,
            'display' => date('g:i A', $startTime),
            'available' => !$isBooked && !$isPast,
            'booked' => $isBooked,
            'past' => $isPast
        ];

        $startTime += $duration;
    }

    sendJSON([
        'success' => true,
        'available' => true,
        'doctor' => $doctor,
        'schedule' => [
            'start_time' => $schedule['start_time'],
            'end_time' => $schedule['end_time'],
            'slot_duration' => $schedule['slot_duration']
        ],
        'slots' => $slots
    ]);

} catch (Exception $e) {
    error_log("Get available slots error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 9: Commit**

```bash
git add api/patient/
git commit -m "feat: patient API endpoints — profile, appointments, booking, medical records"
```

---

### Task 6: Doctor API Endpoints

**Files:**
- Rewrite: `api/doctor/get-profile.php`
- Rewrite: `api/doctor/change-password.php`
- Rewrite: `api/doctor/get-appointments.php`
- Create: `api/doctor/checkin-patient.php`
- Rewrite: `api/doctor/save-medical-record.php`
- Rewrite: `api/doctor/stats.php`

- [ ] **Step 1: Write get-profile.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('doctor')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT d.*, u.email, u.username FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.user_id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $doctor = $stmt->fetch();

    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'Profile not found'], 404);
    }

    // Get schedule
    $schedStmt = $db->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = :doctor_id ORDER BY day_of_week");
    $schedStmt->execute([':doctor_id' => $doctor['id']]);
    $doctor['schedules'] = $schedStmt->fetchAll();

    sendJSON(['success' => true, 'doctor' => $doctor]);

} catch (Exception $e) {
    error_log("Get doctor profile error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 2: Write change-password.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('doctor')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$currentPassword = $data['current_password'] ?? '';
$newPassword = $data['new_password'] ?? '';

if (empty($currentPassword) || empty($newPassword)) {
    sendJSON(['success' => false, 'message' => 'Current and new passwords are required'], 400);
}
if (strlen($newPassword) < 6) {
    sendJSON(['success' => false, 'message' => 'New password must be at least 6 characters'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!password_verify($currentPassword, $user['password_hash'])) {
        sendJSON(['success' => false, 'message' => 'Current password is incorrect'], 400);
    }

    $hash = password_hash($newPassword, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
    $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id")->execute([':hash' => $hash, ':id' => $_SESSION['user_id']]);

    logActivity($db, $_SESSION['user_id'], $_SESSION['username'], 'doctor', 'UPDATE', 'users', $_SESSION['user_id'], 'Doctor changed password');

    sendJSON(['success' => true, 'message' => 'Password changed successfully']);

} catch (Exception $e) {
    error_log("Doctor change password error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 3: Write get-appointments.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('doctor')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$dateFilter = sanitizeInput($_GET['date'] ?? '');
$statusFilter = sanitizeInput($_GET['status'] ?? '');

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT a.*, p.full_name as patient_name, p.date_of_birth, p.gender, p.contact_number, p.blood_group, p.allergies, qt.token_hash, qt.is_used as qr_used FROM appointments a JOIN patients p ON a.patient_id = p.id LEFT JOIN qr_tokens qt ON a.id = qt.appointment_id WHERE a.doctor_id = :doctor_id";
    $params = [':doctor_id' => $_SESSION['profile_id']];

    if (!empty($dateFilter)) {
        $query .= " AND a.appointment_date = :date";
        $params[':date'] = $dateFilter;
    }
    if (!empty($statusFilter)) {
        $query .= " AND a.status = :status";
        $params[':status'] = $statusFilter;
    }

    $query .= " ORDER BY a.appointment_date DESC, a.appointment_time ASC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();

    sendJSON(['success' => true, 'appointments' => $appointments]);

} catch (Exception $e) {
    error_log("Doctor get appointments error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 4: Write checkin-patient.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/QRCodeGenerator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('doctor')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$tokenHash = sanitizeInput($data['token_hash'] ?? '');

if (empty($tokenHash)) {
    sendJSON(['success' => false, 'message' => 'QR token is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $qrGenerator = new QRCodeGenerator($db);
    $result = $qrGenerator->validateQRCode($tokenHash, $_SESSION['user_id']);

    if (!$result['valid']) {
        sendJSON(['success' => false, 'message' => $result['message']], 400);
    }

    // Verify appointment belongs to this doctor
    if ($result['doctor_id'] != $_SESSION['profile_id']) {
        sendJSON(['success' => false, 'message' => 'This appointment is not assigned to you'], 403);
    }

    // Update appointment status to checked_in
    $db->prepare("UPDATE appointments SET status = 'checked_in', checked_in_at = NOW() WHERE id = :id")
       ->execute([':id' => $result['appointment_id']]);

    // Get patient details
    $patientStmt = $db->prepare("SELECT p.full_name, p.date_of_birth, p.gender, p.blood_group, p.allergies, p.contact_number, a.appointment_number, a.appointment_time, a.reason_for_visit FROM patients p JOIN appointments a ON p.id = a.patient_id WHERE a.id = :id");
    $patientStmt->execute([':id' => $result['appointment_id']]);
    $patientInfo = $patientStmt->fetch();

    logActivity($db, $_SESSION['user_id'], $_SESSION['username'], 'doctor', 'CHECKIN', 'appointments', $result['appointment_id'], "Checked in patient via QR — {$patientInfo['appointment_number']}");

    sendJSON([
        'success' => true,
        'message' => 'Patient checked in successfully',
        'patient' => $patientInfo
    ]);

} catch (Exception $e) {
    error_log("Check-in error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 5: Write save-medical-record.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('doctor')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$appointmentId = intval($data['appointment_id'] ?? 0);

if ($appointmentId <= 0) {
    sendJSON(['success' => false, 'message' => 'Invalid appointment ID'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verify appointment belongs to this doctor and is checked_in
    $stmt = $db->prepare("SELECT id, patient_id, status, appointment_number FROM appointments WHERE id = :id AND doctor_id = :doctor_id");
    $stmt->execute([':id' => $appointmentId, ':doctor_id' => $_SESSION['profile_id']]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        sendJSON(['success' => false, 'message' => 'Appointment not found'], 404);
    }

    if (!in_array($appointment['status'], ['checked_in', 'in_progress'])) {
        sendJSON(['success' => false, 'message' => 'Patient must be checked in first'], 400);
    }

    $vitalSigns = json_encode([
        'bp' => sanitizeInput($data['bp'] ?? ''),
        'temp' => sanitizeInput($data['temperature'] ?? ''),
        'heart_rate' => sanitizeInput($data['heart_rate'] ?? ''),
        'weight' => sanitizeInput($data['weight'] ?? ''),
        'height' => sanitizeInput($data['height'] ?? '')
    ]);

    $db->beginTransaction();

    // Insert or update medical record
    $stmt = $db->prepare("INSERT INTO medical_records (appointment_id, patient_id, doctor_id, chief_complaint, symptoms, vital_signs, diagnosis, prescription, lab_tests_ordered, notes, follow_up_date) VALUES (:appointment_id, :patient_id, :doctor_id, :complaint, :symptoms, :vitals, :diagnosis, :prescription, :lab_tests, :notes, :follow_up) ON DUPLICATE KEY UPDATE chief_complaint = VALUES(chief_complaint), symptoms = VALUES(symptoms), vital_signs = VALUES(vital_signs), diagnosis = VALUES(diagnosis), prescription = VALUES(prescription), lab_tests_ordered = VALUES(lab_tests_ordered), notes = VALUES(notes), follow_up_date = VALUES(follow_up_date)");
    $stmt->execute([
        ':appointment_id' => $appointmentId,
        ':patient_id' => $appointment['patient_id'],
        ':doctor_id' => $_SESSION['profile_id'],
        ':complaint' => sanitizeInput($data['chief_complaint'] ?? ''),
        ':symptoms' => sanitizeInput($data['symptoms'] ?? ''),
        ':vitals' => $vitalSigns,
        ':diagnosis' => sanitizeInput($data['diagnosis'] ?? ''),
        ':prescription' => sanitizeInput($data['prescription'] ?? ''),
        ':lab_tests' => sanitizeInput($data['lab_tests_ordered'] ?? ''),
        ':notes' => sanitizeInput($data['notes'] ?? ''),
        ':follow_up' => !empty($data['follow_up_date']) ? $data['follow_up_date'] : null
    ]);

    // Mark appointment as completed
    $db->prepare("UPDATE appointments SET status = 'completed', completed_at = NOW() WHERE id = :id")
       ->execute([':id' => $appointmentId]);

    $db->commit();

    logActivity($db, $_SESSION['user_id'], $_SESSION['username'], 'doctor', 'CREATE', 'medical_records', $appointmentId, "Saved medical record for {$appointment['appointment_number']}");

    sendJSON(['success' => true, 'message' => 'Medical record saved and appointment completed']);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Save medical record error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 6: Write stats.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('doctor')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $doctorId = $_SESSION['profile_id'];
    $today = date('Y-m-d');

    // Today's appointments count
    $todayStmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = :did AND appointment_date = :today AND status NOT IN ('cancelled')");
    $todayStmt->execute([':did' => $doctorId, ':today' => $today]);
    $todayCount = $todayStmt->fetch()['total'];

    // Today's checked in
    $checkedInStmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = :did AND appointment_date = :today AND status = 'checked_in'");
    $checkedInStmt->execute([':did' => $doctorId, ':today' => $today]);
    $checkedIn = $checkedInStmt->fetch()['total'];

    // Today's completed
    $completedStmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = :did AND appointment_date = :today AND status = 'completed'");
    $completedStmt->execute([':did' => $doctorId, ':today' => $today]);
    $completedToday = $completedStmt->fetch()['total'];

    // Total patients (unique)
    $patientsStmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM appointments WHERE doctor_id = :did AND status NOT IN ('cancelled')");
    $patientsStmt->execute([':did' => $doctorId]);
    $totalPatients = $patientsStmt->fetch()['total'];

    // Total completed all time
    $allCompletedStmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = :did AND status = 'completed'");
    $allCompletedStmt->execute([':did' => $doctorId]);
    $totalCompleted = $allCompletedStmt->fetch()['total'];

    sendJSON([
        'success' => true,
        'stats' => [
            'today_appointments' => (int)$todayCount,
            'today_checked_in' => (int)$checkedIn,
            'today_completed' => (int)$completedToday,
            'total_patients' => (int)$totalPatients,
            'total_completed' => (int)$totalCompleted
        ]
    ]);

} catch (Exception $e) {
    error_log("Doctor stats error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 7: Delete unused doctor API files**

```bash
rm -f api/doctor/profile.php api/doctor/update-profile.php api/doctor/get-patients.php
```

- [ ] **Step 8: Commit**

```bash
git add api/doctor/
git commit -m "feat: doctor API endpoints — profile, appointments, QR check-in, medical records, stats"
```

---

### Task 7: Admin API Endpoints

**Files:**
- Rewrite: `api/admin/stats.php`
- Rewrite: `api/admin/appointments.php`
- Create: `api/admin/cancel-appointment.php`
- Rewrite: `api/admin/get-all-patients.php`
- Create: `api/admin/update-patient-status.php`
- Create: `api/admin/get-doctor-schedule.php`
- Create: `api/admin/update-doctor-schedule.php`
- Rewrite: `api/admin/reports-data.php`
- Rewrite: `api/admin/activity.php`
- Create: `api/admin/get-doctor-profile.php`

- [ ] **Step 1: Write stats.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $today = date('Y-m-d');

    $stats = [];

    // Total patients
    $stmt = $db->query("SELECT COUNT(*) as total FROM patients");
    $stats['total_patients'] = (int)$stmt->fetch()['total'];

    // Today's appointments
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE appointment_date = :today AND status NOT IN ('cancelled')");
    $stmt->execute([':today' => $today]);
    $stats['today_appointments'] = (int)$stmt->fetch()['total'];

    // This week
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $weekEnd = date('Y-m-d', strtotime('sunday this week'));
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE appointment_date BETWEEN :start AND :end AND status NOT IN ('cancelled')");
    $stmt->execute([':start' => $weekStart, ':end' => $weekEnd]);
    $stats['week_appointments'] = (int)$stmt->fetch()['total'];

    // This month
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE appointment_date BETWEEN :start AND :end AND status NOT IN ('cancelled')");
    $stmt->execute([':start' => $monthStart, ':end' => $monthEnd]);
    $stats['month_appointments'] = (int)$stmt->fetch()['total'];

    // Completed
    $stmt = $db->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'completed'");
    $stats['total_completed'] = (int)$stmt->fetch()['total'];

    // Cancelled
    $stmt = $db->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'cancelled'");
    $stats['total_cancelled'] = (int)$stmt->fetch()['total'];

    sendJSON(['success' => true, 'stats' => $stats]);

} catch (Exception $e) {
    error_log("Admin stats error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 2: Write appointments.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$dateFilter = sanitizeInput($_GET['date'] ?? '');
$statusFilter = sanitizeInput($_GET['status'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

try {
    $database = new Database();
    $db = $database->getConnection();

    $where = "1=1";
    $params = [];

    if (!empty($dateFilter)) {
        $where .= " AND a.appointment_date = :date";
        $params[':date'] = $dateFilter;
    }
    if (!empty($statusFilter)) {
        $where .= " AND a.status = :status";
        $params[':status'] = $statusFilter;
    }

    // Count total
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM appointments a WHERE $where");
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];

    // Fetch appointments
    $query = "SELECT a.*, p.full_name as patient_name, p.contact_number, d.full_name as doctor_name FROM appointments a JOIN patients p ON a.patient_id = p.id JOIN doctors d ON a.doctor_id = d.id WHERE $where ORDER BY a.appointment_date DESC, a.appointment_time ASC LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();

    sendJSON([
        'success' => true,
        'appointments' => $appointments,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_records' => (int)$total,
            'per_page' => $limit
        ]
    ]);

} catch (Exception $e) {
    error_log("Admin appointments error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 3: Write cancel-appointment.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$appointmentId = intval($data['appointment_id'] ?? 0);

if ($appointmentId <= 0) {
    sendJSON(['success' => false, 'message' => 'Invalid appointment ID'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT id, status, appointment_number FROM appointments WHERE id = :id");
    $stmt->execute([':id' => $appointmentId]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        sendJSON(['success' => false, 'message' => 'Appointment not found'], 404);
    }

    if ($appointment['status'] === 'completed' || $appointment['status'] === 'cancelled') {
        sendJSON(['success' => false, 'message' => 'Cannot cancel this appointment'], 400);
    }

    $db->prepare("UPDATE appointments SET status = 'cancelled', cancelled_at = NOW() WHERE id = :id")->execute([':id' => $appointmentId]);

    logActivity($db, $_SESSION['user_id'], $_SESSION['username'], 'admin', 'UPDATE', 'appointments', $appointmentId, "Admin cancelled appointment {$appointment['appointment_number']}");

    sendJSON(['success' => true, 'message' => 'Appointment cancelled']);

} catch (Exception $e) {
    error_log("Admin cancel appointment error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 4: Write get-all-patients.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT p.*, u.email, u.status as account_status, u.created_at as registered_at, (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id) as total_appointments FROM patients p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC");
    $stmt->execute();
    $patients = $stmt->fetchAll();

    sendJSON(['success' => true, 'patients' => $patients]);

} catch (Exception $e) {
    error_log("Get all patients error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 5: Write update-patient-status.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$patientId = intval($data['patient_id'] ?? 0);
$status = sanitizeInput($data['status'] ?? '');

if ($patientId <= 0 || !in_array($status, ['active', 'inactive'])) {
    sendJSON(['success' => false, 'message' => 'Invalid patient ID or status'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT user_id FROM patients WHERE id = :id");
    $stmt->execute([':id' => $patientId]);
    $patient = $stmt->fetch();

    if (!$patient) {
        sendJSON(['success' => false, 'message' => 'Patient not found'], 404);
    }

    $db->prepare("UPDATE users SET status = :status WHERE id = :id")->execute([':status' => $status, ':id' => $patient['user_id']]);

    logActivity($db, $_SESSION['user_id'], $_SESSION['username'], 'admin', 'UPDATE', 'users', $patient['user_id'], "Admin set patient status to {$status}");

    sendJSON(['success' => true, 'message' => "Patient account {$status}"]);

} catch (Exception $e) {
    error_log("Update patient status error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 6: Write get-doctor-schedule.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT d.id, d.full_name, d.specialization FROM doctors d WHERE d.status = 'active' LIMIT 1");
    $stmt->execute();
    $doctor = $stmt->fetch();

    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'No doctor found'], 404);
    }

    $schedStmt = $db->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = :id ORDER BY day_of_week");
    $schedStmt->execute([':id' => $doctor['id']]);
    $schedules = $schedStmt->fetchAll();

    sendJSON(['success' => true, 'doctor' => $doctor, 'schedules' => $schedules]);

} catch (Exception $e) {
    error_log("Get doctor schedule error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 7: Write update-doctor-schedule.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$schedules = $data['schedules'] ?? [];

if (empty($schedules)) {
    sendJSON(['success' => false, 'message' => 'Schedule data is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get the single doctor
    $doctorStmt = $db->prepare("SELECT id FROM doctors WHERE status = 'active' LIMIT 1");
    $doctorStmt->execute();
    $doctor = $doctorStmt->fetch();

    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'No doctor found'], 404);
    }

    $db->beginTransaction();

    // Delete existing schedules
    $db->prepare("DELETE FROM doctor_schedules WHERE doctor_id = :id")->execute([':id' => $doctor['id']]);

    // Insert new schedules
    $stmt = $db->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, max_patients, is_active) VALUES (:doctor_id, :day, :start, :end, :duration, :max, :active)");

    foreach ($schedules as $sched) {
        if (!isset($sched['day_of_week'])) continue;
        $stmt->execute([
            ':doctor_id' => $doctor['id'],
            ':day' => intval($sched['day_of_week']),
            ':start' => $sched['start_time'] ?? '08:00:00',
            ':end' => $sched['end_time'] ?? '17:00:00',
            ':duration' => intval($sched['slot_duration'] ?? 30),
            ':max' => intval($sched['max_patients'] ?? 20),
            ':active' => isset($sched['is_active']) ? intval($sched['is_active']) : 1
        ]);
    }

    $db->commit();

    logActivity($db, $_SESSION['user_id'], $_SESSION['username'], 'admin', 'UPDATE', 'doctor_schedules', $doctor['id'], 'Admin updated doctor schedule');

    sendJSON(['success' => true, 'message' => 'Schedule updated successfully']);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Update doctor schedule error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 8: Write reports-data.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$period = sanitizeInput($_GET['period'] ?? 'monthly');

try {
    $database = new Database();
    $db = $database->getConnection();

    $report = [];

    // Appointment stats by period
    if ($period === 'daily') {
        $stmt = $db->query("SELECT appointment_date as period, COUNT(*) as total, SUM(status='completed') as completed, SUM(status='cancelled') as cancelled, SUM(status='no_show') as no_show FROM appointments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY appointment_date ORDER BY appointment_date DESC");
    } elseif ($period === 'weekly') {
        $stmt = $db->query("SELECT YEARWEEK(appointment_date, 1) as period, MIN(appointment_date) as week_start, COUNT(*) as total, SUM(status='completed') as completed, SUM(status='cancelled') as cancelled FROM appointments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK) GROUP BY YEARWEEK(appointment_date, 1) ORDER BY period DESC");
    } else {
        $stmt = $db->query("SELECT DATE_FORMAT(appointment_date, '%Y-%m') as period, COUNT(*) as total, SUM(status='completed') as completed, SUM(status='cancelled') as cancelled FROM appointments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(appointment_date, '%Y-%m') ORDER BY period DESC");
    }
    $report['appointment_stats'] = $stmt->fetchAll();

    // Patient demographics
    $genderStmt = $db->query("SELECT gender, COUNT(*) as count FROM patients WHERE gender IS NOT NULL GROUP BY gender");
    $report['gender_distribution'] = $genderStmt->fetchAll();

    // Age distribution
    $ageStmt = $db->query("SELECT CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 'Under 18' WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 30 THEN '18-30' WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 31 AND 50 THEN '31-50' WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) > 50 THEN 'Over 50' ELSE 'Unknown' END as age_group, COUNT(*) as count FROM patients GROUP BY age_group");
    $report['age_distribution'] = $ageStmt->fetchAll();

    // Completion rate
    $totalAppts = $db->query("SELECT COUNT(*) as total FROM appointments WHERE status NOT IN ('cancelled')")->fetch()['total'];
    $completedAppts = $db->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'completed'")->fetch()['total'];
    $report['completion_rate'] = $totalAppts > 0 ? round(($completedAppts / $totalAppts) * 100, 1) : 0;

    sendJSON(['success' => true, 'report' => $report]);

} catch (Exception $e) {
    error_log("Reports data error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 9: Write activity.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$page = max(1, intval($_GET['page'] ?? 1));
$actionFilter = sanitizeInput($_GET['action_type'] ?? '');
$moduleFilter = sanitizeInput($_GET['module'] ?? '');
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

try {
    $database = new Database();
    $db = $database->getConnection();

    $where = "1=1";
    $params = [];

    if (!empty($actionFilter)) {
        $where .= " AND action_type = :action";
        $params[':action'] = $actionFilter;
    }
    if (!empty($moduleFilter)) {
        $where .= " AND module = :module";
        $params[':module'] = $moduleFilter;
    }

    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM activity_logs WHERE $where");
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];

    $stmt = $db->prepare("SELECT * FROM activity_logs WHERE $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    sendJSON([
        'success' => true,
        'logs' => $logs,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_records' => (int)$total
        ]
    ]);

} catch (Exception $e) {
    error_log("Activity logs error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 10: Write get-doctor-profile.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT d.*, u.email, u.last_login FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.status = 'active' LIMIT 1");
    $stmt->execute();
    $doctor = $stmt->fetch();

    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'No doctor found'], 404);
    }

    sendJSON(['success' => true, 'doctor' => $doctor]);

} catch (Exception $e) {
    error_log("Get doctor profile error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 11: Delete unused admin API files**

```bash
rm -f api/admin/get-all-doctors.php api/admin/get-archived-doctors.php api/admin/get-archived-patients.php
rm -f api/admin/restore-doctor.php api/admin/restore-patient.php api/admin/archive-doctor.php api/admin/archive-patient.php
rm -f api/admin/users.php api/admin/users-simple.php api/admin/recent-appointments.php api/admin/confirm-appointment.php
rm -f api/admin/get-settings.php api/admin/save-settings.php api/admin/detect-ip.php api/admin/generate-report.php
rm -f api/admin/get-patients.php
```

- [ ] **Step 12: Commit**

```bash
git add api/admin/
git commit -m "feat: admin API endpoints — stats, appointments, patients, schedule, reports, activity logs"
```

---

### Task 8: Appointment QR API Endpoints

**Files:**
- Rewrite: `api/appointments/generate-qr.php`
- Rewrite: `api/appointments/checkin.php`

- [ ] **Step 1: Write generate-qr.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/QRCodeGenerator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$appointmentId = intval($data['appointment_id'] ?? 0);

if ($appointmentId <= 0) {
    sendJSON(['success' => false, 'message' => 'Invalid appointment ID'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verify appointment exists and belongs to requesting user (patient) or is accessible (doctor/admin)
    $stmt = $db->prepare("SELECT a.id, a.patient_id, a.status FROM appointments a WHERE a.id = :id");
    $stmt->execute([':id' => $appointmentId]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        sendJSON(['success' => false, 'message' => 'Appointment not found'], 404);
    }

    if (hasRole('patient') && $appointment['patient_id'] != $_SESSION['profile_id']) {
        sendJSON(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    if ($appointment['status'] === 'cancelled') {
        sendJSON(['success' => false, 'message' => 'Cannot generate QR for cancelled appointment'], 400);
    }

    $qrGenerator = new QRCodeGenerator($db);
    $qrData = $qrGenerator->generateQRCode($appointmentId);

    sendJSON([
        'success' => true,
        'qr_image' => $qrData['qr_image'],
        'token_hash' => $qrData['token_hash'],
        'expires_at' => $qrData['expires_at']
    ]);

} catch (Exception $e) {
    error_log("Generate QR error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 2: Write checkin.php**

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/QRCodeGenerator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn()) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$tokenHash = sanitizeInput($data['token_hash'] ?? '');

if (empty($tokenHash)) {
    sendJSON(['success' => false, 'message' => 'QR token is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $qrGenerator = new QRCodeGenerator($db);
    $result = $qrGenerator->validateQRCode($tokenHash, $_SESSION['user_id']);

    if (!$result['valid']) {
        sendJSON(['success' => false, 'message' => $result['message']], 400);
    }

    // Update appointment status
    $db->prepare("UPDATE appointments SET status = 'checked_in', checked_in_at = NOW() WHERE id = :id")
       ->execute([':id' => $result['appointment_id']]);

    // Get patient info
    $stmt = $db->prepare("SELECT p.full_name, p.date_of_birth, p.gender, p.blood_group, p.allergies, a.appointment_number, a.appointment_date, a.appointment_time, a.reason_for_visit FROM patients p JOIN appointments a ON p.id = a.patient_id WHERE a.id = :id");
    $stmt->execute([':id' => $result['appointment_id']]);
    $info = $stmt->fetch();

    logActivity($db, $_SESSION['user_id'], $_SESSION['username'] ?? '', $_SESSION['role'] ?? '', 'CHECKIN', 'appointments', $result['appointment_id'], "Patient checked in via QR — {$info['appointment_number']}");

    sendJSON([
        'success' => true,
        'message' => 'Check-in successful',
        'appointment' => $info
    ]);

} catch (Exception $e) {
    error_log("Check-in error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error'], 500);
}
```

- [ ] **Step 3: Delete unused appointment API files**

```bash
rm -f api/appointments/create.php api/appointments/list.php api/appointments/quick-checkin.php
rm -f api/appointments/confirm.php api/appointments/cancel.php api/appointments/complete.php
```

- [ ] **Step 4: Commit**

```bash
git add api/appointments/
git commit -m "feat: appointment QR API — generate and check-in endpoints"
```

---

### Task 9: Landing Page (index.html)

**Files:**
- Rewrite: `index.html`

- [ ] **Step 1: Write the landing page**

Write `index.html` — a clean, professional landing page for the Internal Medicine clinic with:
- Hero section with clinic name "MediTrack — Internal Medicine Clinic"
- Brief description
- Login and Register CTA buttons
- "How It Works" section showing the 4-step flow from the diagram: Scan QR → Book Appointment → Visit Clinic → Get Records
- Static QR code section that links to `pages/qr-booking.html`
- Footer with clinic info
- Uses Tailwind CSS CDN, Font Awesome CDN
- Mobile-responsive
- Medical blue/green color theme

The landing page should be approximately 200-300 lines of clean HTML.

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediTrack — Internal Medicine Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg { background: linear-gradient(135deg, #0f766e 0%, #0284c7 100%); }
        .card-hover { transition: transform 0.2s; }
        .card-hover:hover { transform: translateY(-4px); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- Navbar -->
    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <i class="fas fa-heartbeat text-teal-600 text-2xl"></i>
                <span class="text-xl font-bold text-gray-800">MediTrack</span>
            </div>
            <div class="flex gap-3">
                <a href="pages/login.html" class="px-4 py-2 text-teal-600 font-medium hover:bg-teal-50 rounded-lg transition">Log In</a>
                <a href="pages/register.html" class="px-4 py-2 bg-teal-600 text-white font-medium rounded-lg hover:bg-teal-700 transition">Register</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="gradient-bg text-white py-20 px-4">
        <div class="max-w-4xl mx-auto text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">Internal Medicine Clinic</h1>
            <p class="text-lg md:text-xl text-teal-100 mb-8 max-w-2xl mx-auto">Book your appointment easily, check in with QR code, and access your medical records anytime.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="pages/register.html" class="px-8 py-3 bg-white text-teal-700 font-semibold rounded-lg hover:bg-gray-100 transition text-lg">
                    <i class="fas fa-user-plus mr-2"></i>Register Now
                </a>
                <a href="pages/login.html" class="px-8 py-3 border-2 border-white text-white font-semibold rounded-lg hover:bg-white/10 transition text-lg">
                    <i class="fas fa-sign-in-alt mr-2"></i>Log In
                </a>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="py-16 px-4">
        <div class="max-w-6xl mx-auto">
            <h2 class="text-3xl font-bold text-center text-gray-800 mb-12">How It Works</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="text-center card-hover">
                    <div class="w-16 h-16 bg-teal-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-qrcode text-teal-600 text-2xl"></i>
                    </div>
                    <div class="text-sm font-bold text-teal-600 mb-1">Step 1</div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Scan QR Code</h3>
                    <p class="text-gray-500 text-sm">Scan our clinic QR code to access the booking system</p>
                </div>
                <div class="text-center card-hover">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-calendar-check text-blue-600 text-2xl"></i>
                    </div>
                    <div class="text-sm font-bold text-blue-600 mb-1">Step 2</div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Book Appointment</h3>
                    <p class="text-gray-500 text-sm">Select your preferred date and time slot</p>
                </div>
                <div class="text-center card-hover">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-hospital text-green-600 text-2xl"></i>
                    </div>
                    <div class="text-sm font-bold text-green-600 mb-1">Step 3</div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Visit Clinic</h3>
                    <p class="text-gray-500 text-sm">Show your QR code to the doctor for quick check-in</p>
                </div>
                <div class="text-center card-hover">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-file-medical text-purple-600 text-2xl"></i>
                    </div>
                    <div class="text-sm font-bold text-purple-600 mb-1">Step 4</div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Get Records</h3>
                    <p class="text-gray-500 text-sm">View and print your medical records, prescriptions, and lab results</p>
                </div>
            </div>
        </div>
    </section>

    <!-- QR Code Section -->
    <section class="bg-white py-16 px-4">
        <div class="max-w-md mx-auto text-center">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Scan to Book an Appointment</h2>
            <p class="text-gray-500 mb-6">Use your phone camera to scan this QR code and start booking</p>
            <div class="bg-gray-50 p-8 rounded-2xl inline-block">
                <img id="clinicQR" src="" alt="Clinic QR Code" class="w-48 h-48 mx-auto">
            </div>
            <p class="text-sm text-gray-400 mt-4">Or visit: <a href="pages/qr-booking.html" class="text-teal-600 underline">Book Online</a></p>
        </div>
    </section>

    <!-- Features -->
    <section class="py-16 px-4 bg-gray-50">
        <div class="max-w-6xl mx-auto">
            <h2 class="text-3xl font-bold text-center text-gray-800 mb-12">Why MediTrack?</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white p-6 rounded-xl shadow-sm card-hover">
                    <i class="fas fa-bolt text-teal-600 text-3xl mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Fast Check-In</h3>
                    <p class="text-gray-500">No more long queues. Check in instantly with your QR code.</p>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm card-hover">
                    <i class="fas fa-lock text-teal-600 text-3xl mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Secure Records</h3>
                    <p class="text-gray-500">Your medical records are stored securely and accessible only to you.</p>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm card-hover">
                    <i class="fas fa-print text-teal-600 text-3xl mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Print Anytime</h3>
                    <p class="text-gray-500">Download and print your medical files, prescriptions, and lab results.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-gray-300 py-8 px-4">
        <div class="max-w-6xl mx-auto text-center">
            <div class="flex items-center justify-center gap-2 mb-4">
                <i class="fas fa-heartbeat text-teal-400 text-xl"></i>
                <span class="text-lg font-bold text-white">MediTrack</span>
            </div>
            <p class="text-sm">Internal Medicine Clinic</p>
            <p class="text-sm mt-1">Open Monday — Friday, 8:00 AM — 5:00 PM</p>
            <p class="text-xs text-gray-500 mt-4">&copy; 2026 MediTrack. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Generate static clinic QR code
        const bookingUrl = window.location.origin + '/meditrack/pages/qr-booking.html';
        const qrImg = document.getElementById('clinicQR');
        qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(bookingUrl)}`;
    </script>
</body>
</html>
```

- [ ] **Step 2: Commit**

```bash
git add index.html
git commit -m "feat: landing page with clinic info, how-it-works, and QR booking entry"
```

---

### Task 10: Auth Pages (Login, Register, Password Reset)

**Files:**
- Rewrite: `pages/login.html`
- Rewrite: `pages/register.html`
- Rewrite: `pages/forgot-password.html`
- Rewrite: `pages/reset-password.html`
- Create: `js/auth.js`

- [ ] **Step 1: Write login.html**

A clean login page with email/password form, links to register and forgot password. On success, redirects to role-appropriate dashboard. Uses Tailwind, SweetAlert2. Approx 100 lines HTML + inline or linked JS.

Key elements:
- Form: email input, password input, "Log In" button
- Links: "Don't have an account? Register" and "Forgot password?"
- On submit: POST to `/meditrack/api/auth/login.php`
- On success: store user data in sessionStorage, redirect based on role:
  - patient → `patient-dashboard.html`
  - doctor → `doctor-dashboard.html`
  - admin → `admin-dashboard.html`

- [ ] **Step 2: Write register.html**

Patient registration form with all fields from spec. On success, show SweetAlert and redirect to login. Approx 200 lines.

Key elements:
- Fields: full name, email, username, password, confirm password, DOB, gender (radio), contact number, address, region, city, barangay, blood group (select), allergies (textarea), emergency contact name, emergency contact number
- Validation: required fields, password match, email format
- On submit: POST to `/meditrack/api/auth/register.php`

- [ ] **Step 3: Write forgot-password.html**

Simple form: enter email, submit sends OTP request. Shows OTP input field after submission. Approx 80 lines.

- [ ] **Step 4: Write reset-password.html**

Form: email (from URL param or previous step), OTP code, new password, confirm password. On submit: POST to reset-password API. Approx 80 lines.

- [ ] **Step 5: Write js/auth.js**

Shared auth utility functions used across all pages:

```javascript
// Auth utilities
const API_BASE = '/meditrack/api';

function checkAuth() {
    const user = JSON.parse(sessionStorage.getItem('user') || 'null');
    if (!user) {
        window.location.href = '/meditrack/pages/login.html';
        return null;
    }
    return user;
}

function logout() {
    fetch(`${API_BASE}/auth/logout.php`, { method: 'POST' })
        .then(() => {
            sessionStorage.clear();
            window.location.href = '/meditrack/pages/login.html';
        });
}

function getUser() {
    return JSON.parse(sessionStorage.getItem('user') || 'null');
}

async function apiRequest(url, options = {}) {
    const defaults = {
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include'
    };
    const response = await fetch(`${API_BASE}${url}`, { ...defaults, ...options });
    const data = await response.json();
    if (response.status === 401) {
        sessionStorage.clear();
        window.location.href = '/meditrack/pages/login.html';
        return null;
    }
    return data;
}
```

- [ ] **Step 6: Commit**

```bash
git add pages/login.html pages/register.html pages/forgot-password.html pages/reset-password.html js/auth.js
git commit -m "feat: auth pages — login, register, forgot/reset password with shared auth.js"
```

---

### Task 11: Patient Dashboard

**Files:**
- Rewrite: `pages/patient-dashboard.html`
- Create: `js/patient-dashboard.js`

- [ ] **Step 1: Write patient-dashboard.html**

HTML shell with tab navigation (Book Appointment, My Appointments, Medical Records, Profile) and container divs for each tab's content. Includes Tailwind, SweetAlert2, Font Awesome CDN links. Links to `js/auth.js` and `js/patient-dashboard.js`. Approx 200-300 lines.

Key layout:
- Sidebar nav (desktop) + bottom nav (mobile) with 4 tabs
- Header with user name and logout button
- Content area that switches based on active tab
- QR Code modal (hidden by default, shown when clicking "View QR" on an appointment)

- [ ] **Step 2: Write js/patient-dashboard.js**

Complete JavaScript for the patient dashboard. Approx 500-600 lines. Functions:

**Book Appointment tab:**
- `loadBookingTab()` — renders date picker and time slot grid
- On date select: call `GET /patient/get-available-slots?date=YYYY-MM-DD`
- Display doctor info (auto-selected) and available time slots as clickable buttons
- On slot click: highlight selected, show reason textarea and "Book" button
- On book: POST to `/patient/book-appointment`, show SweetAlert with QR code on success

**My Appointments tab:**
- `loadAppointments()` — call `GET /patient/get-appointments`
- Render as cards with: appointment number, date, time, doctor name, status badge
- Status badge colors: scheduled=blue, checked_in=yellow, completed=green, cancelled=red
- "View QR" button on scheduled appointments → opens QR modal
- "Cancel" button on scheduled appointments → confirm dialog → POST cancel
- "View Record" button on completed appointments → switches to Medical Records tab

**Medical Records tab:**
- `loadMedicalRecords()` — call `GET /patient/get-medical-records`
- Render as expandable cards: appointment date, diagnosis preview
- Expanded: full vital signs, diagnosis, prescription, lab tests, notes, follow-up
- "Print" button → opens `print-record.html?id={record_id}` in new tab

**Profile tab:**
- `loadProfile()` — call `GET /patient/get-profile`
- Render editable form with all patient fields
- "Save" button → POST to `/patient/update-profile`
- "Change Password" section with current/new/confirm fields

**QR Modal:**
- Shows QR image from appointment data
- "Download QR" button (saves as PNG)
- "Regenerate QR" button → POST to `/appointments/generate-qr`
- Close button

- [ ] **Step 3: Commit**

```bash
git add pages/patient-dashboard.html js/patient-dashboard.js
git commit -m "feat: patient dashboard — booking, appointments, medical records, profile"
```

---

### Task 12: Doctor Dashboard

**Files:**
- Rewrite: `pages/doctor-dashboard.html`
- Create: `js/doctor-dashboard.js`

- [ ] **Step 1: Write doctor-dashboard.html**

HTML shell with tab navigation (Today's Appointments, All Appointments, Profile) and container divs. Includes Tailwind, SweetAlert2, Font Awesome. Links to `js/auth.js` and `js/doctor-dashboard.js`. Approx 200-250 lines.

Key layout:
- Sidebar (desktop) + bottom nav (mobile) with 3 tabs
- Header with stats cards (today's count, checked in, completed)
- Content area per tab
- Medical record modal (hidden by default)
- QR scanner modal (hidden by default)

- [ ] **Step 2: Write js/doctor-dashboard.js**

Complete JavaScript for the doctor dashboard. Approx 500-600 lines. Functions:

**Stats:**
- `loadStats()` — call `GET /doctor/stats`, populate header cards

**Today's Appointments tab:**
- `loadTodayAppointments()` — call `GET /doctor/get-appointments?date={today}`
- Render as table/cards: time, patient name, status, actions
- "Scan QR" button → opens QR scanner modal (uses device camera via `navigator.mediaDevices.getUserMedia` or manual token input field)
- On QR scan/manual entry: POST to `/doctor/checkin-patient` with token_hash
- On success: show patient info, refresh list
- "Write Record" button on checked_in appointments → opens medical record modal

**All Appointments tab:**
- `loadAllAppointments()` — call `GET /doctor/get-appointments` with optional date/status filters
- Date filter input and status dropdown
- Render as table with all columns

**Medical Record Modal:**
- Form fields: chief complaint, symptoms, vital signs (BP, temp, heart rate, weight, height), diagnosis, prescription, lab tests ordered, notes, follow-up date
- "Save & Complete" button → POST to `/doctor/save-medical-record`
- On success: SweetAlert, close modal, refresh appointments

**QR Scanner Modal:**
- Camera view (if available) using simple QR scanning via camera
- Manual token input as fallback
- "Check In" button → POST to `/doctor/checkin-patient`
- For camera QR scanning: use a lightweight JS QR decoder library (html5-qrcode CDN) or fall back to manual-only

**Profile tab:**
- Display doctor profile (read-only except password change)
- "Change Password" form

- [ ] **Step 3: Commit**

```bash
git add pages/doctor-dashboard.html js/doctor-dashboard.js
git commit -m "feat: doctor dashboard — appointments, QR check-in, medical records, profile"
```

---

### Task 13: Admin Dashboard

**Files:**
- Rewrite: `pages/admin-dashboard.html`
- Create: `js/admin-dashboard.js`

- [ ] **Step 1: Write admin-dashboard.html**

HTML shell with tab navigation (Overview, Appointments, Patients, Doctor Schedule, Reports, Activity Logs). Includes Tailwind, SweetAlert2, Font Awesome. Links to `js/auth.js` and `js/admin-dashboard.js`. Approx 250-300 lines.

Key layout:
- Sidebar (desktop) + bottom nav (mobile) with 6 tabs
- Header with admin name and logout
- Content area per tab

- [ ] **Step 2: Write js/admin-dashboard.js**

Complete JavaScript for the admin dashboard. Approx 600-700 lines. Functions:

**Overview tab:**
- `loadOverview()` — call `GET /admin/stats`
- Render stat cards: total patients, today/week/month appointments, completed, cancelled

**Appointments tab:**
- `loadAppointments()` — call `GET /admin/appointments` with date/status filters
- Render as table: appointment#, patient, date, time, status, actions
- "Cancel" button → confirm → POST to `/admin/cancel-appointment`
- Pagination controls

**Patients tab:**
- `loadPatients()` — call `GET /admin/get-all-patients`
- Render as table: name, email, contact, blood group, total appointments, status
- "Deactivate"/"Activate" toggle → POST to `/admin/update-patient-status`

**Doctor Schedule tab:**
- `loadSchedule()` — call `GET /admin/get-doctor-schedule`
- Show doctor info card
- Render weekly schedule as editable form: checkbox per day, start time, end time, slot duration, max patients
- "Save Schedule" button → POST to `/admin/update-doctor-schedule`

**Reports tab:**
- `loadReports()` — call `GET /admin/reports-data` with period param
- Period selector: daily/weekly/monthly
- Render tables/simple charts for: appointment stats, gender distribution, age distribution, completion rate
- "Print Report" button → `window.print()`

**Activity Logs tab:**
- `loadActivityLogs()` — call `GET /admin/activity` with filters
- Filter dropdowns: action type, module
- Render as table: timestamp, user, role, action, module, description
- Pagination controls

- [ ] **Step 3: Commit**

```bash
git add pages/admin-dashboard.html js/admin-dashboard.js
git commit -m "feat: admin dashboard — overview, appointments, patients, schedule, reports, logs"
```

---

### Task 14: QR Booking Entry Page and Print Record Page

**Files:**
- Create: `pages/qr-booking.html`
- Rewrite: `pages/print-record.html`

- [ ] **Step 1: Write qr-booking.html**

Simple landing page that opens when someone scans the static clinic QR code. Approx 60 lines.

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment — MediTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-lg p-8 text-center">
        <div class="w-16 h-16 bg-teal-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-heartbeat text-teal-600 text-3xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-gray-800 mb-2">MediTrack</h1>
        <h2 class="text-lg text-teal-600 font-medium mb-4">Internal Medicine Clinic</h2>
        <p class="text-gray-500 mb-8">Book your appointment with our Internal Medicine specialist. Quick, easy, and secure.</p>
        <div class="space-y-3">
            <a href="login.html" class="block w-full py-3 bg-teal-600 text-white font-semibold rounded-lg hover:bg-teal-700 transition">
                <i class="fas fa-sign-in-alt mr-2"></i>Log In to Book
            </a>
            <a href="register.html" class="block w-full py-3 border-2 border-teal-600 text-teal-600 font-semibold rounded-lg hover:bg-teal-50 transition">
                <i class="fas fa-user-plus mr-2"></i>Create Account
            </a>
        </div>
        <p class="text-xs text-gray-400 mt-6">Already have an appointment? Log in to view your QR code.</p>
    </div>
    <script>
        // If already logged in, redirect to dashboard
        const user = JSON.parse(sessionStorage.getItem('user') || 'null');
        if (user && user.role === 'patient') {
            window.location.href = 'patient-dashboard.html';
        }
    </script>
</body>
</html>
```

- [ ] **Step 2: Write print-record.html**

Print-optimized page that loads a medical record by ID from URL params. Approx 150 lines.

Key elements:
- Gets `id` (medical record ID) from URL param
- Fetches record data from `/patient/get-medical-records` (filters client-side by ID) or a dedicated endpoint
- Renders in a clean, A4-friendly layout:
  - **Header**: "MediTrack — Internal Medicine Clinic", address, contact
  - **Patient Info**: name, DOB, gender, contact
  - **Appointment Info**: date, time, appointment number, doctor name
  - **Medical Record**: chief complaint, vital signs table, diagnosis, prescription, lab tests, notes
  - **Follow-up**: follow-up date
  - **Footer**: "This is a computer-generated document" + print date
- Print button that calls `window.print()`
- CSS media query `@media print` to hide the button and optimize layout
- Uses Tailwind for screen view, custom print styles

- [ ] **Step 3: Commit**

```bash
git add pages/qr-booking.html pages/print-record.html
git commit -m "feat: QR booking entry page and printable medical record page"
```

---

### Task 15: QR Code Utility Update and Final Cleanup

**Files:**
- Modify: `utils/QRCodeGenerator.php` (minor — remove settings table lookup)

- [ ] **Step 1: Update QRCodeGenerator.php**

In the `getServerBaseUrl()` method, remove the database settings lookup (lines 25-41 of current file) since we no longer have a settings table. Keep only the `APP_URL` constant check and the auto-detect fallback:

```php
private function getServerBaseUrl() {
    if (defined('APP_URL')) {
        return APP_URL;
    }

    $ip = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());
    if ($ip === '::1') $ip = '127.0.0.1';
    $port = $_SERVER['SERVER_PORT'] ?? '80';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $portSuffix = ($port === '80' || $port === '443') ? '' : ':' . $port;

    return "{$scheme}://{$ip}{$portSuffix}/meditrack";
}
```

- [ ] **Step 2: Verify no remaining references to deleted files**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/meditrack
grep -r "departments\|triage\|reception\|EmailService\|EmailSender\|activity-logger\|notifications" --include="*.php" --include="*.html" --include="*.js" api/ pages/ js/ config/ utils/ index.html 2>/dev/null | grep -v "node_modules" | head -20
```

Fix any remaining references found.

- [ ] **Step 3: Test the full flow**

Open browser:
1. Visit `http://localhost/meditrack/` — landing page loads
2. Click Register → fill form → register succeeds
3. Login with new account → patient dashboard loads
4. Book appointment → select date → select time → submit → QR code appears
5. Login as doctor → doctor dashboard → scan/enter QR token → patient checked in
6. Doctor saves medical record → appointment completed
7. Patient views medical record → clicks print → print view opens
8. Login as admin → all tabs work (stats, appointments, patients, schedule, reports, logs)

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "feat: complete MediTrack simplification — single doctor, QR booking, printable records"
```

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | Clean up — delete removed files | ~40 files deleted |
| 2 | Database schema + seed data | `database/schema.sql` |
| 3 | Config simplification | `config/config.php` |
| 4 | Auth API endpoints | 5 files in `api/auth/` |
| 5 | Patient API endpoints | 8 files in `api/patient/` |
| 6 | Doctor API endpoints | 6 files in `api/doctor/` |
| 7 | Admin API endpoints | 10 files in `api/admin/` |
| 8 | Appointment QR API | 2 files in `api/appointments/` |
| 9 | Landing page | `index.html` |
| 10 | Auth pages | 4 HTML + `js/auth.js` |
| 11 | Patient dashboard | HTML + `js/patient-dashboard.js` |
| 12 | Doctor dashboard | HTML + `js/doctor-dashboard.js` |
| 13 | Admin dashboard | HTML + `js/admin-dashboard.js` |
| 14 | QR booking + print record | 2 HTML pages |
| 15 | QR utility update + final cleanup | `utils/QRCodeGenerator.php` |
