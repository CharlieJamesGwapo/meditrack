-- database/migrations/2026-05-06-batch-c1.sql
-- Batch C1 — staff role, vitals capture extension, medical certificates.
-- Idempotent: re-running on a partially-migrated DB should not error.

-- 1. Extend the users.role ENUM
ALTER TABLE users
  MODIFY COLUMN role ENUM('patient','doctor','admin','staff') NOT NULL;

-- 2. Staff profile table
CREATE TABLE IF NOT EXISTS staff_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNIQUE NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  contact_number VARCHAR(20) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Extend triage_assessments
SET @col := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'triage_assessments' AND column_name = 'appointment_id'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE triage_assessments ADD COLUMN appointment_id INT NULL AFTER patient_id',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'triage_assessments' AND column_name = 'height_cm'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE triage_assessments ADD COLUMN height_cm INT NULL AFTER weight',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'triage_assessments' AND column_name = 'oxygen_saturation'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE triage_assessments ADD COLUMN oxygen_saturation TINYINT UNSIGNED NULL AFTER height_cm',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (
  SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema = DATABASE() AND table_name = 'triage_assessments' AND index_name = 'uniq_triage_appointment'
);
SET @sql := IF(@idx = 0,
  'ALTER TABLE triage_assessments ADD UNIQUE KEY uniq_triage_appointment (appointment_id)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @fk := (
  SELECT COUNT(*) FROM information_schema.table_constraints
   WHERE table_schema = DATABASE() AND table_name = 'triage_assessments' AND constraint_name = 'fk_triage_appointment'
);
SET @sql := IF(@fk = 0,
  'ALTER TABLE triage_assessments ADD CONSTRAINT fk_triage_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4. Medical certificates
CREATE TABLE IF NOT EXISTS medical_certificates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT NOT NULL,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  issued_by_user_id INT NOT NULL,
  diagnosis VARCHAR(500) NOT NULL,
  rest_period_start DATE NOT NULL,
  rest_period_end DATE NOT NULL,
  rest_days INT NOT NULL,
  notes TEXT NULL,
  issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_cert_appointment (appointment_id),
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
  FOREIGN KEY (issued_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
