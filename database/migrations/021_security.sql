-- 021 Security hardening
--  * users.twofa_failed: consecutive wrong 2FA codes (the account locks after 10; reset by a correct code)
--  * password_reset_tokens: one-time password-reset links emailed to the user (only the SHA-256 of the token is stored)
--  * activity_log.hash_v: signing scheme of each log row (1 = app key, 2 = key derived from it for the log only),
--    so rows written before this update still verify

ALTER TABLE users ADD COLUMN twofa_failed INT UNSIGNED NOT NULL DEFAULT 0 AFTER failed_attempts;

CREATE TABLE password_reset_tokens (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64) NOT NULL,
  created_by  INT UNSIGNED NULL,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_prt_token (token_hash),
  KEY idx_prt_user (user_id),
  CONSTRAINT fk_prt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE activity_log ADD COLUMN hash_v TINYINT UNSIGNED NOT NULL DEFAULT 1;
