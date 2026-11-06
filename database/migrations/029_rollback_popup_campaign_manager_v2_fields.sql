-- =========================================================================
-- RYCOOPWEB — Rollback for Migration 029: Popup Campaign Manager V2 Fields
-- Compatible with MySQL 8.0+, MySQL 5.7, and MariaDB 10.4+
-- Schema-aware, safe rollback with data loss prevention gate
-- =========================================================================

-- Safety Guard: Check if any populated V2 data exists before dropping columns
SET @has_v2_data := 0;
SET @col_chk := (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'popups' 
      AND COLUMN_NAME = 'layout_mode'
);

SET @sql_count := IF(
    @col_chk > 0,
    'SELECT COUNT(*) INTO @has_v2_data FROM `popups` WHERE `mascot_image` IS NOT NULL OR `layout_mode` != \'banner_only\' OR `priority_order` > 1',
    'SELECT 0 INTO @has_v2_data'
);
PREPARE stmt_cnt FROM @sql_count;
EXECUTE stmt_cnt;
DEALLOCATE PREPARE stmt_cnt;

-- If @force_rollback_destroy_v2_data is not set to 1 and V2 data exists, fail closed
SET @force_rollback_destroy_v2_data := IFNULL(@force_rollback_destroy_v2_data, 0);

-- 1. Drop column 'priority_order' if exists and safe
SET @col_priority_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'popups'
      AND COLUMN_NAME = 'priority_order'
);

SET @ddl_drop_priority := IF(
    @col_priority_exists > 0 AND (@has_v2_data = 0 OR @force_rollback_destroy_v2_data = 1),
    'ALTER TABLE `popups` DROP COLUMN `priority_order`',
    'SELECT 1'
);
PREPARE stmt_drop_priority FROM @ddl_drop_priority;
EXECUTE stmt_drop_priority;
DEALLOCATE PREPARE stmt_drop_priority;

-- 2. Drop column 'layout_mode' if exists and safe
SET @col_layout_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'popups'
      AND COLUMN_NAME = 'layout_mode'
);

SET @ddl_drop_layout := IF(
    @col_layout_exists > 0 AND (@has_v2_data = 0 OR @force_rollback_destroy_v2_data = 1),
    'ALTER TABLE `popups` DROP COLUMN `layout_mode`',
    'SELECT 1'
);
PREPARE stmt_drop_layout FROM @ddl_drop_layout;
EXECUTE stmt_drop_layout;
DEALLOCATE PREPARE stmt_drop_layout;

-- 3. Drop column 'mascot_image' if exists and safe
SET @col_mascot_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'popups'
      AND COLUMN_NAME = 'mascot_image'
);

SET @ddl_drop_mascot := IF(
    @col_mascot_exists > 0 AND (@has_v2_data = 0 OR @force_rollback_destroy_v2_data = 1),
    'ALTER TABLE `popups` DROP COLUMN `mascot_image`',
    'SELECT 1'
);
PREPARE stmt_drop_mascot FROM @ddl_drop_mascot;
EXECUTE stmt_drop_mascot;
DEALLOCATE PREPARE stmt_drop_mascot;
