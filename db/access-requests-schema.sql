-- Samoma Industries — access request submissions
-- Run once against the same MySQL database that hosts users/sessions/etc.
-- Depends on the `users` table (FK for reviewer).
--
-- Records inbound "Request access" form submissions from request-access.php.
-- An admin reviews pending rows (currently via phpMyAdmin) and, on approval,
-- creates the user via seed.php / direct INSERT into `users`.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS access_requests (
  id          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120)   NOT NULL,
  email       VARCHAR(254)   NOT NULL,
  company     VARCHAR(120)   NOT NULL,
  phone       VARCHAR(40)    NULL,
  notes       TEXT           NULL,
  status      ENUM('pending', 'approved', 'denied', 'spam')
                            NOT NULL DEFAULT 'pending',
  ip          VARCHAR(45)    NULL,
  user_agent  VARCHAR(255)   NULL,
  created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME       NULL DEFAULT NULL,
  reviewed_by INT UNSIGNED   NULL,
  PRIMARY KEY (id),
  KEY idx_access_requests_status  (status),
  KEY idx_access_requests_email   (email),
  KEY idx_access_requests_created (created_at),
  CONSTRAINT fk_access_requests_reviewer
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
