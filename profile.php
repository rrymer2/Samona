<?php
declare(strict_types=1);

require __DIR__ . '/auth/session.php';
$user = require_login();

/**
 * CSRF token tied to the session cookie. The codebase has no $_SESSION;
 * the httponly session token is a per-user secret we can HMAC against so
 * state-changing POSTs can't be forged from another origin.
 */
function csrf_token(): string {
    $cfg   = auth_config();
    $token = (string)($_COOKIE[$cfg['session_cookie']] ?? '');
    return hash_hmac('sha256', 'profile-form', $token);
}
function csrf_ok(): bool {
    $sent = (string)($_POST['csrf'] ?? '');
    return $sent !== '' && hash_equals(csrf_token(), $sent);
}

$pdo = db();

// Pull the editable fields + current hash for verification.
$stmt = $pdo->prepare('SELECT email, company_name, phone, password_hash FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$row = $stmt->fetch() ?: [];

$company = (string)($row['company_name'] ?? '');
$phone   = (string)($row['phone'] ?? '');

$errors  = [];      // field => message
$success = null;    // banner text

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string)($_POST['form'] ?? '');

    if (!csrf_ok()) {
        $errors['_form'] = 'Your session expired. Please reload the page and try again.';
    } elseif ($action === 'profile') {
        $company = trim((string)($_POST['company_name'] ?? ''));
        $phone   = trim((string)($_POST['phone'] ?? ''));

        if (mb_strlen($company) > 120) {
            $errors['company_name'] = 'Company name must be 120 characters or fewer.';
        }
        if (mb_strlen($phone) > 40) {
            $errors['phone'] = 'Phone number must be 40 characters or fewer.';
        }

        if (!$errors) {
            try {
                $pdo->prepare('UPDATE users SET company_name = ?, phone = ? WHERE id = ?')
                    ->execute([
                        $company !== '' ? $company : null,
                        $phone !== ''   ? $phone   : null,
                        $user['id'],
                    ]);
                $success = 'Your profile details have been saved.';
            } catch (Throwable $e) {
                error_log('[profile] update failed: ' . $e->getMessage());
                $errors['_form'] = 'Something went wrong saving your details. Please try again.';
            }
        }
    } elseif ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($current === '' || !password_verify($current, (string)($row['password_hash'] ?? ''))) {
            $errors['current_password'] = 'Your current password is incorrect.';
        }
        if (mb_strlen($new) < 8) {
            $errors['new_password'] = 'New password must be at least 8 characters.';
        } elseif (mb_strlen($new) > 200) {
            $errors['new_password'] = 'New password is too long.';
        } elseif ($new === $current) {
            $errors['new_password'] = 'New password must be different from your current one.';
        }
        if ($new !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (!$errors) {
            try {
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
                $success = 'Your password has been changed.';
            } catch (Throwable $e) {
                error_log('[profile] password change failed: ' . $e->getMessage());
                $errors['_form'] = 'Something went wrong updating your password. Please try again.';
            }
        }
    }
}

function fe(string $k, array $errors): string {
    return isset($errors[$k]) ? ' has-error' : '';
}
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Profile — Samoma Industries</title>
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
        <li><a href="profile.php" class="active">Profile</a></li>
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

  <section class="page-hero" style="padding: 160px 0 88px;">
    <div class="container">
      <span class="eyebrow">Client Portal</span>
      <h3>My profile</h3>
      <p>Signed in as <em><?= htmlspecialchars($user['email'], ENT_QUOTES) ?></em>.</p>
    </div>
  </section>

  <section style="padding: 80px 0;">
    <div class="container" style="max-width: 720px;">

      <?php if ($success): ?>
        <p class="form-banner form-banner-ok"><?= htmlspecialchars($success, ENT_QUOTES) ?></p>
      <?php endif; ?>
      <?php if (!empty($errors['_form'])): ?>
        <p class="form-banner"><?= htmlspecialchars($errors['_form'], ENT_QUOTES) ?></p>
      <?php endif; ?>

      <!-- Profile details -->
      <div class="auth-card reveal" style="margin-bottom: 40px;">
        <span class="eyebrow">Account details</span>
        <h2>Company &amp; contact</h2>
        <p class="lede">Update the company name and phone number on your account.</p>

        <form action="profile.php" method="post" novalidate>
          <input type="hidden" name="form" value="profile">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

          <div class="form-field" data-field="email">
            <label for="email">Email</label>
            <input class="form-input" id="email" type="email" value="<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>" disabled>
          </div>

          <div class="form-field<?= fe('company_name', $errors) ?>" data-field="company_name">
            <label for="company_name">Company name</label>
            <input class="form-input" id="company_name" name="company_name" type="text" maxlength="120" value="<?= htmlspecialchars($company, ENT_QUOTES) ?>" placeholder="Acme Industrial Co.">
            <span class="form-error"><?= htmlspecialchars($errors['company_name'] ?? 'Company name must be 120 characters or fewer.', ENT_QUOTES) ?></span>
          </div>

          <div class="form-field<?= fe('phone', $errors) ?>" data-field="phone">
            <label for="phone">Phone</label>
            <input class="form-input" id="phone" name="phone" type="tel" maxlength="40" value="<?= htmlspecialchars($phone, ENT_QUOTES) ?>" placeholder="+1 (555) 555-0123">
            <span class="form-error"><?= htmlspecialchars($errors['phone'] ?? 'Phone number must be 40 characters or fewer.', ENT_QUOTES) ?></span>
          </div>

          <button type="submit" class="btn btn-primary auth-submit">Save details</button>
        </form>
      </div>

      <!-- Password change -->
      <div class="auth-card reveal">
        <span class="eyebrow">Security</span>
        <h2>Change password</h2>
        <p class="lede">Enter your current password, then choose a new one (at least 8 characters).</p>

        <form action="profile.php" method="post" novalidate>
          <input type="hidden" name="form" value="password">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

          <div class="form-field<?= fe('current_password', $errors) ?>" data-field="current_password">
            <label for="current_password">Current password</label>
            <input class="form-input" id="current_password" name="current_password" type="password" autocomplete="current-password">
            <span class="form-error"><?= htmlspecialchars($errors['current_password'] ?? 'Your current password is incorrect.', ENT_QUOTES) ?></span>
          </div>

          <div class="form-field<?= fe('new_password', $errors) ?>" data-field="new_password">
            <label for="new_password">New password</label>
            <input class="form-input" id="new_password" name="new_password" type="password" autocomplete="new-password">
            <span class="form-error"><?= htmlspecialchars($errors['new_password'] ?? 'New password must be at least 8 characters.', ENT_QUOTES) ?></span>
          </div>

          <div class="form-field<?= fe('confirm_password', $errors) ?>" data-field="confirm_password">
            <label for="confirm_password">Confirm new password</label>
            <input class="form-input" id="confirm_password" name="confirm_password" type="password" autocomplete="new-password">
            <span class="form-error"><?= htmlspecialchars($errors['confirm_password'] ?? 'Passwords do not match.', ENT_QUOTES) ?></span>
          </div>

          <button type="submit" class="btn btn-primary auth-submit">Change password</button>
        </form>
      </div>

      <p style="margin-top: 40px;">
        <a class="btn btn-outline" href="dashboard.php">Back to dashboard</a>
      </p>
    </div>
  </section>

  <script src="assets/js/main.js"></script>
</body>
</html>
