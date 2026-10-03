<?php
declare(strict_types=1);
defined('PARKSENSE') || exit('Forbidden');

// A valid bcrypt hash used only to keep timing equal when a username does not exist.
const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
const GENERIC_LOGIN_ERROR = 'Username or password is incorrect.';

function ua_hash(): string {
    return hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

function record_attempt(string $username, bool $ok): void {
    $st = db()->prepare('INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, ?)');
    $st->execute([substr($username, 0, 50), client_ip(), $ok ? 'true' : 'false']);
}

function is_throttled(string $username): bool {
    $q = 'SELECT COUNT(*) FROM login_attempts WHERE NOT success
          AND attempted_at > NOW() - make_interval(secs => ?) AND ';
    $st = db()->prepare($q . 'username = ?');
    $st->execute([LOCKOUT_SECONDS, substr($username, 0, 50)]);
    if ((int)$st->fetchColumn() >= MAX_USER_ATTEMPTS) return true;

    $st = db()->prepare($q . 'ip_address = ?');
    $st->execute([LOCKOUT_SECONDS, client_ip()]);
    return (int)$st->fetchColumn() >= MAX_IP_ATTEMPTS;
}

/** Returns an error message, or null when the login succeeded. */
function attempt_login(string $username, string $password): ?string {
    if (is_throttled($username)) {
        return 'Too many failed attempts. Wait 15 minutes and try again.';
    }
    $validShape = preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username) === 1
               && $password !== '' && strlen($password) <= 200;

    $user = false;
    if ($validShape) {
        $st = db()->prepare('SELECT id, username, full_name, password_hash, role
                             FROM users WHERE username = ? AND is_active LIMIT 1');
        $st->execute([$username]);
        $user = $st->fetch();
    }
    // Always run password_verify so "no such user" and "wrong password" take the same time.
    $hashOk = password_verify($password, $user['password_hash'] ?? DUMMY_HASH);

    if (!$user || !$hashOk) {
        record_attempt($username, false);
        return GENERIC_LOGIN_ERROR;              // same message for every failure
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
    db()->prepare('DELETE FROM login_attempts WHERE username = ? AND NOT success')->execute([$user['username']]);
    record_attempt($username, true);

    session_regenerate_id(true);                 // new session ID at login (stops session fixation)
    $_SESSION = [
        'uid'           => (int)$user['id'],
        'login_time'    => time(),
        'last_activity' => time(),
        'ua'            => ua_hash(),
        'csrf'          => bin2hex(random_bytes(32)),
    ];
    return null;
}

function destroy_session(): void {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    start_secure_session();                      // fresh anonymous session for the login form
    session_regenerate_id(true);
}

/** The signed-in user, or null. Role and active status are re-read from the DB on every request. */
function current_user(): ?array {
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;
    if (empty($_SESSION['uid'])) return null;

    $now = time();
    if ($now - ($_SESSION['last_activity'] ?? 0) > IDLE_TIMEOUT
        || $now - ($_SESSION['login_time'] ?? 0) > ABSOLUTE_TIMEOUT
        || !hash_equals($_SESSION['ua'] ?? '', ua_hash())) {
        destroy_session();
        return null;
    }
    $st = db()->prepare('SELECT id, username, full_name, role, must_change_password FROM users WHERE id = ? AND is_active');
    $st->execute([$_SESSION['uid']]);
    $user = $st->fetch();
    if (!$user) { destroy_session(); return null; }

    $_SESSION['last_activity'] = $now;
    return $cache = $user;
}

function require_login(): array {
    $u = current_user();
    if (!$u) redirect('login.php');
    return $u;
}

/** Server-side role check. Hiding a link is not access control; this is. */
function require_role(string ...$roles): array {
    $u = require_login();
    if (db_bool($u['must_change_password'])) redirect('change_password.php');
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to open this page.');
    }
    return $u;
}
