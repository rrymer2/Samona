<?php
declare(strict_types=1);

require __DIR__ . '/../auth/session.php';
require __DIR__ . '/../auth/db.php';

$user = require_admin();
$pdo  = db();

$flash         = null;        // ['type' => 'ok|err', 'msg' => '...', 'password' => '...', 'email' => '...']
$filterStatus  = $_GET['status'] ?? 'pending';
$allowedStatus = ['pending', 'approved', 'denied', 'spam', 'all'];
if (!in_array($filterStatus, $allowedStatus, true)) {
    $filterStatus = 'pending';
}

// === POST handlers ===
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action    = (string)($_POST['action']     ?? '');
    $requestId = (int)   ($_POST['request_id'] ?? 0);

    if ($requestId <= 0) {
        $flash = ['type' => 'err', 'msg' => 'Invalid request id.'];
    } else {
        $stmt = $pdo->prepare('SELECT * FROM access_requests WHERE id = ? LIMIT 1');
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();

        if (!$req) {
            $flash = ['type' => 'err', 'msg' => 'Request not found.'];
        } elseif ($action === 'approve') {
            if ($req['status'] !== 'pending') {
                $flash = ['type' => 'err', 'msg' => "Request #{$requestId} is already {$req['status']} — cannot approve again."];
            } else {
                try {
                    $pdo->beginTransaction();

                    // Bail if a user with this email already exists
                    $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                    $check->execute([$req['email']]);
                    if ($check->fetch()) {
                        $pdo->rollBack();
                        $flash = ['type' => 'err', 'msg' => 'A user with that email already exists. Mark this request approved manually or deny it.'];
                    } else {
                        $tempPassword = bin2hex(random_bytes(8)); // 16 hex chars
                        $hash         = password_hash($tempPassword, PASSWORD_DEFAULT);

                        $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)')
                            ->execute([$req['email'], $hash]);

                        $pdo->prepare(
                            'UPDATE access_requests
                                SET status = "approved",
                                    reviewed_at = NOW(),
                                    reviewed_by = ?
                              WHERE id = ?'
                        )->execute([(int)$user['id'], $requestId]);

                        $pdo->commit();

                        $flash = [
                            'type'     => 'ok',
                            'msg'      => "Approved request #{$requestId}. User created.",
                            'email'    => $req['email'],
                            'password' => $tempPassword,
                        ];
                    }
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('[admin/approve] failed: ' . $e->getMessage());
                    $flash = ['type' => 'err', 'msg' => 'Approval failed: ' . $e->getMessage()];
                }
            }
        } elseif ($action === 'deny' || $action === 'spam') {
            $newStatus = ($action === 'spam') ? 'spam' : 'denied';
            if (!in_array($req['status'], ['pending'], true)) {
                $flash = ['type' => 'err', 'msg' => "Request #{$requestId} is {$req['status']} — only pending requests can be marked {$newStatus}."];
            } else {
                try {
                    $pdo->prepare(
                        'UPDATE access_requests
                            SET status = ?,
                                reviewed_at = NOW(),
                                reviewed_by = ?
                          WHERE id = ?'
                    )->execute([$newStatus, (int)$user['id'], $requestId]);
                    $flash = ['type' => 'ok', 'msg' => "Marked request #{$requestId} as {$newStatus}."];
                } catch (Throwable $e) {
                    error_log('[admin/deny-spam] failed: ' . $e->getMessage());
                    $flash = ['type' => 'err', 'msg' => 'Update failed: ' . $e->getMessage()];
                }
            }
        } else {
            $flash = ['type' => 'err', 'msg' => 'Unknown action.'];
        }
    }
}

// === Fetch the list ===
if ($filterStatus === 'all') {
    $rows = $pdo->query('SELECT * FROM access_requests ORDER BY created_at DESC LIMIT 200')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT * FROM access_requests WHERE status = ? ORDER BY created_at DESC LIMIT 200');
    $stmt->execute([$filterStatus]);
    $rows = $stmt->fetchAll();
}

