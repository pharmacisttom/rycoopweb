-- ============================================================================
-- Migration: 022_google_auth_and_mandatory_mfa.sql
-- Description: RYCOOPWEB 2.2 — Google OAuth 2.0 / OIDC Identity Mapping,
--              Staff Invitations, Mandatory MFA, Recovery Codes, and Session Revocation.
-- ============================================================================

-- 1. Table: user_google_identities
-- Binds local user accounts to immutable Google sub claims.
CREATE TABLE IF NOT EXISTS `user_google_identities` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `google_sub` VARCHAR(191) NOT NULL,
    `google_email` VARCHAR(191) NOT NULL,
    `email_verified` TINYINT(1) NOT NULL DEFAULT 1,
    `linked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_google_sub` (`google_sub`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_google_email` (`google_email`),
    CONSTRAINT `fk_user_google_identities_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table: staff_invitations
-- Manages secure, expiring, invitation-based staff onboarding.
CREATE TABLE IF NOT EXISTS `staff_invitations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(191) NOT NULL,
    `name` VARCHAR(191) NOT NULL,
    `department` VARCHAR(191) NULL DEFAULT NULL,
    `intended_role_id` BIGINT UNSIGNED NOT NULL,
    `invited_by` BIGINT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `accepted_at` DATETIME NULL DEFAULT NULL,
    `revoked_at` DATETIME NULL DEFAULT NULL,
    `status` ENUM('pending', 'accepted', 'revoked', 'expired') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_email` (`email`),
    KEY `idx_token_hash` (`token_hash`),
    KEY `idx_status` (`status`),
    KEY `idx_expires_at` (`expires_at`),
    CONSTRAINT `fk_staff_invitations_role_id` FOREIGN KEY (`intended_role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_staff_invitations_invited_by` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table: user_mfa_recovery_codes
-- Stores hashed, single-use recovery codes for emergency access.
CREATE TABLE IF NOT EXISTS `user_mfa_recovery_codes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `code_hash` VARCHAR(255) NOT NULL,
    `used_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_user_used` (`user_id`, `used_at`),
    CONSTRAINT `fk_user_mfa_recovery_codes_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MySQL 8 does not support ADD COLUMN IF NOT EXISTS. Match the existing repair migration pattern.

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='two_factor_confirmed_at')=0,
'ALTER TABLE `users` ADD COLUMN `two_factor_confirmed_at` DATETIME NULL DEFAULT NULL AFTER `two_factor_enabled`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='two_factor_last_used_slice')=0,
'ALTER TABLE `users` ADD COLUMN `two_factor_last_used_slice` BIGINT NULL DEFAULT NULL AFTER `two_factor_confirmed_at`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='session_version')=0,
'ALTER TABLE `users` ADD COLUMN `session_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `two_factor_last_used_slice`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='department')=0,
'ALTER TABLE `users` ADD COLUMN `department` VARCHAR(191) NULL DEFAULT NULL AFTER `name`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_logs' AND COLUMN_NAME='auth_provider')=0,
'ALTER TABLE `login_logs` ADD COLUMN `auth_provider` VARCHAR(50) NOT NULL DEFAULT ''local'' AFTER `status`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_logs' AND COLUMN_NAME='google_sub')=0,
'ALTER TABLE `login_logs` ADD COLUMN `google_sub` VARCHAR(191) NULL DEFAULT NULL AFTER `auth_provider`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
