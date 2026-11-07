-- =========================================================================
-- RYCOOPWEB — Migration 030: Board Term Management & Committee Architecture
-- Compatible with MySQL 8.0+, MySQL 5.7, and MariaDB 10.4+
-- Non-destructive additive migration supporting multi-term committee governance
-- =========================================================================

-- 1. Table: board_terms
CREATE TABLE IF NOT EXISTS `board_terms` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `term_number` INT UNSIGNED NOT NULL,
    `term_name` VARCHAR(100) NOT NULL,
    `year_start_be` INT UNSIGNED NULL,
    `year_end_be` INT UNSIGNED NULL,
    `start_date` DATE NULL,
    `end_date` DATE NULL,
    `publish_at` DATETIME NULL,
    `status` ENUM('draft', 'scheduled', 'published', 'archived') NOT NULL DEFAULT 'draft',
    `is_current` TINYINT(1) NOT NULL DEFAULT 0,
    `source_url` VARCHAR(500) NULL,
    `note` VARCHAR(1000) NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `published_by` BIGINT UNSIGNED NULL,
    `published_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_board_terms_term_number` (`term_number`),
    INDEX `idx_board_terms_status_current` (`status`, `is_current`, `publish_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table: board_term_members
CREATE TABLE IF NOT EXISTS `board_term_members` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `term_id` BIGINT UNSIGNED NOT NULL,
    `person_id` INT UNSIGNED NULL,
    `category` ENUM('director', 'advisor', 'auditor') NOT NULL DEFAULT 'director',
    `name` VARCHAR(255) NOT NULL,
    `position` VARCHAR(255) NOT NULL,
    `role_type` VARCHAR(50) NOT NULL DEFAULT 'director',
    `workplace` VARCHAR(255) NULL,
    `department` VARCHAR(255) NULL,
    `image_url` VARCHAR(500) NULL,
    `bio` TEXT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_chairperson` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_term_members_term_cat_order` (`term_id`, `category`, `sort_order`, `id`),
    CONSTRAINT `fk_board_term_members_term` FOREIGN KEY (`term_id`) REFERENCES `board_terms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table: board_term_groups (Subgroups / คณะกรรมการย่อย)
CREATE TABLE IF NOT EXISTS `board_term_groups` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `term_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` VARCHAR(500) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_term_groups_term_order` (`term_id`, `sort_order`),
    CONSTRAINT `fk_board_term_groups_term` FOREIGN KEY (`term_id`) REFERENCES `board_terms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table: board_term_group_members
CREATE TABLE IF NOT EXISTS `board_term_group_members` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group_id` BIGINT UNSIGNED NOT NULL,
    `member_id` BIGINT UNSIGNED NOT NULL,
    `role_in_group` VARCHAR(150) NOT NULL DEFAULT 'กรรมการ',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_group_member` (`group_id`, `member_id`),
    CONSTRAINT `fk_board_term_gm_group` FOREIGN KEY (`group_id`) REFERENCES `board_term_groups` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_board_term_gm_member` FOREIGN KEY (`member_id`) REFERENCES `board_term_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
