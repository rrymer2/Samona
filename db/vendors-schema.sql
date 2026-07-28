-- Samoma Industries — vendors
-- External counterparties the business collects funds from or pays funds to.
-- Run once. No dependencies. MUST be created before the payments.vendor_id FK
-- (i.e. run this before db/payments-ledger-schema.sql).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS vendors (
  id         INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  name       VARCHAR(120)   NOT NULL,
  email      VARCHAR(254)   NULL DEFAULT NULL,
  phone      VARCHAR(40)    NULL DEFAULT NULL,
  notes      VARCHAR(255)   NULL DEFAULT NULL,
  created_at TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_vendors_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
