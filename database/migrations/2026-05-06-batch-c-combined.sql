-- database/migrations/2026-05-06-batch-c-combined.sql
-- Combined Batch C migration — runs C1 + C2 + C3 in one go.
-- Equivalent to running 2026-05-06-batch-c1.sql, c2.sql, c3.sql in order.
-- Idempotent: re-running on a partially-migrated DB does not error.
--
-- Usage in phpMyAdmin:
--   1. Select your database (e.g. stjohnba_meditrack) in the left pane
--   2. Click the SQL tab at the top
--   3. Paste this entire file's contents
--   4. Click Go
--
-- Run BEFORE swapping to the new code if you want zero downtime.
-- Safe to run AFTER deploy too (existing pages keep working).

-- ============================================================================
-- BATCH C1 — staff role, vitals extension, medical certificates
-- ============================================================================


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
-- appointment_id is NULL-able to preserve any pre-existing walk-in rows that have no appointment.
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
  requested_by VARCHAR(150) NULL,
  issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cert_patient_id (patient_id),
  INDEX idx_cert_doctor_id (doctor_id),
  INDEX idx_cert_issued_by (issued_by_user_id),
  UNIQUE KEY uniq_cert_appointment (appointment_id),
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
  FOREIGN KEY (issued_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Idempotent: add requested_by if the cert table already existed before this column was introduced.
SET @col := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'medical_certificates' AND column_name = 'requested_by'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE medical_certificates ADD COLUMN requested_by VARCHAR(150) NULL AFTER notes',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- ============================================================================
-- BATCH C2 — cancellation broadcast audit / dedupe table
-- ============================================================================


CREATE TABLE IF NOT EXISTS cancel_broadcasts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cancelled_appointment_id INT NOT NULL,
  recipient_user_id INT NOT NULL,
  notification_id INT NULL,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_broadcast_recipient (cancelled_appointment_id, recipient_user_id),
  FOREIGN KEY (cancelled_appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL,
  INDEX idx_recipient (recipient_user_id, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================================
-- BATCH C3 — specialist referrals + follow-up auto-booking
-- ============================================================================


-- 1. Referrals table
CREATE TABLE IF NOT EXISTS referrals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT NOT NULL,
  patient_id INT NOT NULL,
  referring_doctor_id INT NOT NULL,
  specialty VARCHAR(100) NOT NULL,
  specialty_other VARCHAR(100) NULL,
  suggested_specialist VARCHAR(150) NULL,
  reason TEXT NOT NULL,
  urgency ENUM('routine','urgent','emergency') NOT NULL DEFAULT 'routine',
  issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_referral_appointment (appointment_id),
  INDEX idx_referral_patient (patient_id),
  INDEX idx_referral_doctor (referring_doctor_id),
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (referring_doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. appointments: parent_appointment_id + is_followup
SET @col := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'appointments' AND column_name = 'parent_appointment_id'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE appointments ADD COLUMN parent_appointment_id INT NULL AFTER doctor_id',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'appointments' AND column_name = 'is_followup'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE appointments ADD COLUMN is_followup TINYINT(1) NOT NULL DEFAULT 0 AFTER parent_appointment_id',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (
  SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema = DATABASE() AND table_name = 'appointments' AND index_name = 'idx_parent_appointment'
);
SET @sql := IF(@idx = 0,
  'ALTER TABLE appointments ADD INDEX idx_parent_appointment (parent_appointment_id)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @fk := (
  SELECT COUNT(*) FROM information_schema.table_constraints
   WHERE table_schema = DATABASE() AND table_name = 'appointments' AND constraint_name = 'fk_parent_appointment'
);
SET @sql := IF(@fk = 0,
  'ALTER TABLE appointments ADD CONSTRAINT fk_parent_appointment FOREIGN KEY (parent_appointment_id) REFERENCES appointments(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
