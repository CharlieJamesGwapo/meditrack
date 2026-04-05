-- ============================================
-- IMMEDIATE FIX FOR QR CODE ERROR
-- Run this in phpMyAdmin NOW!
-- ============================================

USE meditrack;

-- Fix 1: Add missing created_at column to qr_tokens table
ALTER TABLE qr_tokens 
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER issued_at;

-- Fix 2: Add index for better performance
ALTER TABLE qr_tokens 
ADD INDEX IF NOT EXISTS idx_created (created_at);

-- Fix 3: Update existing records to have created_at = issued_at
UPDATE qr_tokens 
SET created_at = issued_at 
WHERE created_at IS NULL;

-- Verify the fix
SELECT 'QR Tokens table structure after fix:' AS status;
DESCRIBE qr_tokens;

SELECT 'Total QR tokens in database:' AS info, COUNT(*) AS count FROM qr_tokens;

SELECT '✅ FIX COMPLETED SUCCESSFULLY!' AS result;
