-- TEST ONLY. Apply only with DBA approval and independently verified TEST backup.
-- Invitation issuance must be explicitly enabled via FEATURE_MEMBER_ACTIVATION=true.
CREATE TABLE member_activation_invitations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  issued_by BIGINT UNSIGNED NOT NULL,
  verification_method ENUM('in_person_verified') NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_member_activation_token (token_hash),
  KEY idx_member_activation_user (user_id, used_at, expires_at),
  CONSTRAINT fk_member_activation_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE RESTRICT,
  CONSTRAINT fk_member_activation_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_member_activation_staff FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
