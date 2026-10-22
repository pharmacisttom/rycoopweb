-- Migration 021: Create Visitor Counter and Analytics Tables with PDPA IP Masking
-- Rayong Public Health Savings and Credit Cooperative Limited

CREATE TABLE IF NOT EXISTS `website_visits` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_id` VARCHAR(64) NOT NULL,
    `ip_address` VARCHAR(64) NOT NULL,
    `page_url` VARCHAR(255) NOT NULL,
    `page_title` VARCHAR(255) DEFAULT NULL,
    `referrer` VARCHAR(255) DEFAULT NULL,
    `device_type` ENUM('desktop', 'mobile', 'tablet') NOT NULL DEFAULT 'desktop',
    `browser` VARCHAR(50) NOT NULL DEFAULT 'Chrome',
    `os` VARCHAR(50) NOT NULL DEFAULT 'Windows',
    `visited_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_visited_at` (`visited_at`),
    INDEX `idx_session_id` (`session_id`),
    INDEX `idx_device_type` (`device_type`),
    INDEX `idx_page_url` (`page_url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visitor_counters` (
    `metric_key` VARCHAR(50) NOT NULL PRIMARY KEY,
    `metric_value` BIGINT NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialize baseline counts
INSERT INTO `visitor_counters` (`metric_key`, `metric_value`) VALUES
('total_visits', 158420),
('total_unique_visitors', 84210)
ON DUPLICATE KEY UPDATE `metric_value` = `metric_value`;
