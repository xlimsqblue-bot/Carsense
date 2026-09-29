<?php
// Command line only:  php database/create_user.php --username=jdelacruz --role=admin --name="Juan Dela Cruz"
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
define('PARKSENSE', true);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';

$o = getopt('', ['username:', 'role:', 'name:']);
$username = $o['username'] ?? '';
$role     = $o['role'] ?? '';
$name     = trim($o['name'] ?? '');

if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) exit("Username: 3-50 letters, numbers, . _ -\n");
if (!in_array($role, ['admin', 'guard'], true))          exit("Role must be admin or guard\n");
if ($name === '' || strlen($name) > 100)                 exit("Provide --name=\"Full Name\"\n");

function ask(string $label): string {
    echo $label;
    $win = stripos(PHP_OS, 'WIN') === 0;                  // Windows shows what you type
    if (!$win) system('stty -echo');
    $v = rtrim((string)fgets(STDIN), "\r\n");
    if (!$win) system('stty echo');
    echo PHP_EOL;
    return $v;
}
$pw = ask('Password (min 12 characters): ');
if (strlen($pw) < 12 || strlen($pw) > 200 || strcasecmp($pw, $username) === 0) exit("Password too weak (12+ characters, not the username).\n");
if ($pw !== ask('Repeat password: ')) exit("Passwords do not match.\n");

try {
    db()->prepare('INSERT INTO users (username, full_name, password_hash, role) VALUES (?, ?, ?, ?)')
        ->execute([$username, $name, password_hash($pw, PASSWORD_DEFAULT), $role]);
    echo "Created $role account '$username'.\n";
} catch (PDOException $e) {
    echo ($e->getCode() === '23505') ? "That username already exists.\n" : "Database error: see the PHP error log.\n";
    error_log($e->getMessage());
}
