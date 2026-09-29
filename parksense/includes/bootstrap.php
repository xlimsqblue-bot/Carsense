<?php
declare(strict_types=1);
defined('PARKSENSE') || exit('Forbidden');

define('INCLUDES', __DIR__);
require INCLUDES . '/config.php';

date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');   // never show errors to visitors
ini_set('log_errors', '1');

require INCLUDES . '/helpers.php';
require INCLUDES . '/security.php';
require INCLUDES . '/db.php';
require INCLUDES . '/csrf.php';
require INCLUDES . '/auth.php';

send_security_headers();
start_secure_session();
