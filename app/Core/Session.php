<?php

declare(strict_types=1);

namespace App\Core;

/** Hardened session wrapper with flash-message and one-time token support. */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || PHP_SAPI === 'cli') {
            self::$started = true;

            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;

            return;
        }

        $path = (string) Config::get('app.session.path', STORAGE_PATH . '/sessions');
        if (is_dir($path) && is_writable($path)) {
            session_save_path($path);
        }

        $lifetime = (int) Config::get('app.session.lifetime', 7200);
        $secure = (bool) Config::get('app.session.secure', false) || (Request::current()?->isSecure() ?? false);

        session_name((string) Config::get('app.session.name', 'dvc_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => Url::basePath() === '' ? '/' : Url::basePath() . '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => (string) Config::get('app.session.samesite', 'Lax'),
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);

        // session.sid_length / sid_bits_per_character were deprecated in
        // PHP 8.4 (the engine now always uses a 32-byte random id).
        if (PHP_VERSION_ID < 80400) {
            @ini_set('session.sid_length', '48');
            @ini_set('session.sid_bits_per_character', '5');
        }

        session_start();
        self::$started = true;

        // Idle timeout
        $last = (int) (self::get('_last_activity', 0));
        if ($last > 0 && (time() - $last) > $lifetime) {
            self::invalidate();
        }
        self::put('_last_activity', time());

        // Periodic ID rotation limits session fixation windows.
        $rotated = (int) self::get('_rotated_at', 0);
        if ($rotated === 0) {
            self::put('_rotated_at', time());
        } elseif (time() - $rotated > 1800) {
            self::regenerate();
        }
    }

    public static function regenerate(): void
    {
        if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        session_regenerate_id(true);
        self::put('_rotated_at', time());
    }

    public static function invalidate(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
            session_start();
        }
        session_regenerate_id(true);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);

        return $value;
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        return $_SESSION ?? [];
    }

    // ------------------------------------------------------------- Flash --

    public static function flash(string $type, string $message): void
    {
        $messages = self::get('_flash', []);
        $messages[$type][] = $message;
        self::put('_flash', $messages);
    }

    /** @return array<string,array<int,string>> */
    public static function flashes(): array
    {
        return (array) self::pull('_flash', []);
    }

    /** @param array<string,mixed> $input */
    public static function flashInput(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['_token'], $input['current_password']);
        self::put('_old', $input);
    }

    /** @param array<string,array<int,string>> $errors */
    public static function flashErrors(array $errors): void
    {
        self::put('_errors', $errors);
    }

    /** @return array<string,array<int,string>> */
    public static function errors(): array
    {
        return (array) self::get('_errors', []);
    }

    /** Remove one-request-lifetime data at the end of the request cycle. */
    public static function ageFlashData(): void
    {
        self::forget('_errors');
        self::forget('_old');
    }
}
