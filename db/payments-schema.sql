-- Samoma Industries — payments audit trail
-- Run once against the same MySQL database that hosts users/sessions/login_events.
-- Depends on the `users` table (FK).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
  id                       INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  user_id                  INT UNSIGNED   NULL,
  stripe_session_id        VARCHAR(255)   NOT NULL,
  stripe_payment_intent_id VARCHAR(255)   NULL,
  amount                   INT UNSIGNED   NOT NULL,           -- minor units (cents for USD)
  currency                 CHAR(3)        NOT NULL DEFAULT 'usd',
  reference                VARCHAR(50)    NULL,                -- invoice / PO ref from the form
  customer_email           VARCHAR(254)   NULL,
  status                   ENUM('pending', 'paid', 'expired', 'failed', 'refunded')
                                          NOT NULL DEFAULT 'pending',
  created_at               TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at                  DATETIME       NULL DEFAULT NULL,
  updated_at               TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                       ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_payments_session (stripe_session_id),
  KEY idx_payments_user    (user_id),
  KEY idx_payments_intent  (stripe_payment_intent_id),
  KEY idx_payments_status  (status),
  KEY idx_payments_created (created_at),
  CONSTRAINT fk_payments_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
