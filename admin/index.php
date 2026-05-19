<?php
declare(strict_types=1);

require __DIR__ . '/../auth/session.php';
require __DIR__ . '/../auth/db.php';

$user = require_admin();
$pdo  = db();

$pendingRequests = (int) $pdo->query("SELECT COUNT(*) FROM access_requests WHERE status = 'pending'")->fetchColumn();
$totalRequests   = (int) $pdo->query("SELECT COUNT(*) FROM access_requests")->fetchColumn();
$totalUsers      = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$adminCount      = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin · Samoma Industries</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/style.css" />
</head>
<body>
  <?php include __DIR__ . '/_nav.php'; ?>

  <section class="admin-hero">
    <div class="container">
      <span class="eyebrow">Admin overview</span>
      <h1>Hello, <em><?= htmlspecialchars($user['email']) ?></em>.</h1>
      <p>Manage access requests and the portal's user list.</p>
    </div>
  </section>

  <section style="padding: 50px 0 80px;">
    <div class="container">
      <div class="admin-cards">

        <a class="admin-card" href="access-requests.php">
          <div class="admin-card-stat"><?= $pendingRequests ?></div>
          <div class="admin-card-stat-label">Pending requests</div>
          <div class="admin-card-meta"><?= $totalRequests ?> total submissions</div>
          <div class="admin-card-action">Review →</div>
        </a>

        <a class="admin-card" href="users.php">
          <div class="admin-card-stat"><?= $totalUsers ?></div>
          <div class="admin-card-stat-label">Active users</div>
          <div class="admin-card-meta"><?= $adminCount ?> admin<?= $adminCount === 1 ? '' : 's' ?></div>
          <div class="admin-card-action">Manage →</div>
        </a>

      </div>
    </div>
  </section>

  <script src="../assets/js/main.js"></script>
</body>
</html>
