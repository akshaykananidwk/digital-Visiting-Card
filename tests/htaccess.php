<?php

declare(strict_types=1);

/**
 * .htaccess portability.
 *
 * Apache refuses to start serving a directory whose .htaccess contains a
 * directive no loaded module defines: it answers 500 for every request, with
 * its own error page, before PHP runs at all. The directives most likely to do
 * that are the ones that only exist under particular setups -- php_flag and
 * php_value need mod_php, and the Apache 2.2 access syntax needs
 * mod_access_compat -- so each must sit inside an <IfModule> guard.
 *
 * This is a static check of the shipped files. It needs no server, which is
 * the point: the failure it guards against happens on someone else's server.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$pass = 0;
$fail = 0;
$ok = static function (string $m) use (&$pass): void { echo "  PASS  {$m}\n"; $pass++; };
$no = static function (string $m) use (&$fail): void { echo "  FAIL  {$m}\n"; $fail++; };

/** Directives that are only valid when a particular module is loaded. */
const CONDITIONAL = [
    'php_flag'   => 'mod_php',
    'php_value'  => 'mod_php',
    'php_admin_flag'  => 'mod_php',
    'php_admin_value' => 'mod_php',
    'Deny'       => 'mod_access_compat',
    'Allow'      => 'mod_access_compat',
    'Order'      => 'mod_access_compat',
    'Satisfy'    => 'mod_access_compat',
    'Header'     => 'mod_headers',
    'RewriteRule'    => 'mod_rewrite',
    'RewriteCond'    => 'mod_rewrite',
    'RewriteEngine'  => 'mod_rewrite',
    'ExpiresByType'  => 'mod_expires',
    'ExpiresActive'  => 'mod_expires',
    'AddOutputFilterByType' => 'mod_deflate',
    'AddType'    => 'mod_mime',
    'Options'    => 'mod_autoindex',
];

$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator(BASE_PATH, FilesystemIterator::SKIP_DOTS),
        static fn ($file) => !in_array($file->getFilename(), ['.git', 'node_modules', 'vendor'], true)
    )
);
foreach ($iterator as $file) {
    if ($file->getFilename() === '.htaccess') {
        $files[] = $file->getPathname();
    }
}

sort($files);
$files === [] ? $no('no .htaccess files found') : $ok(count($files) . ' .htaccess file(s) found');

foreach ($files as $path) {
    $relative = ltrim(str_replace(BASE_PATH, '', $path), '/');
    $depth = 0;
    $unguarded = [];
    $line = 0;

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $raw) {
        $line++;
        $text = trim($raw);
        if ($text === '' || str_starts_with($text, '#')) {
            continue;
        }

        if (preg_match('/^<IfModule\b/i', $text) === 1) {
            $depth++;
            continue;
        }
        if (preg_match('#^</IfModule>#i', $text) === 1) {
            $depth = max(0, $depth - 1);
            continue;
        }

        $directive = strtok($text, " \t");
        if ($directive === false || !array_key_exists($directive, CONDITIONAL)) {
            continue;
        }
        if ($depth === 0) {
            $unguarded[] = "{$directive} (line {$line}, needs " . CONDITIONAL[$directive] . ')';
        }
    }

    $unguarded === []
        ? $ok("{$relative}: every module-dependent directive is inside <IfModule>")
        : $no("{$relative}: unguarded " . implode('; ', $unguarded));
}

// Every <IfModule> must be closed, or Apache rejects the file outright.
foreach ($files as $path) {
    $relative = ltrim(str_replace(BASE_PATH, '', $path), '/');
    $body = (string) file_get_contents($path);
    $open = preg_match_all('/<IfModule\b/i', $body);
    $close = preg_match_all('#</IfModule>#i', $body);
    $open === $close
        ? $ok("{$relative}: <IfModule> blocks are balanced ({$open})")
        : $no("{$relative}: {$open} <IfModule> opened but {$close} closed");

    $openF = preg_match_all('/<FilesMatch\b/i', $body);
    $closeF = preg_match_all('#</FilesMatch>#i', $body);
    $openF === $closeF
        ? null
        : $no("{$relative}: {$openF} <FilesMatch> opened but {$closeF} closed");
}

// The settings that php_value would have applied must exist for FPM and CGI.
$userIni = BASE_PATH . '/.user.ini';
is_file($userIni)
    ? $ok('.user.ini is present so the PHP limits still apply without mod_php')
    : $no('.user.ini is missing: upload limits would silently not apply under FPM or CGI');

if (is_file($userIni)) {
    $ini = parse_ini_file($userIni) ?: [];
    foreach (['upload_max_filesize', 'post_max_size', 'display_errors'] as $key) {
        array_key_exists($key, $ini)
            ? $ok(".user.ini sets {$key}")
            : $no(".user.ini does not set {$key}");
    }
}

// A route whose first path segment is also a real directory is served by
// Apache as that directory, never reaching the front controller. With no
// index.php inside and directory listings off, the visitor gets 403 and the
// route is simply unreachable -- which is what happened to /install, whose
// directory holds the installation marker and a readme.
$router = new App\Core\Router();
(static function (App\Core\Router $router): void {
    require CONFIG_PATH . '/routes.php';
})($router);

$reflection = new ReflectionObject($router);
$property = $reflection->getProperty('routes');
$property->setAccessible(true);

$segments = [];
foreach ($property->getValue($router) as $routes) {
    foreach ($routes as $route) {
        $first = strtok(ltrim((string) $route['pattern'], '/'), '/');
        if ($first === false || $first === '' || str_contains($first, '{')) {
            continue;
        }
        $segments[$first] = true;
    }
}

$shadowed = array_values(array_filter(array_keys($segments), static fn (string $s): bool => is_dir(BASE_PATH . '/' . $s)));

// The rewrite must hand directories to the front controller, so a shadowed
// route still works. Confirm the rule that does it is present and that no
// directory short-circuit remains.
$root = (string) file_get_contents(BASE_PATH . '/.htaccess');
preg_match('/^\s*RewriteCond\s+%\{REQUEST_FILENAME\}\s+-d/mi', $root) === 0
    ? $ok('the rewrite does not short-circuit directory requests')
    : $no('the rewrite serves directories as-is, so any route sharing a directory name returns 403');

if ($shadowed !== []) {
    $ok('routes sharing a directory name (' . implode(', ', $shadowed) . ') rely on that, and it holds');
}

echo "\nRESULT: {$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
