<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/db.php';

$user = require_admin();
$pdo  = db();

$flash = null;

// === POST handlers ===
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string)($_POST['action']  ?? '');
    $userId = (int)   ($_POST['user_id'] ?? 0);

    if ($userId <= 0) {
        $flash = ['type' => 'err', 'msg' => 'Invalid user id.'];
    } elseif ($userId === (int)$user['id']) {
        $flash = ['type' => 'err', 'msg' => 'You cannot change your own admin status from here.'];
    } else {
        try {
            if ($action === 'make_admin') {
                $pdo->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$userId]);
                $flash = ['type' => 'ok', 'msg' => "User #{$userId} is now an admin."];
            } elseif ($action === 'remove_admin') {
                $pdo->prepare('UPDATE users SET is_admin = 0 WHERE id = ?')->execute([$userId]);
                $flash = ['type' => 'ok', 'msg' => "Admin privileges removed from user #{$userId}."];
            } elseif ($action === 'reset_password') {
                $tempPassword = bin2hex(random_bytes(8));
                $hash         = password_hash($tempPassword, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([$hash, $userId]);
                // Invalidate existing sessions for that user
                $pdo->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([$userId]);
                $row = $pdo->prepare('SELECT email FROM users WHERE id = ?');
                $row->execute([$userId]);
                $email = (string)$row->fetchColumn();
                $flash = [
                    'type'     => 'ok',
                    'msg'      => "Password reset for user #{$userId}.",
                    'email'    => $email,
                    'password' => $tempPassword,
                ];
            } else {
                $flash = ['type' => 'err', 'msg' => 'Unknown action.'];
            }
        } catch (Throwable $e) {
            error_log('[admin/users] action failed: ' . $e->getMessage());
            $flash = ['type' => 'err', 'msg' => 'Action failed: ' . $e->getMessage()];
        }
    }
}

// === Fetch users with last-seen + recent-event data ===
$rows = $pdo->query(
    'SELECT
        u.id, u.email, u.is_admin, u.created_at,
        MAX(s.last_seen) AS last_seen,
        (SELECT MAX(attempted_at) FROM login_events e WHERE e.user_id = u.id AND e.success = 1) AS last_login
     FROM users u
     LEFT JOIN sessions s ON s.user_id = u.id
     GROUP BY u.id
     ORDER BY u.created_at DESC
     LIMIT 500'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Users · Admin · Samoma Industries</title>
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
      <h1>Portal <em>users</em></h1>
      <p>Manage admin privileges and reset passwords. Approved access requests show up here.</p>
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
            <br><small><strong>Copy it now — it is not stored anywhere and will not appear again.</strong> The user's existing sessions have been signed out.</small>
          <?php else: ?>
            <?= htmlspecialchars($flash['msg']) ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Email</th>
              <th>Role</th>
              <th>Created</th>
              <th>Last login</th>
              <th>Last seen</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <?php $isMe = (int)$r['id'] === (int)$user['id']; ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td>
                  <?= htmlspecialchars($r['email']) ?>
                  <?php if ($isMe): ?><small style="color: var(--gold-500);"> (you)</small><?php endif; ?>
                </td>
                <td>
                  <?php if ((int)$r['is_admin'] === 1): ?>
                    <span class="status-badge status-approved">Admin</span>
                  <?php else: ?>
                    <span class="status-badge status-pending">Client</span>
                  <?php endif; ?>
                </td>
                <td><small><?= htmlspecialchars($r['created_at']) ?></small></td>
                <td><small><?= $r['last_login'] ? htmlspecialchars($r['last_login']) : '—' ?></small></td>
                <td><small><?= $r['last_seen'] ? htmlspecialchars($r['last_seen']) : '—' ?></small></td>
                <td>
                  <?php if ($isMe): ?>
                    <small style="color: var(--ink-500);">—</small>
                  <?php else: ?>
                    <div class="admin-actions">
                      <?php if ((int)$r['is_admin'] === 1): ?>
                        <form method="post" style="display:inline">
                          <input type="hidden" name="action" value="remove_admin">
                          <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
                          <button class="btn-tiny btn-tiny-deny" type="submit" onclick="return confirm('Remove admin privileges from <?= htmlspecialchars($r['email'], ENT_QUOTES) ?>?');">Remove admin</button>
                        </form>
                      <?php else: ?>
                        <form method="post" style="display:inline">
                          <input type="hidden" name="action" value="make_admin">
                          <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
                          <button class="btn-tiny btn-tiny-approve" type="submit" onclick="return confirm('Grant admin privileges to <?= htmlspecialchars($r['email'], ENT_QUOTES) ?>?');">Make admin</button>
                        </form>
                      <?php endif; ?>
                      <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
                        <button class="btn-tiny btn-tiny-spam" type="submit" onclick="return confirm('Reset password for <?= htmlspecialchars($r['email'], ENT_QUOTES) ?>?\n\nTheir existing sessions will be signed out and they will need a new temporary password.');">Reset password</button>
                      </form>
                    </div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </div>
  </section>

  <script src="../assets/js/main.js"></script>
</body>
</html>
