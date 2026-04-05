-- Fix for qr_tokens table - Add missing created_at column
-- Run this if you get "Unknown column 'created_at' in 'order clause'" error

USE meditrack;

-- Check if column exists, if not add it
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'meditrack' 
  AND TABLE_NAME = 'qr_tokens' 
  AND COLUMN_NAME = 'created_at';

-- Add column if it doesn't exist
SET @query = IF(@col_exists = 0,
    'ALTER TABLE qr_tokens ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER issued_at, ADD INDEX idx_created (created_at)',
    'SELECT "Column created_at already exists" AS message');

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify the fix
SELECT 'QR Tokens table structure:' AS info;
DESCRIBE qr_tokens;

SELECT 'Fix completed successfully!' AS status;
