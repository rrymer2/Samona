-- Samoma Industries — payments / money ledger
-- Run once against the same MySQL database that hosts users/sessions/login_events.
-- Depends on `users` and `vendors` (FKs) — run db/vendors-schema.sql first.
--
-- This is a two-way ledger: each row is money moving IN (collected) or OUT
-- (paid), with a counterparty that is either a portal user or a vendor.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
  id                       INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  user_id                  INT UNSIGNED   NULL,                -- counterparty when it's a portal user
  counterparty             ENUM('user', 'vendor') NOT NULL DEFAULT 'user',
  vendor_id                INT UNSIGNED   NULL,                -- counterparty when it's a vendor
  stripe_session_id        VARCHAR(255)   NOT NULL,
  stripe_payment_intent_id VARCHAR(255)   NULL,
  amount                   DECIMAL(12,2)  NOT NULL,            -- dollars (2 decimal places), always positive
  currency                 CHAR(3)        NOT NULL DEFAULT 'usd',
  reference                VARCHAR(50)    NULL,                -- invoice / PO ref from the form
  customer_email           VARCHAR(254)   NULL,
  status                   ENUM('pending', 'paid', 'expired', 'failed', 'refunded')
                                          NOT NULL DEFAULT 'pending',
  type                     ENUM('payment', 'deposit', 'withdrawal')
                                          NOT NULL DEFAULT 'payment',
  direction                ENUM('in', 'out') NOT NULL DEFAULT 'in',  -- 'in' collected, 'out' paid; drives balance sign
  created_at               TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at                  DATETIME       NULL DEFAULT NULL,
  updated_at               TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                       ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_payments_session (stripe_session_id),
  KEY idx_payments_user         (user_id),
  KEY idx_payments_counterparty (counterparty),
  KEY idx_payments_vendor       (vendor_id),
  KEY idx_payments_intent       (stripe_payment_intent_id),
  KEY idx_payments_status       (status),
  KEY idx_payments_type         (type),
  KEY idx_payments_direction    (direction),
  KEY idx_payments_created      (created_at),
  CONSTRAINT fk_payments_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_payments_vendor
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-event audit log for Stripe webhooks.
-- UNIQUE on stripe_event_id gives free idempotency against Stripe retries —
-- the same event can be received multiple times but only stored once.
CREATE TABLE IF NOT EXISTS payments_events (
  id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  stripe_event_id VARCHAR(255)   NOT NULL,                    -- evt_xxx
  type            VARCHAR(100)   NOT NULL,                    -- e.g. checkout.session.completed
  payload         MEDIUMTEXT     NOT NULL,                    -- raw JSON Stripe sent
  received_at     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at    DATETIME       NULL DEFAULT NULL,           -- set after the handler completes cleanly
  error           TEXT           NULL,                         -- non-null if the handler threw
  PRIMARY KEY (id),
  UNIQUE KEY uniq_payments_events_stripe (stripe_event_id),
  KEY idx_payments_events_type     (type),
  KEY idx_payments_events_received (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
