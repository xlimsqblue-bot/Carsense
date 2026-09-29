<?php
define('PARKSENSE', true);
require __DIR__ . '/includes/bootstrap.php';

$user = require_role('admin', 'guard');      // both roles may open the dashboard

$pageTitle  = 'Dashboard';
$topbarUser = $user;
require INCLUDES . '/header.php';
?>
<main class="stub">
  <h1>Welcome, <?= e($user['full_name']) ?></h1>
  <p>You are signed in as <strong><?= e(ROLE_LABELS[$user['role']] ?? '') ?></strong>.
     The dashboard content will be built next.</p>
</main>
<?php require INCLUDES . '/footer.php'; ?>
