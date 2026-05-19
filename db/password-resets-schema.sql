-- Samoma Industries — password reset tokens
-- Run once against the same MySQL database that hosts the users table.
-- Depends on the `users` table (FK).
--
-- Used by a future "Forgot password?" flow: the user enters their email,
-- we generate a 32-byte random token (bin2hex → 64 hex chars), email
-- them a link like /auth/reset-password.php?token=…, they choose a new
-- password, we verify the token is unused and not expired, then update
-- users.password_hash.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
  id         INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED   NOT NULL,
  token      CHAR(64)       NOT NULL,                   -- bin2hex(random_bytes(32))
  expires_at DATETIME       NOT NULL,                   -- typically NOW() + 1 hour
  used_at    DATETIME       NULL DEFAULT NULL,          -- single-use; set when the new password is saved
  ip         VARCHAR(45)    NULL,                       -- IP that requested the reset
  created_at TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_password_resets_token (token),
  KEY idx_password_resets_user    (user_id),
  KEY idx_password_resets_expires (expires_at),
  CONSTRAINT fk_password_resets_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
