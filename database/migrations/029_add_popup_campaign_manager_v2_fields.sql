-- =========================================================================
-- RYCOOPWEB — Migration 029: Add Popup Campaign Manager V2 Fields
-- Compatible with MySQL 8.0+, MySQL 5.7, and MariaDB 10.4+
-- Engine-checked, schema-aware, reversible additive migration
-- =========================================================================

-- 1. Add column 'mascot_image' if not present
SET @col_mascot_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'popups'
      AND COLUMN_NAME = 'mascot_image'
);

SET @ddl_add_mascot := IF(
    @col_mascot_exists = 0,
    'ALTER TABLE `popups` ADD COLUMN `mascot_image` VARCHAR(255) DEFAULT NULL AFTER `desktop_image`',
    'SELECT 1'
);

PREPARE stmt_add_mascot FROM @ddl_add_mascot;
EXECUTE stmt_add_mascot;
DEALLOCATE PREPARE stmt_add_mascot;

-- 2. Add column 'layout_mode' if not present
SET @col_layout_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'popups'
      AND COLUMN_NAME = 'layout_mode'
);

SET @ddl_add_layout := IF(
    @col_layout_exists = 0,
    'ALTER TABLE `popups` ADD COLUMN `layout_mode` ENUM(\'banner_only\', \'mascot_only\', \'banner_and_mascot\') NOT NULL DEFAULT \'banner_only\' AFTER `mascot_image`',
    'SELECT 1'
);

PREPARE stmt_add_layout FROM @ddl_add_layout;
EXECUTE stmt_add_layout;
DEALLOCATE PREPARE stmt_add_layout;

-- 3. Add column 'priority_order' if not present
SET @col_priority_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'popups'
      AND COLUMN_NAME = 'priority_order'
);

SET @ddl_add_priority := IF(
    @col_priority_exists = 0,
    'ALTER TABLE `popups` ADD COLUMN `priority_order` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `priority`',
    'SELECT 1'
);

PREPARE stmt_add_priority FROM @ddl_add_priority;
EXECUTE stmt_add_priority;
DEALLOCATE PREPARE stmt_add_priority;
