-- Samoma Industries — profile fields
-- Adds editable profile columns (company name + phone) to the users table.
-- Run once against the same MySQL/MariaDB database that hosts the users table.
-- Safe to skip if these columns already exist.

SET NAMES utf8mb4;

ALTER TABLE users
  ADD COLUMN company_name VARCHAR(120) NULL DEFAULT NULL AFTER email,
  ADD COLUMN phone        VARCHAR(40)  NULL DEFAULT NULL AFTER company_name;
