-- Update doctors table to properly link with departments
-- This script updates the existing schema to connect doctors with departments

USE meditrack;

-- First, add missing columns to departments table if they don't exist
ALTER TABLE departments 
ADD COLUMN IF NOT EXISTS code VARCHAR(20) UNIQUE AFTER name,
ADD COLUMN IF NOT EXISTS head VARCHAR(100) AFTER description,
ADD COLUMN IF NOT EXISTS contact VARCHAR(20) AFTER head,
ADD COLUMN IF NOT EXISTS location VARCHAR(100) AFTER contact,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Update doctors table to use department_id instead of department string
ALTER TABLE doctors 
DROP FOREIGN KEY IF EXISTS fk_doctors_department;

ALTER TABLE doctors 
MODIFY COLUMN department VARCHAR(100) NULL;

ALTER TABLE doctors 
ADD COLUMN IF NOT EXISTS department_id INT AFTER email,
ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) DEFAULT 0 AFTER status,
ADD CONSTRAINT fk_doctors_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;

-- Add index for better performance
ALTER TABLE doctors 
ADD INDEX IF NOT EXISTS idx_department_id (department_id),
ADD INDEX IF NOT EXISTS idx_is_archived (is_archived);

-- Update existing doctors to link with departments based on department name
UPDATE doctors d
INNER JOIN departments dept ON d.department = dept.name
SET d.department_id = dept.id
WHERE d.department_id IS NULL AND d.department IS NOT NULL;

-- Add archive columns to patients table if not exists
ALTER TABLE patients 
ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) DEFAULT 0 AFTER profile_image_path,
ADD COLUMN IF NOT EXISTS archived_at TIMESTAMP NULL AFTER is_archived,
ADD COLUMN IF NOT EXISTS archived_by INT NULL AFTER archived_at,
ADD INDEX IF NOT EXISTS idx_is_archived (is_archived);

-- Update departments table with codes for existing departments
UPDATE departments SET code = 'GENMED' WHERE name = 'General Medicine' AND code IS NULL;
UPDATE departments SET code = 'CARD' WHERE name = 'Cardiology' AND code IS NULL;
UPDATE departments SET code = 'ORTH' WHERE name = 'Orthopedics' AND code IS NULL;
UPDATE departments SET code = 'PEDI' WHERE name = 'Pediatrics' AND code IS NULL;
UPDATE departments SET code = 'DERM' WHERE name = 'Dermatology' AND code IS NULL;
UPDATE departments SET code = 'ENT' WHERE name = 'ENT' AND code IS NULL;
UPDATE departments SET code = 'GYNE' WHERE name = 'Gynecology' AND code IS NULL;
UPDATE departments SET code = 'NEUR' WHERE name = 'Neurology' AND code IS NULL;
UPDATE departments SET code = 'OPTH' WHERE name = 'Ophthalmology' AND code IS NULL;
UPDATE departments SET code = 'DENT' WHERE name = 'Dentistry' AND code IS NULL;

-- Add more sample departments if needed
INSERT IGNORE INTO departments (name, code, description) VALUES
('Emergency', 'EMER', '24/7 emergency medical services'),
('Radiology', 'RADI', 'Medical imaging and diagnostics'),
('Laboratory', 'LAB', 'Clinical laboratory services'),
('Pharmacy', 'PHAR', 'Pharmaceutical services'),
('Surgery', 'SURG', 'Surgical procedures and operations');

-- Create a view for easy doctor-department queries
CREATE OR REPLACE VIEW doctor_details AS
SELECT 
    d.id,
    d.user_id,
    d.first_name,
    d.middle_name,
    d.last_name,
    d.full_name,
    d.specialization,
    d.qualification,
    d.license_number,
    d.contact_number,
    d.email,
    d.department,
    d.department_id,
    dept.name as department_name,
    dept.code as department_code,
    d.consultation_fee,
    d.experience_years,
    d.profile_image,
    d.bio,
    d.status,
    d.is_archived,
    d.created_at,
    d.updated_at,
    u.username,
    COUNT(DISTINCT a.id) as total_appointments,
    COUNT(DISTINCT CASE WHEN a.appointment_date = CURDATE() THEN a.id END) as today_appointments
FROM doctors d
LEFT JOIN departments dept ON d.department_id = dept.id
LEFT JOIN users u ON d.user_id = u.id
LEFT JOIN appointments a ON d.id = a.doctor_id
GROUP BY d.id;

-- Show results
SELECT 'Database updated successfully!' as message;
SELECT * FROM departments ORDER BY name;
