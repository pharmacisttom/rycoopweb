-- =========================================================================
-- RYCOOPWEB — Migration 031: Add Source Member ID & Lineage to Board Term Members
-- Compatible with MySQL 8.0+, MySQL 5.7, and MariaDB 10.4+
-- Non-destructive additive migration supporting selective multi-term transfer
-- =========================================================================

-- 1. Add column 'source_member_id' if not present
SET @col_source_member_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'board_term_members'
      AND COLUMN_NAME = 'source_member_id'
);

SET @ddl_add_source_member := IF(
    @col_source_member_exists = 0,
    'ALTER TABLE `board_term_members` ADD COLUMN `source_member_id` BIGINT UNSIGNED NULL AFTER `person_id`',
    'SELECT 1'
);

PREPARE stmt_add_source_member FROM @ddl_add_source_member;
EXECUTE stmt_add_source_member;
DEALLOCATE PREPARE stmt_add_source_member;

-- 2. Add foreign key constraint if not present
SET @fk_source_member_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'board_term_members'
      AND CONSTRAINT_NAME = 'fk_board_term_members_source'
);

SET @ddl_add_fk_source := IF(
    @fk_source_member_exists = 0,
    'ALTER TABLE `board_term_members` ADD CONSTRAINT `fk_board_term_members_source` FOREIGN KEY (`source_member_id`) REFERENCES `board_term_members` (`id`) ON DELETE SET NULL',
    'SELECT 1'
);

PREPARE stmt_add_fk_source FROM @ddl_add_fk_source;
EXECUTE stmt_add_fk_source;
DEALLOCATE PREPARE stmt_add_fk_source;

-- 3. Add unique constraint uq_term_source_member if not present
SET @idx_source_member_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'board_term_members'
      AND INDEX_NAME = 'uq_term_source_member'
);

SET @ddl_add_uq_source := IF(
    @idx_source_member_exists = 0,
    'ALTER TABLE `board_term_members` ADD UNIQUE KEY `uq_term_source_member` (`term_id`, `source_member_id`)',
    'SELECT 1'
);

PREPARE stmt_add_uq_source FROM @ddl_add_uq_source;
EXECUTE stmt_add_uq_source;
DEALLOCATE PREPARE stmt_add_uq_source;
