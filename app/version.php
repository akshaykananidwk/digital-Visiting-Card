<?php
/**
 * Central application version. The GitHub auto-update system compares this
 * value (and the recorded commit hash) against the remote repository.
 */

declare(strict_types=1);

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}
if (!defined('APP_NAME_DEFAULT')) {
    define('APP_NAME_DEFAULT', 'Digital Visiting Card');
}
if (!defined('APP_RELEASE_DATE')) {
    define('APP_RELEASE_DATE', '2026-09-17');
}
