<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/db.php';

$user = require_admin();
$pdo  = db();

$flash = null;

// Pre-fill so the form survives a validation error.
$in = ['direction' => 'in', 'counterparty' => 'user', 'user_id' => 0, 'vendor_id' => 0, 'amount' => '', 'reference' => ''];

// === POST: record a ledger entry (collection or payout) ===
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $in['direction']    = (string)($_POST['direction']    ?? '');
    $in['counterparty'] = (string)($_POST['counterparty'] ?? '');
    $in['user_id']      = (int)   ($_POST['user_id']      ?? 0);
    $in['vendor_id']    = (int)   ($_POST['vendor_id']    ?? 0);
    $in['amount']       = trim((string)($_POST['amount']    ?? ''));
    $in['reference']    = trim((string)($_POST['reference'] ?? ''));

    $errors = [];

    if (!in_array($in['direction'], ['in', 'out'], true)) {
        $errors[] = 'Choose whether you are collecting or paying.';
    }

    // Resolve the counterparty (user account OR vendor).
    $userId = null; $vendorId = null; $email = null; $partyLabel = '';
    if ($in['counterparty'] === 'user') {
        if ($in['user_id'] <= 0) {
            $errors[] = 'Please choose a user account.';
        } else {
            $q = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
            $q->execute([$in['user_id']]);
            $email = $q->fetchColumn();
            if ($email === false) {
                $errors[] = 'That user account no longer exists.';
            } else {
                $userId = $in['user_id'];
                $partyLabel = (string)$email;
            }
        }
    } elseif ($in['counterparty'] === 'vendor') {
        if ($in['vendor_id'] <= 0) {
            $errors[] = 'Please choose a vendor.';
        } else {
            $q = $pdo->prepare('SELECT name FROM vendors WHERE id = ? LIMIT 1');
            $q->execute([$in['vendor_id']]);
            $vname = $q->fetchColumn();
            if ($vname === false) {
                $errors[] = 'That vendor no longer exists.';
            } else {
                $vendorId = $in['vendor_id'];
                $partyLabel = (string)$vname;
            }
        }
    } else {
        $errors[] = 'Choose a counterparty type.';
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
            // Keep the legacy `type` populated: in -> deposit, out -> withdrawal.
            $type = $in['direction'] === 'in' ? 'deposit' : 'withdrawal';
            $pdo->prepare(
                "INSERT INTO payments
                   (user_id, counterparty, vendor_id, stripe_session_id, amount, currency,
                    reference, customer_email, status, type, direction, paid_at)
                 VALUES (?, ?, ?, ?, ?, 'usd', ?, ?, 'paid', ?, ?, NOW())"
            )->execute([
                $userId,
                $in['counterparty'],
                $vendorId,
                $marker,
                (float) $in['amount'],
                $in['reference'],
                $email !== false ? $email : null,
                $type,
                $in['direction'],
            ]);

            $flash = [
                'type' => 'ok',
                'msg'  => sprintf(
                    '%s $%s %s %s.',
                    $in['direction'] === 'in' ? 'Collected' : 'Paid',
                    number_format((float) $in['amount'], 2),
                    $in['direction'] === 'in' ? 'from' : 'to',
                    htmlspecialchars($partyLabel)
                ),
            ];
            // Reset the form on success.
            $in = ['direction' => 'in', 'counterparty' => 'user', 'user_id' => 0, 'vendor_id' => 0, 'amount' => '', 'reference' => ''];
        } catch (Throwable $e) {
            error_log('[admin/transactions] insert failed: ' . $e->getMessage());
            $flash = ['type' => 'err', 'msg' => 'Could not record the transaction. Please try again.'];
        }
    }
}

// Pickers.
$users   = $pdo->query('SELECT id, email FROM users ORDER BY email')->fetchAll();
$vendors = $pdo->query('SELECT id, name FROM vendors ORDER BY name')->fetchAll();

