<?php
/* =====================================================
   ENVIRONMENT CONFIG
   Set ENVIRONMENT to 'production' before going live
   ===================================================== */

define('ENVIRONMENT', 'production');

if (ENVIRONMENT === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
