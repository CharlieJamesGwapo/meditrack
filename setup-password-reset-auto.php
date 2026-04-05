<?php
/**
 * Automated Password Reset System Setup
 * This script automatically creates the password_resets table
 */

require_once 'config/database.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if table exists
    $checkQuery = "SHOW TABLES LIKE 'password_resets'";
    $result = $db->query($checkQuery);
    
    if ($result->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Password reset table already exists',
            'status' => 'ready'
        ]);
        exit;
    }
    
    // Create the table
    $createTableSQL = "
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
    COMMENT='Stores OTP codes and reset tokens for password recovery'
    ";
    
    $db->exec($createTableSQL);
    
    // Verify table was created
    $verifyQuery = "SHOW TABLES LIKE 'password_resets'";
    $verifyResult = $db->query($verifyQuery);
    
    if ($verifyResult->rowCount() > 0) {
        // Try to enable event scheduler for cleanup (optional)
        try {
            $db->exec("SET GLOBAL event_scheduler = ON");
            
            // Drop existing event if exists
            $db->exec("DROP EVENT IF EXISTS cleanup_expired_password_resets");
            
            // Create cleanup event
            $eventSQL = "
            CREATE EVENT cleanup_expired_password_resets
            ON SCHEDULE EVERY 1 HOUR
            DO
            DELETE FROM password_resets 
            WHERE expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ";
            
            $db->exec($eventSQL);
            
            echo json_encode([
                'success' => true,
                'message' => 'Password reset table created successfully with auto-cleanup',
                'status' => 'created',
                'cleanup_enabled' => true
            ]);
        } catch (Exception $e) {
            // Event scheduler might not have permission, but table is created
            echo json_encode([
                'success' => true,
                'message' => 'Password reset table created successfully',
                'status' => 'created',
                'cleanup_enabled' => false,
                'note' => 'Auto-cleanup requires event scheduler permission'
            ]);
        }
    } else {
        throw new Exception('Failed to verify table creation');
    }
    
} catch (PDOException $e) {
    error_log("Database Error in setup: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Setup Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Setup error: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
?>
