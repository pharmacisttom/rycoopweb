-- TEST ONLY; DBA approval, backup and restore rehearsal required before execution.
-- No production DDL, no auto-migration, no account activation.
CREATE TABLE member_privacy_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('notice_acknowledgement','optional_consent') NOT NULL,
  purpose VARCHAR(64) NOT NULL,
  notice_version VARCHAR(32) NOT NULL,
  choice ENUM('accepted','declined','withdrawn') NOT NULL,
  ip_hash CHAR(64) DEFAULT NULL,
  user_agent_hash CHAR(64) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_member_privacy_latest (user_id, event_type, purpose, id),
  CONSTRAINT fk_member_privacy_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
