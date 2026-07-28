<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/db.php';

$user = require_admin();
$pdo  = db();

$flash = null;

// Pre-fill values so the form survives a validation error.
$in = ['user_id' => 0, 'type' => 'deposit', 'amount' => '', 'reference' => ''];

// === POST: record a manual deposit or withdrawal ===
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $in['user_id']   = (int)   ($_POST['user_id']   ?? 0);
    $in['type']      = (string)($_POST['type']      ?? '');
    $in['amount']    = trim((string)($_POST['amount']    ?? ''));
    $in['reference'] = trim((string)($_POST['reference'] ?? ''));

    $errors = [];

    // Target user must exist.
    $email = null;
    if ($in['user_id'] <= 0) {
        $errors[] = 'Please choose an account.';
    } else {
        $q = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
        $q->execute([$in['user_id']]);
        $email = $q->fetchColumn();
        if ($email === false) {
            $errors[] = 'That account no longer exists.';
        }
    }

    if (!in_array($in['type'], ['deposit', 'withdrawal'], true)) {
        $errors[] = 'Type must be deposit or withdrawal.';
    }

    if (!is_numeric($in['amount']) || (float)$in['amount'] <= 0) {
        $errors[] = 'Enter a positive amount.';
    } elseif ((float)$in['amount'] > 100000000) {
        $errors[] = 'Amount is too large.';
    }

    if ($in['reference'] === '' || mb_strlen($in['reference']) > 50) {
        $errors[] = 'Reference is required and must be 50 characters or fewer.';
    }

    if ($errors) {
        $flash = ['type' => 'err', 'msg' => implode(' ', $errors)];
    } else {
        try {
            // No Stripe session for a manual entry, but stripe_session_id is
            // NOT NULL + UNIQUE — synthesise a unique marker.
            $marker = 'manual_' . bin2hex(random_bytes(16));
            $pdo->prepare(
                "INSERT INTO payments
                   (user_id, stripe_session_id, amount, currency, reference,
                    customer_email, status, type, paid_at)
                 VALUES (?, ?, ?, 'usd', ?, ?, 'paid', ?, NOW())"
            )->execute([
                $in['user_id'],
                $marker,
                (float) $in['amount'],   // stored in dollars, matching create-session.php
                $in['reference'],
                (string) $email,
                $in['type'],
            ]);

            $verb  = $in['type'] === 'deposit' ? 'Deposit' : 'Withdrawal';
            $flash = [
                'type' => 'ok',
                'msg'  => sprintf(
                    '%s of $%s recorded for %s.',
                    $verb,
                    number_format((float) $in['amount'], 2),
                    htmlspecialchars((string) $email)
                ),
            ];
            // Reset the form on success.
            $in = ['user_id' => 0, 'type' => 'deposit', 'amount' => '', 'reference' => ''];
        } catch (Throwable $e) {
            error_log('[admin/transactions] insert failed: ' . $e->getMessage());
            $flash = ['type' => 'err', 'msg' => 'Could not record the transaction. Please try again.'];
        }
    }
}

// Accounts for the dropdown.
$users = $pdo->query('SELECT id, email FROM users ORDER BY email')->fetchAll();

// Recent manual transactions for confirmation.
$recent = $pdo->query(
    "SELECT p.id, p.type, p.amount, p.reference, p.customer_email,
            u.email AS user_email, p.created_at
       FROM payments p
       LEFT JOIN users u ON u.id = p.user_id
      WHERE p.type IN ('deposit', 'withdrawal')
      ORDER BY p.id DESC
      LIMIT 25"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Transactions · Admin · Samoma Industries</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/style.css" />
</head>
<body>
  <?php include __DIR__ . '/_nav.php'; ?>

  <section class="admin-hero">
    <div class="container">
      <span class="eyebrow">Admin</span>
      <h1>Deposits &amp; <em>withdrawals</em></h1>
      <p>Record a manual deposit or withdrawal against a client account. Entries post immediately to the payments ledger.</p>
    </div>
  </section>

  <section style="padding: 40px 0 80px;">
    <div class="container">

      <?php if ($flash): ?>
        <div class="admin-flash <?= $flash['type'] === 'ok' ? 'admin-flash-ok' : 'admin-flash-err' ?>">
          <?= $flash['msg'] // already escaped where it embeds user input ?>
        </div>
      <?php endif; ?>

      <div class="auth-card reveal" style="max-width: 620px; margin-bottom: 48px;">
        <form method="post" novalidate>
          <div class="form-field">
            <label for="user_id">Account</label>
            <select class="form-input" id="user_id" name="user_id" required>
              <option value="">Select an account…</option>
              <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>" <?= (int)$in['user_id'] === (int)$u['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($u['email']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-field">
            <label for="type">Type</label>
            <select class="form-input" id="type" name="type" required>
              <option value="deposit"    <?= $in['type'] === 'deposit'    ? 'selected' : '' ?>>Deposit</option>
              <option value="withdrawal" <?= $in['type'] === 'withdrawal' ? 'selected' : '' ?>>Withdrawal</option>
            </select>
          </div>

          <div class="form-field">
            <label for="amount">Amount (USD)</label>
            <input class="form-input" id="amount" name="amount" type="number" min="0.01" max="100000000" step="0.01" required placeholder="0.00" value="<?= htmlspecialchars((string)$in['amount'], ENT_QUOTES) ?>">
          </div>

          <div class="form-field">
            <label for="reference">Reference / note</label>
            <input class="form-input" id="reference" name="reference" type="text" maxlength="50" required placeholder="e.g. Wire deposit, Refund adjustment" value="<?= htmlspecialchars((string)$in['reference'], ENT_QUOTES) ?>">
          </div>

          <button type="submit" class="btn btn-primary auth-submit">Record transaction</button>
        </form>
      </div>

      <h2 style="font-size: 1.15rem; margin: 0 0 16px;">Recent deposits &amp; withdrawals</h2>
      <?php if (empty($recent)): ?>
        <p style="color: var(--ink-500);">No manual transactions recorded yet.</p>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Account</th>
                <th>Type</th>
                <th>Reference</th>
                <th style="text-align: right;">Amount</th>
                <th>Recorded</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent as $r): ?>
                <?php $isWd = $r['type'] === 'withdrawal'; ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td><?= htmlspecialchars($r['user_email'] ?? $r['customer_email'] ?? '(unlinked)') ?></td>
                  <td>
                    <span class="status-badge <?= $isWd ? 'status-denied' : 'status-approved' ?>">
                      <?= $isWd ? 'Withdrawal' : 'Deposit' ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars((string)$r['reference']) ?></td>
                  <td style="text-align: right;">
                    <?= $isWd ? '−' : '+' ?>$<?= number_format((float)$r['amount'], 2) ?>
                  </td>
                  <td><small><?= htmlspecialchars((string)$r['created_at']) ?></small></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <p style="margin-top: 32px;">
        <a class="btn btn-outline" href="index.php">Back to overview</a>
      </p>

    </div>
  </section>

  <script src="../assets/js/main.js"></script>
</body>
</html>
