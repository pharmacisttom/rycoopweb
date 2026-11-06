-- 029_rollback_popup_campaign_manager_v2_fields.sql
-- Rollback for Migration 029: Removes V2 fields from popups table safely

ALTER TABLE `popups`
    DROP COLUMN IF EXISTS `priority_order`,
    DROP COLUMN IF EXISTS `layout_mode`,
    DROP COLUMN IF EXISTS `mascot_image`;
