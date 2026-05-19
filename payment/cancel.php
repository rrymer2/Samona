<?php
declare(strict_types=1);

require __DIR__ . '/../auth/session.php';
$user = require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Payment Cancelled — Samoma Industries</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/style.css" />
</head>
<body>

  <header class="site-header">
    <div class="container nav">
      <a class="brand" href="../index.html">
        <img class="brand-img brand-img-dark" src="../assets/images/samona-logo-dark.svg" alt="Samona Industries">
        <img class="brand-img brand-img-light" src="../assets/images/samona-logo-light.svg" alt="" aria-hidden="true">
      </a>
      <ul class="nav-links" id="nav-links">
        <li><a href="../index.html">Home</a></li>
        <li><a href="../about.html">About</a></li>
        <li><a href="../services.html">Services</a></li>
        <li><a href="../dashboard.php">Dashboard</a></li>
        <li><a href="../payment.php">Pay Invoice</a></li>
      </ul>
      <div class="nav-cta">
        <a class="btn btn-primary btn-compact" href="../auth/logout.php">Sign out</a>
        <button class="nav-toggle" aria-label="Toggle menu">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </header>

  <section class="page-hero">
    <div class="container">
      <span class="eyebrow">Payment</span>
      <h1>Payment <em>cancelled</em>.</h1>
      <p>No charge was made.</p>
    </div>
  </section>

  <section style="padding: 80px 0;">
    <div class="container" style="max-width: 640px;">
      <p>
        <a class="btn btn-primary" href="../payment.php">Try again</a>
        <a class="btn btn-outline" href="../dashboard.php" style="margin-left: 12px;">Back to dashboard</a>
      </p>
    </div>
  </section>

  <script src="../assets/js/main.js"></script>
</body>
</html>
