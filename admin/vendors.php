<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/db.php';

$user = require_admin();
$pdo  = db();

$flash = null;

// Pre-fill so the add form survives a validation error.
$in = ['name' => '', 'email' => '', 'phone' => '', 'notes' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add') {
        $in['name']  = trim((string)($_POST['name']  ?? ''));
        $in['email'] = trim((string)($_POST['email'] ?? ''));
        $in['phone'] = trim((string)($_POST['phone'] ?? ''));
        $in['notes'] = trim((string)($_POST['notes'] ?? ''));

        $errors = [];
        if ($in['name'] === '' || mb_strlen($in['name']) > 120) {
            $errors[] = 'Vendor name is required (120 characters or fewer).';
        }
        if ($in['email'] !== '' && (!filter_var($in['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($in['email']) > 254)) {
            $errors[] = 'Enter a valid email or leave it blank.';
        }
        if (mb_strlen($in['phone']) > 40)  { $errors[] = 'Phone is too long.'; }
        if (mb_strlen($in['notes']) > 255) { $errors[] = 'Notes are too long.'; }

        if ($errors) {
            $flash = ['type' => 'err', 'msg' => implode(' ', $errors)];
        } else {
            try {
                $pdo->prepare(
                    'INSERT INTO vendors (name, email, phone, notes) VALUES (?, ?, ?, ?)'
                )->execute([
                    $in['name'],
                    $in['email'] !== '' ? $in['email'] : null,
                    $in['phone'] !== '' ? $in['phone'] : null,
                    $in['notes'] !== '' ? $in['notes'] : null,
                ]);
                $flash = ['type' => 'ok', 'msg' => 'Vendor "' . htmlspecialchars($in['name']) . '" added.'];
                $in = ['name' => '', 'email' => '', 'phone' => '', 'notes' => '']; // reset
            } catch (Throwable $e) {
                if ($e instanceof PDOException && $e->getCode() === '23000') {
                    $flash = ['type' => 'err', 'msg' => 'A vendor with that name already exists.'];
                } else {
                    error_log('[admin/vendors] add failed: ' . $e->getMessage());
                    $flash = ['type' => 'err', 'msg' => 'Could not add the vendor. Please try again.'];
                }
            }
        }
    } elseif ($action === 'delete') {
        $vendorId = (int)($_POST['vendor_id'] ?? 0);
        if ($vendorId > 0) {
            try {
                // Ledger rows keep their history: fk_payments_vendor is ON DELETE SET NULL.
                $pdo->prepare('DELETE FROM vendors WHERE id = ?')->execute([$vendorId]);
                $flash = ['type' => 'ok', 'msg' => "Vendor #{$vendorId} deleted."];
            } catch (Throwable $e) {
                error_log('[admin/vendors] delete failed: ' . $e->getMessage());
                $flash = ['type' => 'err', 'msg' => 'Could not delete the vendor.'];
            }
        }
    }
}

// Vendors with lifetime in/out totals from the ledger.
$rows = $pdo->query(
    "SELECT v.id, v.name, v.email, v.phone, v.created_at,
            COALESCE(SUM(CASE WHEN p.direction = 'in'  THEN p.amount END), 0) AS collected,
            COALESCE(SUM(CASE WHEN p.direction = 'out' THEN p.amount END), 0) AS paid_out
       FROM vendors v
       LEFT JOIN payments p ON p.vendor_id = v.id AND p.status = 'paid'
      GROUP BY v.id
      ORDER BY v.name"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Vendors · Admin · Samoma Industries</title>
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
      <h1>Vendors</h1>
      <p>External parties you collect funds from or pay funds to. Add a vendor here, then use it on the Transactions page.</p>
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
        <h2 style="font-size: 1.15rem; margin: 0 0 18px;">Add a vendor</h2>
        <form method="post" novalidate>
          <input type="hidden" name="action" value="add">
          <div class="form-field">
            <label for="name">Vendor name</label>
            <input class="form-input" id="name" name="name" type="text" maxlength="120" required placeholder="Acme Supplies Inc." value="<?= htmlspecialchars($in['name'], ENT_QUOTES) ?>">
          </div>
          <div class="form-field">
            <label for="email">Email <span style="font-weight:400;color:var(--ink-500);text-transform:none;letter-spacing:0;">(optional)</span></label>
            <input class="form-input" id="email" name="email" type="email" maxlength="254" placeholder="ap@acme.com" value="<?= htmlspecialchars($in['email'], ENT_QUOTES) ?>">
          </div>
          <div class="form-field">
            <label for="phone">Phone <span style="font-weight:400;color:var(--ink-500);text-transform:none;letter-spacing:0;">(optional)</span></label>
            <input class="form-input" id="phone" name="phone" type="tel" maxlength="40" placeholder="+1 (555) 555-0100" value="<?= htmlspecialchars($in['phone'], ENT_QUOTES) ?>">
          </div>
          <div class="form-field">
            <label for="notes">Notes <span style="font-weight:400;color:var(--ink-500);text-transform:none;letter-spacing:0;">(optional)</span></label>
            <input class="form-input" id="notes" name="notes" type="text" maxlength="255" placeholder="What this vendor supplies" value="<?= htmlspecialchars($in['notes'], ENT_QUOTES) ?>">
          </div>
          <button type="submit" class="btn btn-primary auth-submit">Add vendor</button>
        </form>
      </div>

      <h2 style="font-size: 1.15rem; margin: 0 0 16px;">All vendors</h2>
      <?php if (empty($rows)): ?>
        <p style="color: var(--ink-500);">No vendors yet. Add your first one above.</p>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th style="text-align: right;">Collected</th>
                <th style="text-align: right;">Paid out</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td><?= htmlspecialchars($r['name']) ?></td>
                  <td><small><?= $r['email'] ? htmlspecialchars($r['email']) : '—' ?></small></td>
                  <td><small><?= $r['phone'] ? htmlspecialchars($r['phone']) : '—' ?></small></td>
                  <td style="text-align: right;">$<?= number_format((float)$r['collected'], 2) ?></td>
                  <td style="text-align: right;">$<?= number_format((float)$r['paid_out'], 2) ?></td>
                  <td>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="vendor_id" value="<?= (int)$r['id'] ?>">
                      <button class="btn-tiny btn-tiny-deny" type="submit" onclick="return confirm('Delete vendor <?= htmlspecialchars($r['name'], ENT_QUOTES) ?>?\n\nExisting ledger entries are kept but will no longer link to this vendor.');">Delete</button>
                    </form>
                  </td>
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
