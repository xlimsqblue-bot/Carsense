<?php
declare(strict_types=1);
defined('PARKSENSE') || exit('Forbidden');

function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/** REMOTE_ADDR only. X-Forwarded-For can be forged, so it is not trusted. */
function client_ip(): string {
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

function send_security_headers(): void {
    header_remove('X-Powered-By');
    header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; "
         . "img-src 'self' data:; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cache-Control: no-store');            // pages behind login are never cached
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function start_secure_session(): void {
    ini_set('session.use_strict_mode', '1');      // reject session IDs the server did not issue
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,                          // cookie dies when the browser closes
        'path'     => '/',
        'secure'   => is_https(),                 // HTTPS only when the site uses HTTPS
        'httponly' => true,                       // JavaScript cannot read the cookie
        'samesite' => 'Strict',                   // not sent on cross-site requests
    ]);
    session_start();
}
