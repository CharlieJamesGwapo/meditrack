-- database/migrations/2026-05-06-batch-c3.sql
-- Batch C3 — specialist referrals + follow-up auto-booking.
-- Idempotent: re-running on a partially-migrated DB should not error.

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
