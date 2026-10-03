<?php
defined('PARKSENSE') || exit('Forbidden');
$pageTitle  = $pageTitle  ?? APP_NAME;
$topbarUser = $topbarUser ?? null;           // set on pages that show the top bar
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php if ($topbarUser): $isAdmin = $topbarUser['role'] === 'admin'; ?>
<header class="topbar">
  <a class="brand" href="dashboard.php"><?= e(APP_NAME) ?></a>
  <nav aria-label="Main">
    <a href="dashboard.php" aria-current="page">Dashboard</a>
    <a href="dashboard.php#monitor">Monitor</a>
    <span class="soon" title="Coming soon">Alerts</span>
    <span class="soon" title="Coming soon">Records</span>
    <?php if ($isAdmin): ?>
      <span class="soon" title="Coming soon">Reports</span>
      <span class="soon" title="Coming soon">Users</span>
    <?php endif; ?>
  </nav>
  <div class="who">
    <span class="role <?= $isAdmin ? 'role-admin' : 'role-guard' ?>"><?= e(ROLE_LABELS[$topbarUser['role']] ?? '') ?></span>
    <span class="name"><?= e($topbarUser['full_name']) ?></span>
    <form method="post" action="logout.php">
      <?= csrf_field() ?>
      <button type="submit" class="btn-out">Sign out</button>
    </form>
  </div>
</header>
<?php endif; ?>
