<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/auth/db.php';

$user  = require_login();
$rows  = [];
$error = null;
$grandTotal = 0.0;

try {
    $stmt = db()->prepare(
        'SELECT u.email AS user_email, p.reference, SUM(p.amount) AS Total, COUNT(*) AS payment_count
           FROM payments p
           LEFT JOIN users u ON p.user_id = u.id
          GROUP BY u.email, p.reference
          ORDER BY u.email, p.reference'
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        $grandTotal += (int) $r['Total'];
    }
} catch (Throwable $e) {
    error_log('[all-payments] query failed: ' . $e->getMessage());
    $error = 'Unable to load payments right now. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>All Payments — Samoma Industries</title>
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
      <h1>All <em>payments</em>.</h1>
      <p>Complete payment history across all accounts.</p>
    </div>
  </section>

  <section style="padding: 60px 0 80px;">
    <div class="container">

      <?php if ($error !== null): ?>
        <p class="form-banner"><?= htmlspecialchars($error) ?></p>

      <?php elseif (empty($rows)): ?>
        <p style="color: var(--ink-500);">No payments recorded yet.</p>
        <p style="margin-top: 24px;"><a class="btn btn-primary" href="dashboard.php">Back to dashboard</a></p>

      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>User Email</th>
                <th>Reference</th>
                <th style="text-align: center;">Payments</th>
                <th style="text-align: right;">Total (cents)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['user_email'] ?? '(unlinked)') ?></td>
                  <td><?= htmlspecialchars((string)$r['reference']) ?></td>
                  <td style="text-align: center;"><?= (int)$r['payment_count'] ?></td>
                  <td style="text-align: right;"><?= number_format((int)$r['Total']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <th colspan="3">Total</th>
                <th style="text-align: right;"><?= number_format((int)$grandTotal) ?></th>
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
