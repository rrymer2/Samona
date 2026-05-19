-- Samoma Industries — admin flag on users
-- Run once against the production database.
-- Adds an is_admin column to users so admin pages can gate access.

SET NAMES utf8mb4;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) NOT NULL DEFAULT 0;

-- After running this, set yourself as admin:
--   UPDATE users SET is_admin = 1 WHERE email = 'you@example.com';
