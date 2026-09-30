-- Repair legacy members tables required by the authentication lookup.
SET @schema_name := DATABASE();

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'members' AND COLUMN_NAME = 'user_id') = 0,
  'ALTER TABLE `members` ADD COLUMN `user_id` BIGINT UNSIGNED NULL AFTER `uuid`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'members' AND COLUMN_NAME = 'id_card') = 0,
  'ALTER TABLE `members` ADD COLUMN `id_card` VARCHAR(20) NULL AFTER `member_no`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'members' AND INDEX_NAME = 'idx_members_user') = 0,
  'ALTER TABLE `members` ADD INDEX `idx_members_user` (`user_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'members' AND INDEX_NAME = 'idx_members_id_card') = 0,
  'ALTER TABLE `members` ADD INDEX `idx_members_id_card` (`id_card`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @schema_name AND TABLE_NAME = 'members' AND CONSTRAINT_NAME = 'fk_members_user') = 0,
  'ALTER TABLE `members` ADD CONSTRAINT `fk_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
