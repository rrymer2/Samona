-- Samoma Industries — auth schema
-- Run once against your MySQL/MariaDB database (e.g. via phpMyAdmin or `mysql < db/schema.sql`).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  email           VARCHAR(254)   NOT NULL,
  company_name    VARCHAR(120)   NULL DEFAULT NULL,
  phone           VARCHAR(40)    NULL DEFAULT NULL,
  password_hash   VARCHAR(255)   NOT NULL,
  created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
  id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED   NOT NULL,
  token           CHAR(64)       NOT NULL,
  expires_at      DATETIME       NOT NULL,
  last_seen       TIMESTAMP      NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_sessions_token (token),
  KEY idx_sessions_user (user_id),
  KEY idx_sessions_expires (expires_at),
  CONSTRAINT fk_sessions_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- user_id is NULLABLE so failed attempts against unknown emails still get logged.
CREATE TABLE IF NOT EXISTS login_events (
  id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED   NULL,
  ip              VARCHAR(45)    NULL,
  user_agent      VARCHAR(255)   NULL,
  success         TINYINT(1)     NOT NULL,
  attempted_at    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_events_user (user_id),
  KEY idx_login_events_attempted (attempted_at),
  CONSTRAINT fk_login_events_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
