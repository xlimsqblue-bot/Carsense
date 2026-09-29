<?php
// Command line only:  php database/set_db_password.php
// Asks for the Supabase database password, tests it, and saves it to includes/config.local.php only if it works.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
define('PARKSENSE', true);
require __DIR__ . '/../includes/config.php';

echo "Paste the Supabase DATABASE password (from Database -> Settings -> Reset password).\n";
echo "Not the API key (sb_publishable_... / sb_secret_...) and not the project URL.\n";
echo 'Password: ';
$pw = rtrim((string)fgets(STDIN), "\r\n");

if ($pw === '')                                      exit("Nothing entered.\n");
if (str_starts_with($pw, 'sb_') || str_starts_with($pw, 'eyJ')) exit("That is an API key, not the database password.\n");
if (str_contains($pw, 'supabase.co'))                exit("That is the project URL, not the database password.\n");

try {
    $pdo = new PDO('pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';sslmode=require;connect_timeout=10',
                   DB_USER, $pw, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    exit("Supabase rejected it: " . trim($e->getMessage()) . "\nNothing was saved.\n");
}

$localFile = __DIR__ . '/../includes/config.local.php';
$src = "<?php\n// Secrets for this computer only. Listed in .gitignore: never commit this file.\n"
     . "defined('PARKSENSE') || exit('Forbidden');\n\n"
     . 'define(\'DB_PASS\', ' . var_export($pw, true) . ");   // Supabase database password\n";
if (!file_put_contents($localFile, $src)) exit("Password works, but includes/config.local.php could not be written.\n");

echo "Connected to Supabase and saved the password in includes/config.local.php.\n";
$users = $pdo->query("SELECT to_regclass('public.users')")->fetchColumn()
    ? $pdo->query('SELECT username, role FROM public.users ORDER BY id')->fetchAll(PDO::FETCH_NUM) : null;
if ($users === null) echo "Warning: the users table does not exist in this project. Run database/schema.sql in the SQL Editor.\n";
else foreach ($users as [$u, $r]) echo "  account: $u ($r)\n";
