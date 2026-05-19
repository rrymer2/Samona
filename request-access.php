<?php
declare(strict_types=1);

require __DIR__ . '/auth/db.php';

$errors    = [];
$submitted = false;
$name      = '';
$email     = '';
$company   = '';
$phone     = '';
$notes     = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // Honeypot — bots fill hidden fields humans never see.
    $honeypot = trim((string)($_POST['website'] ?? ''));
    if ($honeypot !== '') {
        // Silently "succeed" so bots don't learn to retry.
        $submitted = true;
    } else {
        $name    = trim((string)($_POST['name']    ?? ''));
        $email   = trim((string)($_POST['email']   ?? ''));
        $company = trim((string)($_POST['company'] ?? ''));
        $phone   = trim((string)($_POST['phone']   ?? ''));
        $notes   = trim((string)($_POST['notes']   ?? ''));

        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'] = 'Please enter your name (120 characters or fewer).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
            $errors['email'] = 'Please enter a valid work email address.';
        }
        if ($company === '' || mb_strlen($company) > 120) {
            $errors['company'] = 'Please enter your company name (120 characters or fewer).';
        }
        if ($phone !== '' && mb_strlen($phone) > 40) {
            $errors['phone'] = 'Phone number is too long.';
        }
        if ($notes !== '' && mb_strlen($notes) > 2000) {
            $errors['notes'] = 'Please keep notes under 2,000 characters.';
        }

        if (!$errors) {
            try {
                db()->prepare(
                    'INSERT INTO access_requests (name, email, company, phone, notes, ip, user_agent)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $name,
                    $email,
                    $company,
                    $phone !== '' ? $phone : null,
                    $notes !== '' ? $notes : null,
                    client_ip(),
                    client_ua(),
                ]);
                $submitted = true;
            } catch (Throwable $e) {
                error_log('[request-access] insert failed: ' . $e->getMessage());
                $errors['_form'] = 'Something went wrong submitting your request. Please try again or email projects@samoma.industries directly.';
            }
        }
    }
}

