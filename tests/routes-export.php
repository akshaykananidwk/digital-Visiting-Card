<?php

declare(strict_types=1);

/**
 * Print the application's route table as JSON, so the browser suites walk the
 * real routes instead of a hand-maintained list that drifts out of date.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Router;

$router = new Router();
(static function (Router $router): void {
    require CONFIG_PATH . '/routes.php';
})($router);

$reflection = new ReflectionObject($router);
$property = $reflection->getProperty('routes');
$property->setAccessible(true);

$out = [];
foreach ($property->getValue($router) as $method => $routes) {
    foreach ($routes as $route) {
        $out[] = [
            'method'     => $method,
            'pattern'    => $route['pattern'],
            'handler'    => is_string($route['handler']) ? $route['handler'] : 'closure',
            'middleware' => $route['middleware'] ?? [],
            'name'       => $route['name'] ?? null,
        ];
    }
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
