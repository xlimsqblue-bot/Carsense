<?php
declare(strict_types=1);
define('PARKSENSE', true);
require __DIR__ . '/includes/bootstrap.php';
require INCLUDES . '/user_import.php';

$admin = require_role('admin');
if (($_GET['template'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="parksense-staff-template.csv"');
    echo "username,full_name,role\r\n";
    exit;
}

function generate_temporary_password(): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $password = '';
    for ($i = 0; $i < 20; $i++) {
        $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $password;
}

function valid_new_account(string $username, string $fullName): bool {
    return preg_match('/^[A-Za-z0-9._-]{3,50}$/D', $username) === 1
        && $fullName !== '' && strlen($fullName) <= 100;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'This page expired. Reload it and try again.';
    } else {
        $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
        $accounts = [];
        try {
            if ($action === 'reset_password') {
                $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (!$userId) {
                    throw new RuntimeException('Choose a valid guard account.');
                }
                $temporaryPassword = generate_temporary_password();
                $pdo = db();
                $pdo->beginTransaction();
                $reset = $pdo->prepare(
                    'UPDATE users SET password_hash = ?, must_change_password = TRUE
                     WHERE id = ? AND role = \'guard\' AND is_active
                     RETURNING username, full_name'
                );
                $reset->execute([password_hash($temporaryPassword, PASSWORD_DEFAULT), $userId]);
                $resetUser = $reset->fetch();
                if (!$resetUser) {
                    $pdo->rollBack();
                    throw new RuntimeException('That active guard account was not found. Refresh the page and try again.');
                }
                $pdo->commit();
                $_SESSION['new_account_credentials'] = [[
                    'username' => $resetUser['username'],
                    'full_name' => $resetUser['full_name'],
                    'temporary_password' => $temporaryPassword,
                ]];
                $_SESSION['credential_notice'] = 'A new temporary password was created. The guard must change it at next sign-in.';
                redirect('users.php?reset=1');
            } elseif ($action === 'create') {
                $username = isset($_POST['username']) && is_string($_POST['username']) ? trim($_POST['username']) : '';
                $fullName = isset($_POST['full_name']) && is_string($_POST['full_name']) ? trim($_POST['full_name']) : '';
                if (!valid_new_account($username, $fullName)) {
                    throw new RuntimeException('Enter a valid username (3-50 letters, numbers, dots, underscores, or dashes) and full name (up to 100 characters).');
                }
                $accounts[] = ['username' => $username, 'full_name' => $fullName, 'role' => 'guard'];
            } elseif ($action === 'import') {
                $file = $_FILES['staff_file'] ?? null;
                if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                    || !isset($file['tmp_name'], $file['name']) || !is_uploaded_file($file['tmp_name'])) {
                    throw new RuntimeException('Choose a valid CSV or .xlsx upload. The file may be too large for the current PHP upload limit.');
                }
                if ((int)($file['size'] ?? 0) > USER_IMPORT_MAX_BYTES) {
                    throw new RuntimeException('Choose an import file no larger than 1 MB.');
                }
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $rows = read_user_import($file['tmp_name'], $extension);
                $validationErrors = validate_import_accounts($rows);
                if ($validationErrors) {
                    throw new RuntimeException(implode(' ', array_slice($validationErrors, 0, 10)));
                }
                $accounts = array_column($rows, 'account');
            } else {
                throw new RuntimeException('That action is not supported.');
            }

            $credentials = [];
            foreach ($accounts as $account) {
                $credentials[] = $account + ['temporary_password' => generate_temporary_password()];
            }
            $pdo = db();
            $pdo->beginTransaction();
            $insert = $pdo->prepare(
                'INSERT INTO users (username, full_name, password_hash, role, must_change_password)
                 VALUES (?, ?, ?, ?, TRUE)'
            );
            foreach ($credentials as $account) {
                $insert->execute([
                    $account['username'],
                    $account['full_name'],
                    password_hash($account['temporary_password'], PASSWORD_DEFAULT),
                    'guard',
                ]);
            }
            $pdo->commit();
            $_SESSION['new_account_credentials'] = $credentials;
            redirect('users.php?created=' . count($credentials));
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getCode() === '23505') {
                $error = 'A username already exists. No accounts from this submission were created; check the list and try again.';
            } else {
                throw $e;
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
}

$createdCredentials = $_SESSION['new_account_credentials'] ?? [];
unset($_SESSION['new_account_credentials']);
$users = db()->query(
    'SELECT id, username, full_name, role, is_active, must_change_password, created_at
     FROM users ORDER BY created_at DESC, id DESC LIMIT 200'
)->fetchAll();

$pageTitle = 'Manage users';
$topbarUser = $admin;
require INCLUDES . '/header.php';
?>
<main class="dashboard">
  <div class="page-heading">
    <div>
      <h1>Manage guard accounts</h1>
      <p class="sub">Create accounts individually or import up to 100 guards/staff at a time.</p>
    </div>
  </div>
  <?php if ($error !== ''): ?><p class="err form-error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if ($createdCredentials): ?>
    <section class="credential-notice" aria-labelledby="credential-title">
      <h2 id="credential-title">Temporary password — save it now</h2>
      <p><?= e($_SESSION['credential_notice'] ?? 'This password is shown only once. Share it privately; the user must change it at first sign-in.') ?></p>
      <div class="table-scroll">
        <table>
          <thead><tr><th>Username</th><th>Name</th><th>Temporary password</th></tr></thead>
          <tbody>
          <?php foreach ($createdCredentials as $credential): ?>
            <tr>
              <td><?= e($credential['username']) ?></td>
              <td><?= e($credential['full_name']) ?></td>
              <td><code><?= e($credential['temporary_password']) ?></code></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
  <?php unset($_SESSION['credential_notice']); ?>

  <div class="account-forms">
    <section class="slot-setup">
      <h2>Add one guard or staff member</h2>
      <p class="sub">The account receives the Guard / Staff role and a one-time temporary password.</p>
      <form method="post" action="users.php" class="account-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <label>Username
          <input name="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]+" autocomplete="off" required>
        </label>
        <label>Full name
          <input name="full_name" maxlength="100" required>
        </label>
        <button type="submit" class="btn">Create account</button>
      </form>
    </section>

    <section class="slot-setup">
      <h2>Import from Excel</h2>
      <p class="sub">Upload .xlsx or CSV. Columns: username, full_name, role (optional; guard or staff). No passwords in the file.</p>
      <p><a href="users.php?template=csv">Download CSV template</a></p>
      <form method="post" action="users.php" enctype="multipart/form-data" class="account-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="import">
        <label>Staff list (.xlsx or .csv)
          <input type="file" name="staff_file" accept=".xlsx,.csv" required>
        </label>
        <button type="submit" class="btn">Import accounts</button>
      </form>
      <p class="sub">Imports are all-or-nothing. Maximum 100 accounts and 1 MB per file.</p>
    </section>
  </div>

  <section class="history-section">
    <h2>Accounts</h2>
    <div class="table-scroll">
      <table>
        <thead><tr><th>Username</th><th>Full name</th><th>Role</th><th>Status</th><th>First sign-in</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $listedUser): ?>
          <?php $active = db_bool($listedUser['is_active']); ?>
          <?php $mustChange = db_bool($listedUser['must_change_password']); ?>
          <tr>
            <td><?= e($listedUser['username']) ?></td>
            <td><?= e($listedUser['full_name']) ?></td>
            <td><?= e(ROLE_LABELS[$listedUser['role']] ?? $listedUser['role']) ?></td>
            <td><?= $active ? 'Active' : 'Disabled' ?></td>
            <td><?= $mustChange ? 'Temporary password' : 'Ready' ?></td>
            <td><?= e((new DateTimeImmutable($listedUser['created_at']))->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y')) ?></td>
            <td>
              <?php if ($listedUser['role'] === 'guard' && $active): ?>
                <form method="post" action="users.php" class="inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="reset_password">
                  <input type="hidden" name="user_id" value="<?= (int)$listedUser['id'] ?>">
                  <button type="submit" class="btn-small">Reset password</button>
                </form>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
<?php require INCLUDES . '/footer.php'; ?>
