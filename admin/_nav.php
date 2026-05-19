<?php
// Shared admin navigation. Include AFTER $user = require_admin() so $user is set.
$current = basename($_SERVER['PHP_SELF'] ?? '');
function admin_nav_active(string $current, string $page): string {
    return $current === $page ? ' class="active"' : '';
}
?>
<header class="admin-header">
  <div class="container nav">
    <a class="brand" href="../index.html">
      <img class="brand-img brand-img-dark" src="../assets/images/samona-logo-dark.svg" alt="Samona Industries">
      <img class="brand-img brand-img-light" src="../assets/images/samona-logo-light.svg" alt="" aria-hidden="true">
    </a>
    <span class="admin-pill">Admin</span>
    <ul class="nav-links" id="nav-links">
      <li><a href="index.php"<?= admin_nav_active($current, 'index.php') ?>>Overview</a></li>
      <li><a href="access-requests.php"<?= admin_nav_active($current, 'access-requests.php') ?>>Access requests</a></li>
      <li><a href="users.php"<?= admin_nav_active($current, 'users.php') ?>>Users</a></li>
      <li><a href="../dashboard.php">Portal</a></li>
    </ul>
    <div class="nav-cta">
      <a class="btn btn-primary btn-compact" href="../auth/logout.php">Sign out</a>
      <button class="nav-toggle" aria-label="Toggle menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>
