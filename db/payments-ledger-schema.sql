-- Samoma Industries — two-way ledger upgrade
-- Turns `payments` into a full money ledger:
--   * direction   — 'in' (funds collected) or 'out' (funds paid out)
--   * counterparty — 'user' (portal account) or 'vendor' (external)
--   * vendor_id    — FK to vendors when counterparty = 'vendor'
-- Also normalises `amount` to DECIMAL(12,2) (dollars with cents).
--
-- Run order:
--   1. db/vendors-schema.sql   (creates the vendors table this FK needs)
--   2. this file
-- Not idempotent: a "Duplicate column" error means it was already applied.

SET NAMES utf8mb4;

ALTER TABLE payments
  MODIFY COLUMN amount DECIMAL(12,2) NOT NULL,
  ADD COLUMN counterparty ENUM('user','vendor') NOT NULL DEFAULT 'user' AFTER user_id,
  ADD COLUMN vendor_id    INT UNSIGNED          NULL DEFAULT NULL        AFTER counterparty,
  ADD COLUMN direction    ENUM('in','out')      NOT NULL DEFAULT 'in'    AFTER type,
  ADD KEY idx_payments_counterparty (counterparty),
  ADD KEY idx_payments_vendor (vendor_id),
  ADD KEY idx_payments_direction (direction),
  ADD CONSTRAINT fk_payments_vendor
      FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL;

-- Backfill direction on legacy rows from the existing `type`.
UPDATE payments SET direction = 'out' WHERE type = 'withdrawal';
UPDATE payments SET direction = 'in'  WHERE type IN ('payment', 'deposit');
