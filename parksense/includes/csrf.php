<?php
declare(strict_types=1);
defined('PARKSENSE') || exit('Forbidden');

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** True only if the POSTed token matches the session token (constant-time compare). */
function csrf_verify(): bool {
    $t = $_POST['csrf'] ?? '';
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}
