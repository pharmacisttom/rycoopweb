-- =========================================================================
-- RYCOOPWEB — Rollback 031: Add Source Member ID & Lineage to Board Term Members
-- Drops constraints and column source_member_id added by Migration 031.
-- Strictly for manual recovery / isolated testing only.
-- MUST NEVER be discovered or run automatically by forward migration runners.
-- =========================================================================

-- 1. Drop foreign key constraint if present
SET @fk_source_member_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'board_term_members'
      AND CONSTRAINT_NAME = 'fk_board_term_members_source'
);

SET @ddl_drop_fk := IF(
    @fk_source_member_exists > 0,
    'ALTER TABLE `board_term_members` DROP FOREIGN KEY `fk_board_term_members_source`',
    'SELECT 1'
);

PREPARE stmt_drop_fk FROM @ddl_drop_fk;
EXECUTE stmt_drop_fk;
DEALLOCATE PREPARE stmt_drop_fk;

-- 2. Drop unique constraint uq_term_source_member if present
SET @idx_source_member_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'board_term_members'
      AND INDEX_NAME = 'uq_term_source_member'
);

SET @ddl_drop_idx := IF(
    @idx_source_member_exists > 0,
    'ALTER TABLE `board_term_members` DROP INDEX `uq_term_source_member`',
    'SELECT 1'
);

PREPARE stmt_drop_idx FROM @ddl_drop_idx;
EXECUTE stmt_drop_idx;
DEALLOCATE PREPARE stmt_drop_idx;

-- 3. Drop column source_member_id if present
SET @col_source_member_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'board_term_members'
      AND COLUMN_NAME = 'source_member_id'
);

SET @ddl_drop_col := IF(
    @col_source_member_exists > 0,
    'ALTER TABLE `board_term_members` DROP COLUMN `source_member_id`',
    'SELECT 1'
);

PREPARE stmt_drop_col FROM @ddl_drop_col;
EXECUTE stmt_drop_col;
DEALLOCATE PREPARE stmt_drop_col;
