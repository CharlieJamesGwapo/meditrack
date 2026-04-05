-- ================================================
-- QUICK FIX FOR PASSWORD RESET
-- Copy this entire file and paste in phpMyAdmin
-- ================================================

-- Select the meditrack database
USE meditrack;

-- Create the password_resets table
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verify table was created
SELECT 'SUCCESS! Table created. You can now use password reset!' AS Status;
DESCRIBE password_resets;
