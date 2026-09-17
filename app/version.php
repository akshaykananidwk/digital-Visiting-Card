<?php
/**
 * Central application version. The GitHub auto-update system compares this
 * value (and the recorded commit hash) against the remote repository.
 */

declare(strict_types=1);

// Refuse to run when reached directly through the web server: this file is
// only ever included by the front controller.
if (PHP_SAPI !== 'cli' && !defined('DVC_START') && basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}
if (!defined('APP_NAME_DEFAULT')) {
    define('APP_NAME_DEFAULT', 'Digital Visiting Card');
}
if (!defined('APP_RELEASE_DATE')) {
    define('APP_RELEASE_DATE', '2026-09-17');
}
