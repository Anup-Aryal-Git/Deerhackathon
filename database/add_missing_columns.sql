-- Add missing location columns to EXISTING database
-- This script safely adds columns without affecting existing data
-- Run this in phpMyAdmin or MySQL client: http://localhost/phpmyadmin

USE hamrosewa;

-- Check if columns exist before adding (safe for existing database)
SET @sql = '';

-- Add province column if it doesn't exist
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'hamrosewa' 
  AND TABLE_NAME = 'users' 
  AND COLUMN_NAME = 'province';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN province VARCHAR(100) NULL AFTER city;',
    'SELECT "Column province already exists" AS message;');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add district column if it doesn't exist
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'hamrosewa' 
  AND TABLE_NAME = 'users' 
  AND COLUMN_NAME = 'district';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN district VARCHAR(100) NULL AFTER province;',
    'SELECT "Column district already exists" AS message;');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add municipality column if it doesn't exist
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'hamrosewa' 
  AND TABLE_NAME = 'users' 
  AND COLUMN_NAME = 'municipality';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN municipality VARCHAR(100) NULL AFTER district;',
    'SELECT "Column municipality already exists" AS message;');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes (will skip if they already exist)
CREATE INDEX IF NOT EXISTS idx_province ON users(province);
CREATE INDEX IF NOT EXISTS idx_district ON users(district);
CREATE INDEX IF NOT EXISTS idx_municipality ON users(municipality);
CREATE INDEX IF NOT EXISTS idx_blood_group ON users(blood_group);
CREATE INDEX IF NOT EXISTS idx_is_donor ON users(is_donor);

SELECT '✅ Migration completed successfully! All location columns and indexes have been added.' AS status;
