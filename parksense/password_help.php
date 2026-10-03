<?php
declare(strict_types=1);
define('PARKSENSE', true);
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Password help';
require INCLUDES . '/header.php';
?>
<main class="login">
  <h1>Password help</h1>
  <p class="sub">Password recovery is handled by a ParkSense administrator.</p>
  <p>Ask your administrator to reset your guard/staff password. They will give you a temporary password, which you must change after signing in.</p>
  <p><a class="login-link" href="login.php">Back to sign in</a></p>
</main>
<?php require INCLUDES . '/footer.php'; ?>
