<?php
declare(strict_types=1);
defined('PARKSENSE') || exit('Forbidden');

// ---- Database: Supabase Postgres (edit these, or set the PS_DB_* environment variables) ----
// Supabase dashboard -> Connect -> "Session pooler". Copy host and user from there.
// The password (Database -> Settings, not the API key) lives in includes/config.local.php, which is
// not committed. Create it from config.local.example.php, or run: php database/set_db_password.php
if (is_file(__DIR__ . '/config.local.php')) require __DIR__ . '/config.local.php';
define('DB_HOST', getenv('PS_DB_HOST') ?: 'aws-0-ap-northeast-1.pooler.supabase.com');
define('DB_PORT', getenv('PS_DB_PORT') ?: '5432');         // session pooler; do not use 6543
define('DB_NAME', getenv('PS_DB_NAME') ?: 'postgres');
define('DB_USER', getenv('PS_DB_USER') ?: 'postgres.nqxtbkfrorlhdrrzctxu');
defined('DB_PASS') || define('DB_PASS', getenv('PS_DB_PASS') ?: '');

// ---- App ----
const APP_NAME     = 'ParkSense';
const APP_DEBUG    = false;          // keep false everywhere except your own laptop
const SESSION_NAME = 'PSSESSID';

// ---- Session limits (seconds) ----
const IDLE_TIMEOUT     = 900;        // signed out after 15 min of no activity
const ABSOLUTE_TIMEOUT = 28800;      // signed out after 8 h no matter what

// ---- Brute-force protection ----
const MAX_USER_ATTEMPTS = 5;         // failed tries per username in the window
const MAX_IP_ATTEMPTS   = 20;        // failed tries per IP in the window
const LOCKOUT_SECONDS   = 900;       // window length: 15 min

// ---- Roles ----
const ROLE_LABELS = ['admin' => 'Administrator', 'guard' => 'Guard / Staff'];
