<?php
// One scenario per process so Url's static root cache starts clean.
// argv: appUrl, requestHost, trustedHosts
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Request;
use App\Core\Url;

[$script, $appUrl, $host, $trusted] = $argv + [null, '', '', ''];

$_SERVER['HTTP_HOST'] = $host;
$_SERVER['REQUEST_URI'] = '/login';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SERVER_PORT'] = str_contains($appUrl, 'https') ? '443' : '80';
if (str_starts_with($appUrl, 'https')) {
    $_SERVER['HTTPS'] = 'on';
}
\App\Core\Env::set('APP_TRUSTED_HOSTS', $trusted);

Config::set('app.url', $appUrl);
Request::capture();

echo Url::to('dashboard'), "\n";