$counts = [];
foreach ($pdo->query("SELECT status, COUNT(*) c FROM access_requests GROUP BY status") as $r) {
    $counts[$r['status']] = (int)$r['c'];
}
$counts['all'] = array_sum($counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Access requests · Admin · Samoma Industries</title>
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
      <h1>Access <em>requests</em></h1>
      <p>Approve to auto-create the portal user; deny or mark as spam to dismiss.</p>
    </div>
  </section>

  <section style="padding: 40px 0 80px;">
    <div class="container">

      <?php if ($flash): ?>
        <div class="admin-flash <?= $flash['type'] === 'ok' ? 'admin-flash-ok' : 'admin-flash-err' ?>">
          <?php if (!empty($flash['password'])): ?>
            <strong><?= htmlspecialchars($flash['msg']) ?></strong><br>
            Send this to <code><?= htmlspecialchars($flash['email']) ?></code>:
            <code class="admin-temp-pw"><?= htmlspecialchars($flash['password']) ?></code>
            <br><small><strong>Copy it now — it is not stored anywhere and will not appear again.</strong> The user should change it on first login.</small>
          <?php else: ?>
            <?= htmlspecialchars($flash['msg']) ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="admin-filters">
        <?php foreach (['pending', 'approved', 'denied', 'spam', 'all'] as $s): ?>
          <a href="?status=<?= $s ?>" class="admin-filter <?= $filterStatus === $s ? 'is-active' : '' ?>">
            <?= ucfirst($s) ?>
            <span><?= $counts[$s] ?? 0 ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if (empty($rows)): ?>
        <p style="padding: 32px 0; color: var(--ink-500);">No <?= htmlspecialchars($filterStatus) ?> requests.</p>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Name / Company</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Notes</th>
                <th>Submitted</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td>
                    <strong><?= htmlspecialchars($r['name']) ?></strong><br>
                    <small><?= htmlspecialchars($r['company']) ?></small>
                  </td>
                  <td><a href="mailto:<?= htmlspecialchars($r['email']) ?>"><?= htmlspecialchars($r['email']) ?></a></td>
                  <td><?= htmlspecialchars($r['phone'] ?? '') ?></td>
                  <td><?= nl2br(htmlspecialchars(mb_substr((string)$r['notes'], 0, 240) . (mb_strlen((string)$r['notes']) > 240 ? '…' : ''))) ?></td>
                  <td><small><?= htmlspecialchars($r['created_at']) ?></small></td>
                  <td><span class="status-badge status-<?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                  <td>
                    <?php if ($r['status'] === 'pending'): ?>
                      <div class="admin-actions">
                        <form method="post" style="display:inline">
                          <input type="hidden" name="action" value="approve">
                          <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                          <button class="btn-tiny btn-tiny-approve" type="submit" onclick="return confirm('Approve this request and create a user account for <?= htmlspecialchars($r['email'], ENT_QUOTES) ?>?');">Approve</button>
                        </form>
                        <form method="post" style="display:inline">
                          <input type="hidden" name="action" value="deny">
                          <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                          <button class="btn-tiny btn-tiny-deny" type="submit" onclick="return confirm('Deny this request?');">Deny</button>
                        </form>
                        <form method="post" style="display:inline">
                          <input type="hidden" name="action" value="spam">
                          <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                          <button class="btn-tiny btn-tiny-spam" type="submit" onclick="return confirm('Mark as spam?');">Spam</button>
                        </form>
                      </div>
                    <?php else: ?>
                      <small style="color: var(--ink-500);">
                        <?= $r['reviewed_at'] ? 'on ' . htmlspecialchars($r['reviewed_at']) : '—' ?>
                      </small>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    </div>
  </section>

  <script src="../assets/js/main.js"></script>
</body>
</html>
