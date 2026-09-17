<?php
/**
 * Framework bootstrap: autoloader, error handling, environment, constants.
 */

declare(strict_types=1);

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

// --- Error & exception handling -----------------------------------------
App\Core\ErrorHandler::register();

// --- Timezone ------------------------------------------------------------
date_default_timezone_set(App\Core\Env::get('APP_TIMEZONE', 'Asia/Kolkata'));

// --- Version -------------------------------------------------------------
require APP_PATH . '/version.php';
