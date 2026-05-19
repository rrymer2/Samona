<?php
declare(strict_types=1);

require __DIR__ . '/../auth/session.php';
require __DIR__ . '/../auth/db.php';
require __DIR__ . '/stripe.php';

$user = require_login();

$sessionId = $_GET['session_id'] ?? null;
$verified  = null;
$error     = null;

if (!is_string($sessionId) || $sessionId === '' || !preg_match('/^cs_(test|live)_[A-Za-z0-9]+$/', $sessionId)) {
    $error = 'Missing or invalid payment session.';
} else {
    try {
        $session = stripe_request('GET', '/checkout/sessions/' . $sessionId);
        if (($session['payment_status'] ?? '') === 'paid') {
            $verified = [
                'amount'    => ($session['amount_total'] ?? 0) / 100,
                'currency'  => strtoupper((string)($session['currency'] ?? 'usd')),
                'email'     => (string)($session['customer_details']['email'] ?? ''),
                'reference' => (string)($session['metadata']['reference'] ?? ''),
            ];

            // Synchronous fallback in case the webhook is delayed or
            // not yet configured. Idempotent: re-running yields no change.
            try {
                db()->prepare(
                    'UPDATE payments
                        SET status = "paid",
                            paid_at = COALESCE(paid_at, NOW()),
                            stripe_payment_intent_id = COALESCE(NULLIF(?, ""), stripe_payment_intent_id),
                            customer_email = COALESCE(NULLIF(?, ""), customer_email)
                      WHERE stripe_session_id = ? AND status != "paid"'
                )->execute([
                    (string)($session['payment_intent'] ?? ''),
                    $verified['email'],
                    $sessionId,
                ]);
            } catch (Throwable $e) {
                error_log('[payment] success.php payments UPDATE failed: ' . $e->getMessage());
            }
        } else {
            $error = 'Payment is not yet confirmed. If you just completed checkout, refresh in a moment or contact support.';
        }
    } catch (Throwable $e) {
        error_log('[payment] Stripe session verify failed: ' . $e->getMessage());
        $error = 'Could not verify payment. Please contact support with reference: ' . substr($sessionId, 0, 16) . '…';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Payment Confirmation — Samoma Industries</title>
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
      <?php if ($verified): ?>
        <h1>Thank you. Payment <em>received</em>.</h1>
      <?php else: ?>
        <h1>Payment <em>pending</em>.</h1>
      <?php endif; ?>
    </div>
  </section>

  <section style="padding: 80px 0;">
    <div class="container" style="max-width: 640px;">
      <?php if ($verified): ?>
        <p class="form-banner" style="background: rgba(45,122,45,0.08); color: #2d7a2d; border-color: rgba(45,122,45,0.25);">
          <strong><?= htmlspecialchars($verified['currency'], ENT_QUOTES) ?> <?= number_format($verified['amount'], 2) ?></strong>
          received for reference <strong><?= htmlspecialchars($verified['reference'], ENT_QUOTES) ?></strong>.
          A receipt was emailed to <strong><?= htmlspecialchars($verified['email'], ENT_QUOTES) ?></strong>.
        </p>
      <?php else: ?>
        <p class="form-banner"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
      <?php endif; ?>

      <p style="margin-top: 32px;">
        <a class="btn btn-primary" href="../dashboard.php">Back to dashboard</a>
        <a class="btn btn-outline" href="../payment.php" style="margin-left: 12px;">Make another payment</a>
      </p>
    </div>
  </section>

  <script src="../assets/js/main.js"></script>
</body>
</html>
