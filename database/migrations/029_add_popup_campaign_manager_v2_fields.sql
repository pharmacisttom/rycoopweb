-- 029_add_popup_campaign_manager_v2_fields.sql
-- Additive only: Adds mascot_image, layout_mode, and priority_order to popups table

ALTER TABLE `popups`
    ADD COLUMN IF NOT EXISTS `mascot_image` VARCHAR(255) DEFAULT NULL AFTER `desktop_image`,
    ADD COLUMN IF NOT EXISTS `layout_mode` ENUM('banner_only', 'mascot_only', 'banner_and_mascot') NOT NULL DEFAULT 'banner_only' AFTER `mascot_image`,
    ADD COLUMN IF NOT EXISTS `priority_order` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `priority`;
