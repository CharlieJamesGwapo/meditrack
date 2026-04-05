-- Migration: Add barangay column to patients table
-- Date: 2024
-- Description: Adds barangay field support for Bislig City and other Philippine locations

USE meditrack;

-- Check if column exists before adding
SET @dbname = DATABASE();
SET @tablename = 'patients';
SET @columnname = 'barangay';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' VARCHAR(100) COMMENT ''Barangay name'' AFTER address;')
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Verify the column was added
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_COMMENT
FROM 
    INFORMATION_SCHEMA.COLUMNS
WHERE 
    TABLE_SCHEMA = 'meditrack'
    AND TABLE_NAME = 'patients'
    AND COLUMN_NAME = 'barangay';

-- Success message
SELECT 'Migration completed successfully! Barangay column added to patients table.' AS Status;
