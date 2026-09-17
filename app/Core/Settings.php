<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Key/value application settings backed by the `settings` table, cached for
 * the lifetime of the request. Secret values are transparently encrypted.
 */
final class Settings
{
    /** @var array<string,mixed>|null */
    private static ?array $cache = null;

    /** @var array<int,string> Keys whose values are encrypted at rest. */
    public const SECRET_KEYS = [
        'razorpay_key_secret',
        'razorpay_webhook_secret',
        'smtp_password',
        'github_token',
    ];

    public static function load(bool $force = false): void
    {
        if (self::$cache !== null && !$force) {
            return;
        }
        self::$cache = [];

        if (!is_installed()) {
            return;
        }

        try {
            $rows = Database::instance()->select('SELECT `key`, `value`, `type` FROM ' . Database::instance()->table('settings'));
        } catch (Throwable) {
            return;
        }

        foreach ($rows as $row) {
            self::$cache[$row['key']] = self::castOut((string) $row['key'], $row['value'], (string) ($row['type'] ?? 'string'));
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        $value = self::$cache[$key] ?? null;

        if ($value === null || $value === '') {
            return $value === '' && $default === null ? '' : ($value ?? $default);
        }

        return $value;
    }

    public static function has(string $key): bool
    {
        self::load();

        return array_key_exists($key, self::$cache ?? []);
    }

    public static function set(string $key, mixed $value, string $group = 'general', string $type = 'string'): void
    {
        $db = Database::instance();
        $stored = self::castIn($key, $value, $type);

        $exists = $db->scalar('SELECT COUNT(*) FROM ' . $db->table('settings') . ' WHERE `key` = :key', ['key' => $key]);
        if ((int) $exists > 0) {
            $db->update('settings', ['value' => $stored, 'type' => $type, 'group' => $group, 'updated_at' => now()], ['key' => $key]);
        } else {
            $db->insert('settings', [
                'key'        => $key,
                'value'      => $stored,
                'type'       => $type,
                'group'      => $group,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        self::$cache[$key] = self::castOut($key, $stored, $type);
    }

    /** @param array<string,mixed> $values */
    public static function setMany(array $values, string $group = 'general'): void
    {
        foreach ($values as $key => $value) {
            $type = match (true) {
                is_bool($value)  => 'boolean',
                is_int($value)   => 'integer',
                is_float($value) => 'float',
                is_array($value) => 'json',
                default          => 'string',
            };
            self::set($key, $value, $group, $type);
        }
    }

    /** @return array<string,mixed> */
    public static function group(string $group): array
    {
        $db = Database::instance();
        $rows = $db->select('SELECT `key`, `value`, `type` FROM ' . $db->table('settings') . ' WHERE `group` = :group ORDER BY `key`', ['group' => $group]);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['key']] = self::castOut((string) $row['key'], $row['value'], (string) ($row['type'] ?? 'string'));
        }

        return $result;
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        self::load();

        return self::$cache ?? [];
    }

    public static function forget(string $key): void
    {
        Database::instance()->delete('settings', ['key' => $key]);
        unset(self::$cache[$key]);
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function isSecret(string $key): bool
    {
        return in_array($key, self::SECRET_KEYS, true);
    }

    private static function castIn(string $key, mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }
        $stored = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json'    => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            default   => (string) $value,
        };

        if (self::isSecret($key) && $stored !== '') {
            $stored = Crypto::encrypt($stored);
        }

        return $stored;
    }

    private static function castOut(string $key, ?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }
        if (self::isSecret($key) && $value !== '') {
            $value = Crypto::decrypt($value);
        }

        return match ($type) {
            'boolean' => in_array($value, ['1', 'true', 'on', 'yes'], true),
            'integer' => (int) $value,
            'float'   => (float) $value,
            'json'    => json_decode_safe($value),
            default   => $value,
        };
    }
}
