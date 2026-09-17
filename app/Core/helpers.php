<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Settings;
use App\Core\Url;

if (!function_exists('e')) {
    /** HTML-escape a value for safe output. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return Url::to($path);
    }
}

if (!function_exists('url_path')) {
    /** Root-relative URL -- use for iframes so the frame stays same-origin. */
    function url_path(string $path = ''): string
    {
        return Url::relative($path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return Url::asset($path);
    }
}

if (!function_exists('upload_url')) {
    function upload_url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        return Url::to('uploads/' . ltrim($path, '/'));
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
    }
}

if (!function_exists('auth')) {
    function auth(): Auth
    {
        return Auth::instance();
    }
}

if (!function_exists('user')) {
    function user(): ?array
    {
        return Auth::instance()->user();
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        $old = App\Core\Session::get('_old', []);

        return $old[$key] ?? $default;
    }
}

if (!function_exists('str_slug')) {
    function str_slug(string $value, string $separator = '-'): string
    {
        $value = trim($value);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false && trim($converted) !== '') {
                $value = $converted;
            }
        }
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', $separator, $value) ?? '';
        $value = trim($value, $separator);

        return $value === '' ? 'item' : substr($value, 0, 100);
    }
}

if (!function_exists('str_random')) {
    function str_random(int $length = 32): string
    {
        return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
    }
}

if (!function_exists('money')) {
    function money(float|int|string $amount, ?string $currency = null): string
    {
        $currency = $currency ?? (string) Settings::get('currency_symbol', '₹');

        return $currency . number_format((float) $amount, 2);
    }
}

if (!function_exists('array_get')) {
    function array_get(?array $array, string $key, mixed $default = null): mixed
    {
        if ($array === null) {
            return $default;
        }
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }
}

if (!function_exists('json_decode_safe')) {
    /** @return array<mixed> */
    function json_decode_safe(?string $json, array $default = []): array
    {
        if ($json === null || $json === '') {
            return $default;
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : $default;
    }
}

if (!function_exists('now')) {
    function now(string $format = 'Y-m-d H:i:s'): string
    {
        return date($format);
    }
}

if (!function_exists('is_installed')) {
    function is_installed(): bool
    {
        return is_file(INSTALL_LOCK);
    }
}

if (!function_exists('human_size')) {
    function human_size(int|float $bytes, int $decimals = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max((float) $bytes, 0);
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), $decimals) . ' ' . $units[$power];
    }
}

if (!function_exists('mask_secret')) {
    function mask_secret(?string $value, int $visible = 4): string
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        if (strlen($value) <= $visible) {
            return str_repeat('•', strlen($value));
        }

        return str_repeat('•', max(6, strlen($value) - $visible)) . substr($value, -$visible);
    }
}

if (!function_exists('icon')) {
    function icon(string $name, int $size = 20, string $class = ''): string
    {
        return App\Core\Icon::render($name, $size, $class);
    }
}
