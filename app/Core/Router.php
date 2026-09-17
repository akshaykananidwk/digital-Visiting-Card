<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * Route table with {param} placeholders, per-route middleware and named
 * groups. Routes are declared in config/routes.php.
 */
final class Router
{
    /** @var array<string,array<int,array{pattern:string,regex:string,params:array<int,string>,handler:mixed,middleware:array<int,string>,name:?string}>> */
    private array $routes = [];

    /** @var array<string,string> */
    private array $names = [];

    /** @var array<int,string> */
    private array $groupMiddleware = [];

    private string $groupPrefix = '';

    public function get(string $pattern, mixed $handler): RouteDefinition
    {
        return $this->add(['GET', 'HEAD'], $pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): RouteDefinition
    {
        return $this->add(['POST'], $pattern, $handler);
    }

    public function put(string $pattern, mixed $handler): RouteDefinition
    {
        return $this->add(['PUT'], $pattern, $handler);
    }

    public function patch(string $pattern, mixed $handler): RouteDefinition
    {
        return $this->add(['PATCH'], $pattern, $handler);
    }

    public function delete(string $pattern, mixed $handler): RouteDefinition
    {
        return $this->add(['DELETE'], $pattern, $handler);
    }

    public function any(string $pattern, mixed $handler): RouteDefinition
    {
        return $this->add(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], $pattern, $handler);
    }

    /**
     * @param array{prefix?:string,middleware?:array<int,string>} $attributes
     */
    public function group(array $attributes, Closure $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix .= '/' . trim((string) ($attributes['prefix'] ?? ''), '/');
        $this->groupPrefix = rtrim($this->groupPrefix, '/');
        $this->groupMiddleware = array_merge($this->groupMiddleware, $attributes['middleware'] ?? []);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /** @param array<int,string> $methods */
    private function add(array $methods, string $pattern, mixed $handler): RouteDefinition
    {
        $pattern = $this->groupPrefix . '/' . trim($pattern, '/');
        $pattern = '/' . trim($pattern, '/');
        if ($pattern === '//') {
            $pattern = '/';
        }

        $params = [];
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::((?:[^{}]|\{[^{}]*\})+))?\}#',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];

                return '(' . ($m[2] ?? '[^/]+') . ')';
            },
            str_replace('#', '\#', $pattern)
        ) ?? $pattern;

        $route = [
            'pattern'    => $pattern,
            'regex'      => '#^' . $regex . '$#u',
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => $this->groupMiddleware,
            'name'       => null,
        ];

        $keys = [];
        foreach ($methods as $method) {
            $this->routes[$method][] = $route;
            $keys[$method] = array_key_last($this->routes[$method]);
        }

        return new RouteDefinition($this, $keys);
    }

    /** @internal */
    public function decorate(array $keys, string $property, mixed $value): void
    {
        foreach ($keys as $method => $index) {
            if ($property === 'middleware') {
                $this->routes[$method][$index]['middleware'] = array_merge(
                    $this->routes[$method][$index]['middleware'],
                    (array) $value
                );
            } else {
                $this->routes[$method][$index][$property] = $value;
                if ($property === 'name' && is_string($value)) {
                    $this->names[$value] = $this->routes[$method][$index]['pattern'];
                }
            }
        }
    }

    /**
     * @return array{handler:mixed,middleware:array<int,string>,params:array<string,string>}|null
     */
    public function match(string $method, string $path): ?array
    {
        $candidates = $this->routes[strtoupper($method)] ?? [];
        foreach ($candidates as $route) {
            if (preg_match($route['regex'], $path, $matches) === 1) {
                array_shift($matches);
                $params = [];
                foreach ($route['params'] as $index => $name) {
                    $params[$name] = $matches[$index] ?? '';
                }

                return [
                    'handler'    => $route['handler'],
                    'middleware' => $route['middleware'],
                    'params'     => $params,
                ];
            }
        }

        return null;
    }

    /** @return array<int,string> Methods that would match this path. */
    public function allowedMethods(string $path): array
    {
        $allowed = [];
        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $route) {
                if (preg_match($route['regex'], $path) === 1) {
                    $allowed[] = $method;
                    break;
                }
            }
        }

        return array_values(array_unique($allowed));
    }

    /** @param array<string,string|int> $params */
    public function route(string $name, array $params = []): string
    {
        $pattern = $this->names[$name] ?? '/';
        foreach ($params as $key => $value) {
            $pattern = preg_replace('#\{' . preg_quote((string) $key, '#') . '(?::[^}]+)?\}#', rawurlencode((string) $value), $pattern) ?? $pattern;
        }

        return Url::to($pattern);
    }

    public function hasName(string $name): bool
    {
        return isset($this->names[$name]);
    }

    public function count(): int
    {
        $total = 0;
        foreach ($this->routes as $routes) {
            $total += count($routes);
        }

        return $total;
    }
}
