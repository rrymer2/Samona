<?php
declare(strict_types=1);

// === Diagnostic mode: surface PHP errors directly on the page ===
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/auth/db.php';

// ============================================================
// Pre-flight checks — run on every page load (GET and POST).
// Each check produces a row in $checks that's rendered in the
// "Pre-flight" panel at the top of the page.
// ============================================================
$checks = [];
$cfg    = null;
$pdo    = null;

function record_check(array &$checks, string $label, bool $ok, string $detail): void {
    $checks[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
}

try {
    $cfg = auth_config();
    record_check(
        $checks,
        'auth/config.php loads',
        true,
        'DSN: ' . htmlspecialchars($cfg['dsn']) . ' · user: ' . htmlspecialchars($cfg['user'])
    );
} catch (Throwable $e) {
    record_check($checks, 'auth/config.php loads', false, htmlspecialchars($e->getMessage()));
}

if ($cfg !== null) {
    try {
        $pdo = db();
        record_check($checks, 'Connect to MySQL', true, 'OK');
    } catch (Throwable $e) {
        record_check(
            $checks,
            'Connect to MySQL',
            false,
            '[' . htmlspecialchars((string)$e->getCode()) . '] ' . htmlspecialchars($e->getMessage())
        );
    }
}

if ($pdo !== null) {
    foreach (['users', 'sessions', 'login_events'] as $tbl) {
        try {
            $pdo->query("SELECT 1 FROM `{$tbl}` LIMIT 1");
            record_check($checks, "Table `{$tbl}` exists", true, 'OK');
        } catch (Throwable $e) {
            record_check(
                $checks,
                "Table `{$tbl}` exists",
                false,
                '[' . htmlspecialchars((string)$e->getCode()) . '] ' . htmlspecialchars($e->getMessage())
            );
        }
    }
}

// ============================================================
// Form handling
// ============================================================
$message = null;
$status  = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'self_destruct') {
        if (@unlink(__FILE__)) {
            $message = 'seed.php deleted. Reload to confirm 404.';
            $status  = 'ok';
        } else {
            $message = 'Could not delete seed.php — remove it manually via File Manager.';
            $status  = 'err';
        }
    } elseif ($action === 'create_user') {
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $message = 'Email and password are required.';
            $status  = 'err';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'That email address doesn\'t look valid.';
            $status  = 'err';
        } elseif (strlen($password) < 8) {
            $message = 'Password must be at least 8 characters.';
            $status  = 'err';
        } elseif ($pdo === null) {
            $message = 'Cannot create user — see pre-flight failures above.';
            $status  = 'err';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)')
                    ->execute([$email, $hash]);
                $message = 'User created: ' . htmlspecialchars($email, ENT_QUOTES) . '. Delete seed.php now.';
                $status  = 'ok';
            } catch (Throwable $e) {
                if ($e instanceof PDOException && $e->getCode() === '23000') {
                    $message = 'A user with that email already exists.';
                } else {
                    $message = '<strong>['
                        . htmlspecialchars((string)$e->getCode()) . ']</strong> '
                        . htmlspecialchars($e->getMessage());
                }
                $status = 'err';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="robots" content="noindex,nofollow">
  <title>Seed user · Samona Industries · diagnostic</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 720px; margin: 40px auto; padding: 0 24px; color: #111; line-height: 1.5; }
    h1 { font-size: 1.5rem; margin: 0 0 8px; }
    h2 { font-size: 1.1rem; margin: 28px 0 10px; }
    .warn { background: #fff3cd; border: 1px solid #ffe69c; padding: 12px 16px; border-radius: 6px; margin: 20px 0; font-size: 0.92rem; }
    .ok   { background: #d4edda; border: 1px solid #b8dab8; padding: 12px 16px; border-radius: 6px; margin: 20px 0; }
    .err  { background: #f8d7da; border: 1px solid #f1aeb5; padding: 12px 16px; border-radius: 6px; margin: 20px 0; }
    label { display: block; font-size: 0.85rem; font-weight: 600; margin: 16px 0 6px; }
    input { width: 100%; padding: 10px 12px; font-size: 1rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    button { margin-top: 20px; padding: 12px 22px; font-size: 0.95rem; font-weight: 600; background: #0a1226; color: #fff; border: 0; border-radius: 4px; cursor: pointer; }
    button.danger { background: #c0392b; margin-top: 12px; }
    form + form { margin-top: 28px; padding-top: 20px; border-top: 1px solid #eee; }
    code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; }
    small { color: #666; }
    ul.checks { list-style: none; padding: 0; margin: 12px 0; }
    ul.checks li { padding: 8px 12px; border-radius: 4px; margin-bottom: 6px; font-size: 0.92rem; display: flex; gap: 10px; }
    ul.checks li.check-ok  { background: #f0f8f0; }
    ul.checks li.check-err { background: #fdf2f2; }
    ul.checks .icon { font-weight: 700; font-size: 1.1em; flex-shrink: 0; width: 16px; }
    ul.checks .icon-ok  { color: #2d7a2d; }
    ul.checks .icon-err { color: #c0392b; }
    ul.checks .label { font-weight: 600; }
    ul.checks .detail { color: #555; word-break: break-word; }
  </style>
</head>
<body>
  <h1>Seed a Samona user · diagnostic mode</h1>
  <p>One-shot helper to insert your first user. <strong>Delete this file the moment you're done.</strong></p>

  <div class="warn">
    Diagnostic mode prints raw PHP and PDO error messages directly onto this page. That includes your database name and SQL details. Delete <code>seed.php</code> via cPanel File Manager (or the red button below) as soon as you have your user in.
  </div>

  <h2>Pre-flight checks</h2>
  <ul class="checks">
    <?php foreach ($checks as $c): ?>
      <li class="<?= $c['ok'] ? 'check-ok' : 'check-err' ?>">
        <span class="icon <?= $c['ok'] ? 'icon-ok' : 'icon-err' ?>"><?= $c['ok'] ? '&#10003;' : '&#10007;' ?></span>
        <span><span class="label"><?= htmlspecialchars($c['label']) ?></span> &mdash; <span class="detail"><?= $c['detail'] ?></span></span>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($message !== null): ?>
    <div class="<?= $status === 'ok' ? 'ok' : 'err' ?>"><?= $message ?></div>
  <?php endif; ?>

  <h2>Create user</h2>
  <form method="post" autocomplete="off">
    <input type="hidden" name="action" value="create_user">

    <label for="email">Email</label>
    <input id="email" type="email" name="email" required>

    <label for="password">Password (8+ characters)</label>
    <input id="password" type="password" name="password" minlength="8" required>

    <button type="submit">Create user</button>
  </form>

  <form method="post">
    <input type="hidden" name="action" value="self_destruct">
    <button class="danger" type="submit" onclick="return confirm('Delete seed.php now?');">Delete seed.php</button>
    <small>Or remove it manually via cPanel File Manager.</small>
  </form>
</body>
</html>
