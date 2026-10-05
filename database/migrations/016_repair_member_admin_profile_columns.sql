-- Add missing optional profile columns on existing installations.
-- Do not overwrite names, identifiers, passwords or dividend records.
SET @member_schema := DATABASE();

SET @member_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@member_schema AND TABLE_NAME='members' AND COLUMN_NAME='prefix')=0,
  'ALTER TABLE `members` ADD COLUMN `prefix` VARCHAR(20) NULL',
  'SELECT 1'
);
PREPARE member_stmt FROM @member_ddl; EXECUTE member_stmt; DEALLOCATE PREPARE member_stmt;

SET @member_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@member_schema AND TABLE_NAME='members' AND COLUMN_NAME='department')=0,
  'ALTER TABLE `members` ADD COLUMN `department` VARCHAR(150) NULL',
  'SELECT 1'
);
PREPARE member_stmt FROM @member_ddl; EXECUTE member_stmt; DEALLOCATE PREPARE member_stmt;

SET @member_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@member_schema AND TABLE_NAME='members' AND COLUMN_NAME='phone')=0,
  'ALTER TABLE `members` ADD COLUMN `phone` VARCHAR(20) NULL',
  'SELECT 1'
);
PREPARE member_stmt FROM @member_ddl; EXECUTE member_stmt; DEALLOCATE PREPARE member_stmt;

SET @member_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@member_schema AND TABLE_NAME='members' AND COLUMN_NAME='email')=0,
  'ALTER TABLE `members` ADD COLUMN `email` VARCHAR(190) NULL',
  'SELECT 1'
);
PREPARE member_stmt FROM @member_ddl; EXECUTE member_stmt; DEALLOCATE PREPARE member_stmt;

SET @member_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@member_schema AND TABLE_NAME='members' AND COLUMN_NAME='address')=0,
  'ALTER TABLE `members` ADD COLUMN `address` TEXT NULL',
  'SELECT 1'
);
PREPARE member_stmt FROM @member_ddl; EXECUTE member_stmt; DEALLOCATE PREPARE member_stmt;

SET @member_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@member_schema AND TABLE_NAME='members' AND COLUMN_NAME='updated_at')=0,
  'ALTER TABLE `members` ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP',
  'SELECT 1'
);
PREPARE member_stmt FROM @member_ddl; EXECUTE member_stmt; DEALLOCATE PREPARE member_stmt;
