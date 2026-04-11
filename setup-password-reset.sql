-- ============================================
-- Internal Medicine OPD — Password Reset System Setup
-- Run this in phpMyAdmin to set up the database
-- ============================================

-- Use the meditrack database
USE meditrack;

-- Create password_resets table
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `otp` VARCHAR(6) NOT NULL,
  `reset_token` VARCHAR(64) NOT NULL,
  `verified` TINYINT(1) NOT NULL DEFAULT 0,
  `verified_at` DATETIME NULL DEFAULT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `used_at` DATETIME NULL DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_reset_token` (`reset_token`),
  INDEX `idx_otp` (`otp`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores OTP codes and reset tokens for password recovery';

-- Verify table was created
SELECT 'password_resets table created successfully!' AS Status;

-- Show table structure
DESCRIBE password_resets;

-- Optional: Enable event scheduler for automatic cleanup
SET GLOBAL event_scheduler = ON;

-- Create cleanup event (optional - removes expired OTPs after 24 hours)
DROP EVENT IF EXISTS cleanup_expired_password_resets;

DELIMITER $$

CREATE EVENT cleanup_expired_password_resets
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
  DELETE FROM password_resets 
  WHERE expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
END$$

DELIMITER ;

SELECT 'Setup completed successfully!' AS Status;
