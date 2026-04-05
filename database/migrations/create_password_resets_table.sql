-- Create password_resets table for OTP-based password reset functionality
-- This table stores OTP codes and reset tokens for secure password recovery

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

-- Add comment to table
ALTER TABLE `password_resets` 
COMMENT = 'Stores OTP codes and reset tokens for password recovery process';

-- Optional: Create a cleanup event to delete expired password reset requests after 24 hours
-- This helps keep the table clean and improves performance

DELIMITER $$

CREATE EVENT IF NOT EXISTS `cleanup_expired_password_resets`
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
  DELETE FROM password_resets 
  WHERE expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
END$$

DELIMITER ;

-- Enable the event scheduler (if not already enabled)
SET GLOBAL event_scheduler = ON;
