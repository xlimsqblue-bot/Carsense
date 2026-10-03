<?php
define('PARKSENSE', true);
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) redirect('dashboard.php');

$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'This page expired. Reload it and try again.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $error = attempt_login($username, $password);
        if ($error === null) redirect('dashboard.php');   // fixed destination, no "next" parameter
    }
}
$notice = isset($_GET['out']) ? 'You have been signed out.' : '';

$pageTitle = 'Sign in';
require INCLUDES . '/header.php';
?>
<main class="login">
  <h1><?= e(APP_NAME) ?></h1>
  <p class="sub">Campus parking dashboard for guards, staff and administrators</p>
  <?php if ($notice): ?><p class="note" role="status"><?= e($notice) ?></p><?php endif; ?>
  <form method="post" action="login.php" autocomplete="on" novalidate>
    <?= csrf_field() ?>
    <label for="username">Username</label>
    <input id="username" name="username" type="text" maxlength="50" autocomplete="username"
           value="<?= e($username) ?>" required autofocus>
    <label for="password">Password</label>
    <input id="password" name="password" type="password" maxlength="200" autocomplete="current-password" required>
    <p class="err" role="alert"><?= e($error) ?></p>
    <button type="submit" class="btn">Sign in</button>
  </form>
  <p class="login-help"><a href="password_help.php">Forgot your password?</a></p>
</main>
<?php require INCLUDES . '/footer.php'; ?>
