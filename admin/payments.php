<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/db.php';

$user  = require_login();
$rows  = [];
$error = null;
$grandTotal = 0.0;

try {
    $stmt = db()->prepare(
        "SELECT u.email AS user_email, SUM(p.amount) AS Total, COUNT(*) AS payment_count
           FROM payments p
           LEFT JOIN users u ON p.user_id = u.id
          WHERE p.status = 'paid'
          GROUP BY u.email
          ORDER BY SUM(p.amount) DESC"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        $grandTotal += (int) $r['Total'];
    }
} catch (Throwable $e) {
    error_log('[admin-payments] query failed: ' . $e->getMessage());
    $error = 'Unable to load payments right now. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>All Payments — Samoma Industries Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/style.css" />
</head>
<body>
  <?php include __DIR__ . '/_nav.php'; ?>

  <section class="admin-hero">
    <div class="container">
      <span class="eyebrow">Payment records</span>
      <h1>All <em>payments</em>.</h1>
      <p>Complete payment history across all accounts.</p>
    </div>
  </section>

  <section style="padding: 50px 0 80px;">
    <div class="container">

      <?php if ($error !== null): ?>
        <p class="form-banner"><?= htmlspecialchars($error) ?></p>

      <?php elseif (empty($rows)): ?>
        <p style="color: var(--ink-500);">No payments recorded yet.</p>

      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>User Email</th>
                <th style="text-align: center;">Payments</th>
                <th style="text-align: right;">Total (cents)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['user_email'] ?? '(unlinked)') ?></td>
                  <td style="text-align: center;"><?= (int)$r['payment_count'] ?></td>
                  <td style="text-align: right;"><?= number_format((int)$r['Total']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <th colspan="2">Total</th>
                <th style="text-align: right;"><?= number_format((int)$grandTotal) ?></th>
              </tr>
            </tfoot>
          </table>
        </div>

        <p style="margin-top: 32px;">
          <a class="btn btn-outline" href="index.php">Back to overview</a>
        </p>
      <?php endif; ?>

    </div>
  </section>

  <script src="../assets/js/main.js"></script>
</body>
</html>
