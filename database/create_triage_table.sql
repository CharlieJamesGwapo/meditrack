-- Create triage_assessments table
CREATE TABLE IF NOT EXISTS triage_assessments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    chief_complaint TEXT NOT NULL,
    blood_pressure VARCHAR(20),
    temperature DECIMAL(4,1),
    heart_rate INT,
    weight DECIMAL(5,2),
    priority_level ENUM('low', 'medium', 'high') DEFAULT 'low',
    notes TEXT,
    recorded_by INT NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_patient_id (patient_id),
    INDEX idx_recorded_by (recorded_by),
    INDEX idx_priority (priority_level),
    INDEX idx_recorded_at (recorded_at)
);

-- Add priority_level column to appointments table if it doesn't exist
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS priority_level ENUM('low', 'medium', 'high') DEFAULT 'low' AFTER status;

-- Create notifications table if it doesn't exist
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    related_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);
