<?php
require __DIR__ . '/auth/session.php';
$user = require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Client Portal — Samoma Industries</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
  <header class="site-header">
    <div class="container nav">
      <a class="brand" href="index.html">
        <img class="brand-img brand-img-dark" src="assets/images/samona-logo-dark.svg" alt="Samona Industries">
        <img class="brand-img brand-img-light" src="assets/images/samona-logo-light.svg" alt="" aria-hidden="true">
      </a>
      <ul class="nav-links" id="nav-links">
        <li><a href="index.html">Home</a></li>
        <li><a href="about.html">About</a></li>
        <li><a href="services.html">Services</a></li>
        <li><a href="dashboard.php" class="active">Dashboard</a></li>
        <li><a href="payment.php">Pay Invoice</a></li>
        <?php if (!empty($user['is_admin'])): ?>
          <li><a href="admin/index.php">Admin</a></li>
        <?php endif; ?>
      </ul>
      <div class="nav-cta">
        <a class="btn btn-primary btn-compact" href="auth/logout.php">Sign out</a>
        <button class="nav-toggle" aria-label="Toggle menu">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </header>

  <section class="page-hero">
    <div class="container">
      <span class="eyebrow">Client Portal</span>
      <h3>Welcome back, <em><?= htmlspecialchars($user['email']) ?></em>.</h3>
      <p>You are signed in to the Samoma client workspace.</p>
    </div>
  </section>

  <section style="padding: 80px 0;">
    <div class="container">
      <div class="portal-grid">

        <a class="portal-tile" href="payment.php">
          <span class="portal-tile-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="2" y="5" width="20" height="14" rx="2"/>
              <path d="M2 10h20M6 15h3"/>
            </svg>
          </span>
          <span class="portal-tile-label">Make Payment</span>
        </a>

        <a class="portal-tile" href="my-payments.php">
          <span class="portal-tile-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="12" r="9"/>
              <path d="M12 7v5l3 2"/>
              <path d="M3 12a9 9 0 0 1 2-5.7"/>
            </svg>
          </span>
          <span class="portal-tile-label">Payment history</span>
        </a>

        <a class="portal-tile" href="#">
          <span class="portal-tile-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M21 8H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2z"/>
              <path d="M3 8V6a2 2 0 0 1 2-2h12"/>
              <circle cx="17" cy="14" r="1.5" fill="currentColor"/>
            </svg>
          </span>
          <span class="portal-tile-label">My Balance</span>
        </a>

        <a class="portal-tile" href="#">
          <span class="portal-tile-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M3 20h18"/>
              <rect x="5" y="11" width="3" height="9"/>
              <rect x="10.5" y="6" width="3" height="14"/>
              <rect x="16" y="14" width="3" height="6"/>
            </svg>
          </span>
          <span class="portal-tile-label">Overall Balance</span>
        </a>

        <a class="portal-tile" href="#">
          <span class="portal-tile-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M7 4v16M7 20l-3-3M7 20l3-3"/>
              <path d="M17 20V4M17 4l-3 3M17 4l3 3"/>
            </svg>
          </span>
          <span class="portal-tile-label">Account transactions</span>
        </a>

        <a class="portal-tile" href="#">
          <span class="portal-tile-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </span>
          <span class="portal-tile-label">Review all accounts</span>
        </a>

      </div>

      <p style="margin-top: 48px;">
        <a class="btn btn-outline" href="auth/logout.php">Sign out</a>
      </p>
    </div>
  </section>

  <script src="assets/js/main.js"></script>
</body>
</html>
