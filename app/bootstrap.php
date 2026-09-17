<?php
/**
 * Framework bootstrap: autoloader, error handling, environment, constants.
 */

declare(strict_types=1);

// Refuse to run when reached directly through the web server: this file is
// only ever included by the front controller.
if (PHP_SAPI !== 'cli' && !defined('DVC_START') && basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('DATABASE_PATH', BASE_PATH . '/database');
define('VIEW_PATH', APP_PATH . '/Views');
define('INSTALL_LOCK', STORAGE_PATH . '/installed.lock');

// --- PSR-4 style autoloader (no Composer required on shared hosting) -----
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/Core/helpers.php';

// --- Environment ---------------------------------------------------------
App\Core\Env::load(BASE_PATH . '/.env');

// Stop here rather than running on with no configuration at all. Continuing
// produces a database login failure for an empty user, which reads like wrong
// credentials and hides the real cause: the file is there, PHP just is not
// allowed to open it.
if (App\Core\Env::unreadable()) {
    $owner = function_exists('posix_getpwuid') && function_exists('fileowner')
        ? (posix_getpwuid((int) fileowner(BASE_PATH . '/.env'))['name'] ?? 'unknown')
        : 'unknown';
    $runningAs = function_exists('posix_geteuid') && function_exists('posix_getpwuid')
        ? (posix_getpwuid(posix_geteuid())['name'] ?? 'unknown')
        : 'unknown';
    $permissions = substr(sprintf('%o', (int) @fileperms(BASE_PATH . '/.env')), -4);

    $message = 'Configuration file .env exists but cannot be read. '
        . 'It is owned by "' . $owner . '" with permissions ' . $permissions
        . ', and PHP is running as "' . $runningAs . '". '
        . 'Give that user read access, for example: chown ' . $runningAs . ' .env && chmod 600 .env';

    error_log('[digital-visiting-card] ' . $message);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Configuration error\n\n";
    echo "The application's .env file exists but this server cannot read it.\n";
    echo "See the server error log for the owner, permissions and the user PHP runs as.\n";
    exit;
}

// --- Error & exception handling -----------------------------------------
App\Core\ErrorHandler::register();

// --- Timezone ------------------------------------------------------------
date_default_timezone_set(App\Core\Env::get('APP_TIMEZONE', 'Asia/Kolkata'));

// --- Version -------------------------------------------------------------
require APP_PATH . '/version.php';
