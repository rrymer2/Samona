-- Samoma Industries — payment transaction type
-- Adds a `type` column so the payments table can hold manual deposits and
-- withdrawals alongside Stripe payments. Run once against the existing database.
-- (Not idempotent: if it errors with "Duplicate column", the column already exists.)

SET NAMES utf8mb4;

ALTER TABLE payments
  ADD COLUMN type ENUM('payment', 'deposit', 'withdrawal')
             NOT NULL DEFAULT 'payment' AFTER status,
  ADD KEY idx_payments_type (type);
