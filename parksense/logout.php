<?php
define('PARKSENSE', true);
require __DIR__ . '/includes/bootstrap.php';

// POST + CSRF token, so another site cannot sign a user out with an image or link.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    destroy_session();
    redirect('login.php?out=1');
}
redirect(current_user() ? 'dashboard.php' : 'login.php');
