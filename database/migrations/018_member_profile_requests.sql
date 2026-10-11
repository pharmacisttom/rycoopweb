-- Additive non-destructive migration for member profile change requests
CREATE TABLE IF NOT EXISTS `member_profile_requests` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `request_no` VARCHAR(30) NOT NULL UNIQUE,
    `member_id` BIGINT UNSIGNED NOT NULL,
    `requested_changes_json` JSON NOT NULL,
    `status` ENUM('submitted', 'in_progress', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'submitted',
    `reviewed_by` BIGINT UNSIGNED NULL,
    `reviewed_at` DATETIME NULL,
    `review_notes` TEXT NULL,
    `history_json` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_mpr_member_status` (`member_id`, `status`),
    CONSTRAINT `fk_mpr_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mpr_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
