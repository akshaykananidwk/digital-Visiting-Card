<?php

declare(strict_types=1);

namespace App\Core;

/** URL generation that works both at a domain root and inside a subfolder. */
final class Url
{
    private static ?string $base = null;

    private static ?string $root = null;

    /** The sub-directory the app lives in, e.g. "" or "/cards". */
    public static function basePath(): string
    {
        if (self::$base !== null) {
            return self::$base;
        }

        $configured = (string) Config::get('app.url', '');
        if ($configured !== '') {
            $path = (string) (parse_url($configured, PHP_URL_PATH) ?? '');
            $path = rtrim($path, '/');
            if ($path !== '' && $path !== '/') {
                return self::$base = $path;
            }
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');

        return self::$base = ($dir === '/' ? '' : $dir);
    }

    /** Scheme + host (+ sub-directory) without a trailing slash. */
    public static function root(): string
    {
        if (self::$root !== null) {
            return self::$root;
        }

        $configured = rtrim((string) Config::get('app.url', ''), '/');
        if ($configured !== '') {
            return self::$root = $configured;
        }

        $request = Request::current();
        $scheme = $request !== null && $request->isSecure() ? 'https' : 'http';
        $host = $request?->host() ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return self::$root = $scheme . '://' . $host . self::basePath();
    }

    /** Override the root (used for reseller white-label domains). */
    public static function setRoot(string $root): void
    {
        self::$root = rtrim($root, '/');
    }

    public static function to(string $path = ''): string
    {
        if (preg_match('#^(https?:)?//#i', $path) === 1 || str_starts_with($path, 'mailto:') || str_starts_with($path, 'tel:')) {
            return $path;
        }

        $path = ltrim($path, '/');

        return $path === '' ? self::root() . '/' : self::root() . '/' . $path;
    }

    public static function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = BASE_PATH . '/' . $path;
        $version = is_file($file) ? substr((string) filemtime($file), -6) : substr(APP_VERSION, 0, 6);

        return self::to($path) . '?v=' . $version;
    }

    public static function card(string $slug): string
    {
        return self::to('card/' . $slug);
    }

    public static function current(): string
    {
        return Request::current()?->fullUrl() ?? self::root();
    }

    /** Only allow redirects that stay inside this application. */
    public static function safeRedirect(?string $target, string $fallback = '/'): string
    {
        if ($target === null || trim($target) === '') {
            return self::to($fallback);
        }
        $target = trim($target);

        if (str_starts_with($target, '//') || preg_match('#^[a-z][a-z0-9+.\-]*:#i', $target) === 1) {
            return self::to($fallback);
        }
        if (!str_starts_with($target, '/')) {
            return self::to($fallback);
        }

        $base = self::basePath();
        if ($base !== '' && str_starts_with($target, $base)) {
            $target = substr($target, strlen($base));
        }

        return self::to($target);
    }
}
