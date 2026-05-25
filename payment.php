<?php
declare(strict_types=1);

require __DIR__ . '/auth/session.php';
$user  = require_login();
$error = isset($_GET['error']) ? (string)$_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Pay an Invoice — Samoma Industries</title>
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
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="payment.php" class="active">Pay Invoice</a></li>
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
      <h3>Pay an <em>invoice</em>.</h3>
      <p>Secure payment via Stripe — your card details never touch our servers.</p>
    </div>
  </section>

  <section style="padding: 80px 0;">
    <div class="container" style="max-width: 600px;">
      <form action="payment/create-session.php" method="post" id="payment-form">
        <?php if ($error !== ''): ?>
          <p class="form-banner"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
        <?php endif; ?>

        <div class="form-field">
          <label for="amount">Amount (USD)</label>
          <input class="form-input" id="amount" name="amount" type="number" min="1" max="1000000" step="0.01" required placeholder="0.00">
        </div>

        <div class="form-field">
          <label for="reference">Payment For:</label>
          <input class="form-input" id="reference" name="reference" type="text" maxlength="50" required placeholder="INV-2026-014">
        </div>

        <div class="form-field">
          <label for="email">Receipt email</label>
          <input class="form-input" id="email" name="email" type="email" required value="<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>">
        </div>

        <button type="submit" class="btn btn-primary auth-submit">
          Continue to payment
          <svg class="arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
        </button>

        <p style="font-size: 0.82rem; color: var(--ink-500); margin-top: 24px;">
          You'll be redirected to Stripe's secure checkout to enter card details. We never see or store your card number.
        </p>
      </form>
    </div>
  </section>

  <script src="assets/js/main.js"></script>
</body>
</html>
