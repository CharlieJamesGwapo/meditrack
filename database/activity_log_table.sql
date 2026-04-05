-- Activity Log Table for Recording All CRUD Operations
-- This table tracks all user actions in the system

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(100) NOT NULL,
    user_role ENUM('admin', 'doctor', 'patient', 'staff') NOT NULL,
    action_type ENUM('CREATE', 'READ', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'VIEW', 'EXPORT', 'IMPORT') NOT NULL,
    module VARCHAR(50) NOT NULL COMMENT 'doctors, patients, appointments, departments, etc.',
    record_id INT NULL COMMENT 'ID of the affected record',
    description TEXT NOT NULL,
    old_data JSON NULL COMMENT 'Previous data before update/delete',
    new_data JSON NULL COMMENT 'New data after create/update',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    status ENUM('success', 'failed', 'warning') DEFAULT 'success',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_module (module),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample data for testing
INSERT INTO activity_logs (user_id, username, user_role, action_type, module, record_id, description, ip_address, status) VALUES
(1, 'admin', 'admin', 'LOGIN', 'auth', NULL, 'Admin user logged in successfully', '127.0.0.1', 'success'),
(1, 'admin', 'admin', 'CREATE', 'doctors', 1, 'Created new doctor: Dr. KC D Alberca', '127.0.0.1', 'success'),
(1, 'admin', 'admin', 'UPDATE', 'doctors', 1, 'Updated doctor profile: Dr. KC D Alberca', '127.0.0.1', 'success'),
(1, 'admin', 'admin', 'VIEW', 'patients', NULL, 'Viewed patients list', '127.0.0.1', 'success'),
(1, 'admin', 'admin', 'CREATE', 'appointments', 1, 'Created new appointment for patient', '127.0.0.1', 'success');