function fe(string $k, array $errors): string {
    return isset($errors[$k]) ? ' has-error' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Request Access — Samoma Industries</title>
  <meta name="description" content="Request access to the Samoma Industries Client Portal." />
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
        <li><a href="services.html#cases">Cases</a></li>
        <li><a href="login.html">Client Portal</a></li>
      </ul>
      <div class="nav-cta">
        <div class="nav-phone">
          <span>Call us</span>
          +1 (415) 555&#8209;0144
        </div>
        <a class="btn btn-primary btn-compact" href="login.html">Client Portal</a>
        <button class="nav-toggle" aria-label="Toggle menu">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </header>

  <section class="auth-shell">
    <aside class="auth-aside">
      <a class="brand" href="index.html">
        <img class="brand-img brand-img-dark" src="assets/images/samona-logo-dark.svg" alt="Samona Industries">
        <img class="brand-img brand-img-light" src="assets/images/samona-logo-light.svg" alt="" aria-hidden="true">
      </a>

      <div class="auth-aside-copy reveal">
        <span class="eyebrow">Client Portal</span>
        <h2>Request access to the Samoma <em>workspace</em>.</h2>
        <p>The portal is reserved for active and prospective Samoma engagement partners. Tell us a bit about your team and we&rsquo;ll be in touch within one business day.</p>
      </div>

      <div class="auth-aside-meta">
        <div class="item">
          <div class="label">Existing client?</div>
          <div class="value"><a href="login.html" style="color: var(--gold-400);">Sign in &rarr;</a></div>
        </div>
        <div class="item">
          <div class="label">Direct</div>
          <div class="value">projects@samoma.industries</div>
        </div>
      </div>
    </aside>

    <div class="auth-form-wrap">
      <div class="auth-card reveal">
        <span class="eyebrow">Request access</span>

        <?php if ($submitted): ?>
          <h2>Thanks. We&rsquo;ll be in touch.</h2>
          <p class="lede">Your request has been received. A Samoma engagement partner will reach out within one business day to confirm next steps.</p>
          <p style="margin-top: 28px;">
            <a class="btn btn-primary" href="index.html">Back to home</a>
            <a class="btn btn-outline" href="login.html" style="margin-left: 12px;">Sign in</a>
          </p>
        <?php else: ?>
          <h2>Tell us about your team.</h2>
          <p class="lede">Required fields are marked. We typically respond within one business day.</p>

          <form action="request-access.php" method="post" id="request-access-form" novalidate>
            <?php if (!empty($errors['_form'])): ?>
              <p class="form-banner"><?= htmlspecialchars($errors['_form'], ENT_QUOTES) ?></p>
            <?php endif; ?>

            <!-- Honeypot: hidden from humans, irresistible to bots. -->
            <div style="position: absolute; left: -10000px; top: auto; width: 1px; height: 1px; overflow: hidden;" aria-hidden="true">
              <label>Website (leave blank)<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="form-field<?= fe('name', $errors) ?>" data-field="name">
              <label for="name">Full name</label>
              <input class="form-input" id="name" name="name" type="text" maxlength="120" required value="<?= htmlspecialchars($name, ENT_QUOTES) ?>" placeholder="Jane Doe">
              <span class="form-error"><?= htmlspecialchars($errors['name'] ?? 'Name is required.', ENT_QUOTES) ?></span>
            </div>

            <div class="form-field<?= fe('email', $errors) ?>" data-field="email">
              <label for="email">Work email</label>
              <input class="form-input" id="email" name="email" type="email" maxlength="254" required value="<?= htmlspecialchars($email, ENT_QUOTES) ?>" placeholder="jane@company.com">
              <span class="form-error"><?= htmlspecialchars($errors['email'] ?? 'Please enter a valid email address.', ENT_QUOTES) ?></span>
            </div>

            <div class="form-field<?= fe('company', $errors) ?>" data-field="company">
              <label for="company">Company</label>
              <input class="form-input" id="company" name="company" type="text" maxlength="120" required value="<?= htmlspecialchars($company, ENT_QUOTES) ?>" placeholder="Acme Industrial Co.">
              <span class="form-error"><?= htmlspecialchars($errors['company'] ?? 'Company is required.', ENT_QUOTES) ?></span>
            </div>

            <div class="form-field<?= fe('phone', $errors) ?>" data-field="phone">
              <label for="phone">Phone <span style="font-weight: 400; color: var(--ink-500); text-transform: none; letter-spacing: 0;">(optional)</span></label>
              <input class="form-input" id="phone" name="phone" type="tel" maxlength="40" value="<?= htmlspecialchars($phone, ENT_QUOTES) ?>" placeholder="+1 (555) 555-0123">
              <span class="form-error"><?= htmlspecialchars($errors['phone'] ?? 'Please check the phone number.', ENT_QUOTES) ?></span>
            </div>

            <div class="form-field<?= fe('notes', $errors) ?>" data-field="notes">
              <label for="notes">How can we help? <span style="font-weight: 400; color: var(--ink-500); text-transform: none; letter-spacing: 0;">(optional)</span></label>
              <textarea class="form-input" id="notes" name="notes" rows="4" maxlength="2000" placeholder="Tell us about your project, timeline, or current engagement."><?= htmlspecialchars($notes, ENT_QUOTES) ?></textarea>
              <span class="form-error"><?= htmlspecialchars($errors['notes'] ?? 'Please keep notes under 2,000 characters.', ENT_QUOTES) ?></span>
            </div>

            <button type="submit" class="btn btn-primary auth-submit">
              Submit request
              <svg class="arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
            </button>

            <div class="form-meta">
              Already have an account?<a href="login.html">Sign in</a>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <footer class="site-footer">
    <div class="container">
      <div class="footer-grid">
        <div>
          <a class="brand" href="index.html">
            <img class="brand-img brand-img-dark" src="assets/images/samona-logo-dark.svg" alt="Samona Industries">
            <img class="brand-img brand-img-light" src="assets/images/samona-logo-light.svg" alt="" aria-hidden="true">
          </a>
          <p>Samoma Industries delivers precision industrial solutions for manufacturing, energy, and infrastructure across twenty-seven countries.</p>
        </div>
        <div>
          <h5>Company</h5>
          <ul>
            <li><a href="about.html">About</a></li>
            <li><a href="services.html#cases">Cases</a></li>
            <li><a href="#">Careers</a></li>
          </ul>
        </div>
        <div>
          <h5>Practices</h5>
          <ul>
            <li><a href="services.html">Manufacturing</a></li>
            <li><a href="services.html">Energy systems</a></li>
            <li><a href="services.html">Infrastructure</a></li>
          </ul>
        </div>
        <div>
          <h5>Get in touch</h5>
          <p>2400 Industrial Parkway<br/>Houston, TX 77001<br/>United States</p>
          <p><a href="mailto:projects@samoma.industries">projects@samoma.industries</a><br/>+1 (415) 555-0144</p>
        </div>
      </div>
      <div class="footer-bottom">
        <div>&copy; <span id="year">2026</span> Samoma Industries, Inc. All rights reserved.</div>
        <div><a href="#">Privacy</a> &nbsp;&middot;&nbsp; <a href="#">Terms</a> &nbsp;&middot;&nbsp; <a href="#">Code of conduct</a></div>
      </div>
    </div>
  </footer>

  <script src="assets/js/main.js"></script>
</body>
</html>
