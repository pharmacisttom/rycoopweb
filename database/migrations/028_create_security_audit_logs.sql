-- =========================================================================
-- RAYONGCOOP DIGITAL PORTAL - Security Audit Logs Table
-- Migration 028: Separated Audit Logging for Member and Staff/Backend Events
-- =========================================================================

CREATE TABLE IF NOT EXISTS `security_audit_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_id` VARCHAR(36) NOT NULL,
    `timestamp_utc` DATETIME NOT NULL,
    `actor_user_id` BIGINT UNSIGNED NULL,
    `actor_type` ENUM('member', 'staff') NOT NULL,
    `role` VARCHAR(64) NOT NULL,
    `event_type` VARCHAR(64) NOT NULL,
    `target_resource` VARCHAR(255) NULL,
    `result` ENUM('success', 'failure', 'denied') NOT NULL,
    `correlation_id` VARCHAR(36) NOT NULL,
    `session_fingerprint` VARCHAR(64) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(500) NULL,
    `metadata` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sec_audit_event_id` (`event_id`),
    INDEX `idx_sec_audit_actor` (`actor_type`, `actor_user_id`),
    INDEX `idx_sec_audit_event_type` (`event_type`),
    INDEX `idx_sec_audit_timestamp` (`timestamp_utc`),
    INDEX `idx_sec_audit_correlation` (`correlation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