// Recent manual ledger entries for confirmation.
$recent = $pdo->query(
    "SELECT p.id, p.direction, p.counterparty, p.amount, p.reference,
            p.customer_email, u.email AS user_email, v.name AS vendor_name, p.created_at
       FROM payments p
       LEFT JOIN users   u ON u.id = p.user_id
       LEFT JOIN vendors v ON v.id = p.vendor_id
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
      <h1>Record a <em>transaction</em></h1>
      <p>Collect funds from — or pay funds to — a user or a vendor. Entries post immediately to the ledger.</p>
    </div>
  </section>

  <section style="padding: 40px 0 80px;">
    <div class="container">

      <?php if ($flash): ?>
        <div class="admin-flash <?= $flash['type'] === 'ok' ? 'admin-flash-ok' : 'admin-flash-err' ?>">
          <?= $flash['msg'] // pre-escaped where it embeds input ?>
        </div>
      <?php endif; ?>

      <div class="auth-card reveal" style="max-width: 620px; margin-bottom: 48px;">
        <form method="post" novalidate id="txn-form">
          <div class="form-field">
            <label for="direction">Direction</label>
            <select class="form-input" id="direction" name="direction" required>
              <option value="in"  <?= $in['direction'] === 'in'  ? 'selected' : '' ?>>Collect funds (money in)</option>
              <option value="out" <?= $in['direction'] === 'out' ? 'selected' : '' ?>>Pay funds (money out)</option>
            </select>
          </div>

          <div class="form-field">
            <label for="counterparty">Counterparty</label>
            <select class="form-input" id="counterparty" name="counterparty" required>
              <option value="user"   <?= $in['counterparty'] === 'user'   ? 'selected' : '' ?>>User account</option>
              <option value="vendor" <?= $in['counterparty'] === 'vendor' ? 'selected' : '' ?>>Vendor</option>
            </select>
          </div>

          <div class="form-field" id="field-user">
            <label for="user_id">User account</label>
            <select class="form-input" id="user_id" name="user_id">
              <option value="">Select a user…</option>
              <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>" <?= (int)$in['user_id'] === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['email']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-field" id="field-vendor">
            <label for="vendor_id">Vendor</label>
            <select class="form-input" id="vendor_id" name="vendor_id">
              <option value="">Select a vendor…</option>
              <?php foreach ($vendors as $v): ?>
                <option value="<?= (int)$v['id'] ?>" <?= (int)$in['vendor_id'] === (int)$v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (empty($vendors)): ?>
              <span class="form-error" style="display:block;">No vendors yet — <a href="vendors.php">add one first</a>.</span>
            <?php endif; ?>
          </div>

          <div class="form-field">
            <label for="amount">Amount (USD)</label>
            <input class="form-input" id="amount" name="amount" type="number" min="0.01" max="100000000" step="0.01" required placeholder="0.00" value="<?= htmlspecialchars((string)$in['amount'], ENT_QUOTES) ?>">
          </div>

          <div class="form-field">
            <label for="reference">Reference / note</label>
            <input class="form-input" id="reference" name="reference" type="text" maxlength="50" required placeholder="e.g. Invoice 1042, Vendor bill" value="<?= htmlspecialchars((string)$in['reference'], ENT_QUOTES) ?>">
          </div>

          <button type="submit" class="btn btn-primary auth-submit">Record transaction</button>
        </form>
      </div>

      <h2 style="font-size: 1.15rem; margin: 0 0 16px;">Recent ledger entries</h2>
      <?php if (empty($recent)): ?>
        <p style="color: var(--ink-500);">No manual transactions recorded yet.</p>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Direction</th>
                <th>Counterparty</th>
                <th>Reference</th>
                <th style="text-align: right;">Amount</th>
                <th>Recorded</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent as $r): ?>
                <?php
                  $isOut = $r['direction'] === 'out';
                  $party = $r['counterparty'] === 'vendor'
                    ? ($r['vendor_name'] ?? '(deleted vendor)')
                    : ($r['user_email'] ?? $r['customer_email'] ?? '(unlinked)');
                ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td>
                    <span class="status-badge <?= $isOut ? 'status-denied' : 'status-approved' ?>">
                      <?= $isOut ? 'Paid out' : 'Collected' ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($party) ?></td>
                  <td><?= htmlspecialchars((string)$r['reference']) ?></td>
                  <td style="text-align: right;"><?= $isOut ? '−' : '+' ?>$<?= number_format((float)$r['amount'], 2) ?></td>
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
  <script>
    // Show the user picker or the vendor picker based on the counterparty select.
    (function () {
      var cp = document.getElementById('counterparty');
      var fu = document.getElementById('field-user');
      var fv = document.getElementById('field-vendor');
      function sync() {
        var vendor = cp.value === 'vendor';
        fu.style.display = vendor ? 'none' : '';
        fv.style.display = vendor ? '' : 'none';
      }
      if (cp && fu && fv) { cp.addEventListener('change', sync); sync(); }
    })();
  </script>
</body>
</html>
