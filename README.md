# Samoma Industries

Marketing site + PHP client portal (auth, dashboard, profile, Stripe payments)
for Samoma Industries. Static HTML/CSS/JS front end with a MySQL-backed PHP
back end.

## Local development (XAMPP)

The portal needs **PHP + MySQL**. The static pages alone open in any browser,
but `*.php` pages require a PHP runtime.

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** + **MySQL**.
2. Symlink (or copy) this folder into XAMPP's web root, e.g. `C:\xampp\htdocs\samoma`.
3. In phpMyAdmin (`http://localhost/phpmyadmin`), create a database `samoma`
   and **Import** `db/schema.sql`. Import the other `db/*.sql` files for the
   payments/admin areas as needed.
   - On an **existing** database, run `db/profile-fields-schema.sql` to add the
     `company_name`/`phone` columns. A fresh `schema.sql` import already has them.
4. Copy `auth/config.example.php` → `auth/config.php` and fill in your DB
   credentials. For XAMPP defaults: user `root`, empty password, db `samoma`.
   (For payments, copy `payment/config.example.php` → `payment/config.php` too.)
5. Create a first user via `seed.php`, then delete `seed.php`.
6. Visit `http://localhost/samoma/login.html`.

`config.php` files are gitignored — **real credentials must never go in the
`*.example.php` templates** (those stay as `CHANGE_ME` / `REPLACE_ME`).

## Git hooks (secret guard) — run once per clone

This repo ships a pre-commit hook (`.githooks/pre-commit`) that blocks
committing real secrets (Stripe/AWS keys, private keys, non-placeholder values
in `*config.example.php`). `core.hooksPath` is a **local** git setting and is
not carried by `git clone`, so after cloning, enable it once:

```sh
git config core.hooksPath .githooks
```

Bypass a genuine false positive with `git commit --no-verify`.
