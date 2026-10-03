<?php
declare(strict_types=1);
define('PARKSENSE', true);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$mustChange = db_bool($user['must_change_password']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'This page expired. Reload it and try again.';
    } else {
        $currentPassword = isset($_POST['current_password']) && is_string($_POST['current_password'])
            ? $_POST['current_password'] : '';
        $newPassword = isset($_POST['new_password']) && is_string($_POST['new_password'])
            ? $_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) && is_string($_POST['confirm_password'])
            ? $_POST['confirm_password'] : '';

        $st = db()->prepare('SELECT password_hash FROM users WHERE id = ? AND is_active');
        $st->execute([$user['id']]);
        $passwordHash = $st->fetchColumn();
        if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPassword) < 12 || strlen($newPassword) > 200) {
            $error = 'Your new password must be between 12 and 200 characters.';
        } elseif (hash_equals($currentPassword, $newPassword)) {
            $error = 'Choose a new password that is different from your current password.';
        } elseif (!hash_equals($newPassword, $confirmPassword)) {
            $error = 'The new passwords do not match.';
        } else {
            db()->prepare(
                'UPDATE users SET password_hash = ?, must_change_password = FALSE WHERE id = ?'
            )->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            redirect('dashboard.php?password_changed=1');
        }
    }
}

$pageTitle = 'Change password';
$topbarUser = $mustChange ? null : $user;
require INCLUDES . '/header.php';
?>
<main class="login">
  <h1>Change password</h1>
  <?php if ($mustChange): ?>
    <p class="sub">Your administrator created a temporary password. Choose a new password to continue.</p>
    <p>If you no longer have the temporary password, ask an administrator to reset it. Then sign out below and sign in with the new one.</p>
  <?php else: ?>
    <p class="sub">Use a password with at least 12 characters.</p>
  <?php endif; ?>
  <?php if ($error !== ''): ?><p class="err" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="change_password.php" autocomplete="on">
    <?= csrf_field() ?>
    <label for="current-password">Current password</label>
    <input id="current-password" name="current_password" type="password" maxlength="200" autocomplete="current-password" required autofocus>
    <label for="new-password">New password</label>
    <input id="new-password" name="new_password" type="password" minlength="12" maxlength="200" autocomplete="new-password" required>
    <label for="confirm-password">Confirm new password</label>
    <input id="confirm-password" name="confirm_password" type="password" minlength="12" maxlength="200" autocomplete="new-password" required>
    <button type="submit" class="btn">Update password</button>
  </form>
  <?php if ($mustChange): ?>
    <form method="post" action="logout.php" class="back-login-form">
      <?= csrf_field() ?>
      <button type="submit" class="btn back-login-button">Sign out and return to login</button>
    </form>
  <?php endif; ?>
</main>
<?php require INCLUDES . '/footer.php'; ?>
