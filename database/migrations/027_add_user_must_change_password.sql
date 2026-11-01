-- =========================================================================
-- RYCOOPWEB 3.0 - Add must_change_password column to users table
-- Rayong Public Health Savings and Credit Cooperative Limited
-- Reversible additive schema migration
-- =========================================================================

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'must_change_password'
);

SET @ddl_add_col := IF(
    @col_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`',
    'SELECT 1'
);

PREPARE stmt_add FROM @ddl_add_col;
EXECUTE stmt_add;
DEALLOCATE PREPARE stmt_add;

-- Create an index to quickly filter/query users requiring credential setup
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'idx_users_must_change_pw'
);

SET @ddl_add_idx := IF(
    @idx_exists = 0,
    'ALTER TABLE `users` ADD INDEX `idx_users_must_change_pw` (`must_change_password`, `status`)',
    'SELECT 1'
);

PREPARE stmt_idx FROM @ddl_add_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;
