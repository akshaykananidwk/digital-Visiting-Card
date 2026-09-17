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

        // SCRIPT_NAME is only meaningful here when it names the front
        // controller. Some server configurations report the request path
        // instead, and taking dirname() of that would scope the session
        // cookie to whatever directory the visitor happened to enter on and
        // strip a real path segment off every route. A sub-directory install
        // is configured through APP_URL above, which is checked first.
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (!str_ends_with(strtolower($script), '.php')) {
            return self::$base = '';
        }

        $dir = rtrim(dirname($script), '/');

        return self::$base = ($dir === '/' ? '' : $dir);
    }

    /** Scheme + host (+ sub-directory) without a trailing slash. */
    public static function root(): string
    {
        if (self::$root !== null) {
            return self::$root;
        }

        $configured = rtrim((string) Config::get('app.url', ''), '/');
        $request = Request::current();

        // Serve links, assets and AJAX endpoints from the host the visitor is
        // actually on, so the session cookie keeps travelling with them. Only
        // hosts this installation recognises are honoured -- an unknown Host
        // header falls back to APP_URL rather than being echoed back into
        // generated URLs, which is what makes header poisoning possible.
        if ($request !== null && self::isTrustedHost($request->host(), $configured)) {
            $scheme = $request->isSecure() ? 'https' : 'http';

            return self::$root = $scheme . '://' . $request->host() . self::basePath();
        }

        if ($configured !== '') {
            return self::$root = $configured;
        }

        $scheme = $request !== null && $request->isSecure() ? 'https' : 'http';
        $host = $request?->host() ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return self::$root = $scheme . '://' . $host . self::basePath();
    }

    /**
     * Hosts this installation answers on: the configured APP_URL host, its
     * www/non-www counterpart (the pair is the same site in practice), and
     * anything explicitly listed in APP_TRUSTED_HOSTS. Reseller white-label
     * domains do not go through here -- Tenant::resolve() verifies them
     * against the database and calls setRoot() directly.
     *
     * @param string $configured The configured APP_URL, if any.
     */
    private static function isTrustedHost(string $host, string $configured): bool
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return false;
        }

        $allowed = [];
        $configuredHost = strtolower((string) (parse_url($configured, PHP_URL_HOST) ?: ''));
        if ($configuredHost !== '') {
            $port = parse_url($configured, PHP_URL_PORT);
            $suffix = $port !== null ? ':' . (int) $port : '';
            $bare = str_starts_with($configuredHost, 'www.') ? substr($configuredHost, 4) : $configuredHost;
            $allowed[] = $bare . $suffix;
            $allowed[] = 'www.' . $bare . $suffix;
        }

        foreach (explode(',', (string) Env::get('APP_TRUSTED_HOSTS', '')) as $extra) {
            $extra = strtolower(trim($extra));
            if ($extra !== '') {
                $allowed[] = $extra;
            }
        }

        return in_array($host, $allowed, true);
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

    /**
     * Root-relative URL for the same application.
     *
     * to() builds absolute URLs from APP_URL (or the reseller root), which is
     * what emails, QR codes and vCards need. Embedded documents are the
     * opposite case: an iframe whose src carries a host the visitor is not
     * currently browsing is cross-origin, so the framed page's
     * "frame-ancestors 'self'" rejects its own parent. Keeping those URLs
     * relative keeps the frame same-origin on every host the site answers on.
     */
    public static function relative(string $path = ''): string
    {
        if (preg_match('#^(https?:)?//#i', $path) === 1) {
            return $path;
        }

        $path = ltrim($path, '/');

        return self::basePath() . '/' . $path;
    }

    /**
     * Collapse an absolute URL that points at this application back to a
     * root-relative one, so redirects keep the visitor on the host they are
     * actually browsing. Sending someone from www.example.com to
     * example.com mid-flow drops the session cookie and the login or
     * checkout they were half-way through. External URLs pass through
     * untouched.
     */
    public static function sameOrigin(string $url): string
    {
        if ($url === '' || !preg_match('#^(https?:)?//#i', $url)) {
            return $url;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || !self::isOwnHost($host)) {
            return $url;
        }

        $path = (string) ($parts['path'] ?? '/');
        $path = $path === '' ? '/' : $path;

        return $path
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    /** True when the hostname belongs to this installation. */
    private static function isOwnHost(string $host): bool
    {
        $hosts = [];
        foreach ([(string) Config::get('app.url', ''), self::$root ?? ''] as $candidate) {
            $candidateHost = strtolower((string) (parse_url($candidate, PHP_URL_HOST) ?: ''));
            if ($candidateHost !== '') {
                $hosts[] = $candidateHost;
            }
        }

        return in_array($host, $hosts, true);
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
