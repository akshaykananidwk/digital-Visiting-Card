<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Loads PHP configuration arrays from /config lazily; values addressed with
 * dot notation, e.g. config('app.name') or config('database.host').
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];

    /** @var array<string,bool> */
    private static array $loaded = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);

        if (!isset(self::$loaded[$file])) {
            $path = CONFIG_PATH . '/' . $file . '.php';
            self::$items[$file] = is_file($path) ? (array) require $path : [];
            self::$loaded[$file] = true;
        }

        $value = self::$items[$file];
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);
        if (!isset(self::$loaded[$file])) {
            self::get($file . '.__probe__');
        }
        if ($segments === []) {
            self::$items[$file] = $value;

            return;
        }
        $ref = &self::$items[$file];
        foreach ($segments as $segment) {
            if (!is_array($ref)) {
                $ref = [];
            }
            if (!array_key_exists($segment, $ref)) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }
}
