<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/auth/db.php';

$user  = require_login();
$rows  = [];
$error = null;
$grandTotal   = 0.0;
$paymentCount = 0;

try {
    // Only paid payments — abandoned/expired/refunded checkouts must not appear
    // here. Queried directly off `payments` rather than vwMyPayments because that
    // view exposes no status column to filter on. NOTE: create-session.php stores
    // the dollar amount in `payments.amount` (not cents), so Total is summed as-is
    // — no /100 division.
    $stmt = db()->prepare(
        "SELECT reference, customer_email, COUNT(*) AS payment_count, SUM(amount) AS Total
           FROM payments
          WHERE customer_email = ? AND status = 'paid'
          GROUP BY reference, customer_email
          ORDER BY reference"
    );
    $stmt->execute([$user['email']]);
    $rows = $stmt->fetchAll();
    // Total is already in dollars (cents / 100) and carries cents as a decimal.
    foreach ($rows as $r) {
        $grandTotal   += (float) $r['Total'];
        $paymentCount += (int) $r['payment_count'];
    }
} catch (Throwable $e) {
    error_log('[my-payments] query failed: ' . $e->getMessage());
    $error = 'Unable to load your payments right now. Please try again later or contact projects@samoma.industries.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Payment History — Samoma Industries</title>
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
      <h1>Payment <em>history</em>.</h1>
      <p>Payments on record for <?= htmlspecialchars($user['email']) ?>.</p>
    </div>
  </section>

  <section style="padding: 60px 0 80px;">
    <div class="container">

      <?php if ($error !== null): ?>
        <p class="form-banner"><?= htmlspecialchars($error) ?></p>

      <?php elseif (empty($rows)): ?>
        <p style="color: var(--ink-500);">No payments are on record for your account yet.</p>
        <p style="margin-top: 24px;"><a class="btn btn-primary" href="payment.php">Make a payment</a></p>

      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Reference</th>
                <th style="text-align: center;">Payments</th>
                <th style="text-align: right;">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= htmlspecialchars((string)$r['reference']) ?></td>
                  <td style="text-align: center;"><?= (int)$r['payment_count'] ?></td>
                  <td style="text-align: right;">$<?= number_format((float)$r['Total'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <th>Total</th>
                <th style="text-align: center;"><?= number_format($paymentCount) ?></th>
                <th style="text-align: right;">$<?= number_format($grandTotal, 2) ?></th>
              </tr>
            </tfoot>
          </table>
        </div>

        <p style="margin-top: 32px;">
          <a class="btn btn-primary" href="payment.php">Make a payment</a>
          <a class="btn btn-outline" href="dashboard.php" style="margin-left: 12px;">Back to dashboard</a>
        </p>
      <?php endif; ?>

    </div>
  </section>

  <script src="assets/js/main.js"></script>
</body>
</html>
