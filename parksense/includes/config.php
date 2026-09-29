<?php
declare(strict_types=1);
defined('PARKSENSE') || exit('Forbidden');

// ---- Database (edit these, or set the PS_DB_* environment variables) ----
define('DB_HOST', getenv('PS_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('PS_DB_NAME') ?: 'parksense');
define('DB_USER', getenv('PS_DB_USER') ?: 'parksense_app');
define('DB_PASS', getenv('PS_DB_PASS') ?: '021203060601$ean8054S');

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
