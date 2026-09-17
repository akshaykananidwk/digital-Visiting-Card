<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal, dependency-free .env parser.
 *
 * Values are cached in a static array (never in $_ENV / getenv() so they are
 * not leaked through phpinfo() or process listings on shared hosting).
 */
final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];

    private static bool $loaded = false;

    /** Set when .env exists but PHP was not allowed to read it. */
    private static bool $unreadable = false;

    public static function load(string $path): void
    {
        self::$loaded = true;
        self::$unreadable = false;

        // No file at all is the normal state before installation.
        if (!is_file($path)) {
            return;
        }

        // A file that exists but cannot be read is a misconfiguration, and the
        // worst thing to do is carry on quietly: every setting would be empty
        // and the first symptom is a database login failure for user '',
        // which sends people hunting through database credentials that were
        // correct all along. It happens whenever .env is chmod 600 but owned
        // by a different user than the one PHP runs as, which is the usual
        // arrangement on shared hosting.
        $lines = is_readable($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
        if ($lines === false) {
            self::$unreadable = true;

            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // Strip surrounding quotes, honouring escaped characters.
            if (strlen($value) > 1) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                    if ($first === '"') {
                        $value = str_replace(['\\n', '\\r', '\\"', '\\\\'], ["\n", "\r", '"', '\\'], $value);
                    }
                }
            }

            self::$vars[$key] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, self::$vars)) {
            return $default;
        }

        $value = self::$vars[$key];

        return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }

    public static function set(string $key, string $value): void
    {
        self::$vars[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$vars);
    }

    public static function loaded(): bool
    {
        return self::$loaded;
    }

    /** True when .env is present but PHP could not read it. */
    public static function unreadable(): bool
    {
        return self::$unreadable;
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        return self::$vars;
    }

    /**
     * Persist (create or update) a set of keys inside the .env file while
     * preserving comments and ordering of the untouched lines.
     *
     * @param array<string,string|int|bool|null> $values
     */
    public static function write(string $path, array $values): bool
    {
        $existing = is_file($path) ? (file($path, FILE_IGNORE_NEW_LINES) ?: []) : [];
        $handled = [];

        foreach ($existing as $index => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $pos = strpos($trimmed, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($trimmed, 0, $pos));
            if (array_key_exists($key, $values)) {
                $existing[$index] = $key . '=' . self::encode($values[$key]);
                $handled[$key] = true;
            }
        }

        foreach ($values as $key => $value) {
            if (!isset($handled[$key])) {
                $existing[] = $key . '=' . self::encode($value);
            }
            self::$vars[$key] = (string) $value;
        }

        $content = implode(PHP_EOL, $existing) . PHP_EOL;

        $dir = dirname($path);
        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }

        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            return false;
        }
        @chmod($tmp, 0600);

        return rename($tmp, $path);
    }

    private static function encode(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        $value = (string) $value;
        if ($value === '' || preg_match('/[\s#"\'=]/', $value) === 1) {
            return '"' . str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value) . '"';
        }

        return $value;
    }
}
