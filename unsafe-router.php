<?php
// Deliberately permissive router: simulates a MISCONFIGURED server that does
// not deny /app, to prove the in-file guard is a real second line of defence.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$file = __DIR__ . $path;
if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}
require __DIR__ . '/index.php';
