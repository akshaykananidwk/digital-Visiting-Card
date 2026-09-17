<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Database-backed rate limiter (works across PHP-FPM workers and on shared
 * hosting where APCu/Redis are unavailable).
 */
final class RateLimiter
{
    /**
     * @return array{allowed:bool,remaining:int,retry_after:int}
     */
    public static function hit(string $key, int $maxAttempts, int $decaySeconds): array
    {
        $hash = hash('sha256', $key);
        $db = Database::instance();
        $table = $db->table('rate_limits');

        try {
            $row = $db->selectOne("SELECT * FROM {$table} WHERE `key_hash` = :hash", ['hash' => $hash]);
            $now = time();

            if ($row === null || strtotime((string) $row['expires_at']) <= $now) {
                $db->execute(
                    "INSERT INTO {$table} (`key_hash`, `attempts`, `expires_at`, `created_at`)
                     VALUES (:hash, 1, :expires, :created)
                     ON DUPLICATE KEY UPDATE `attempts` = 1, `expires_at` = VALUES(`expires_at`)",
                    [
                        'hash'    => $hash,
                        'expires' => date('Y-m-d H:i:s', $now + $decaySeconds),
                        'created' => now(),
                    ]
                );

                return ['allowed' => true, 'remaining' => $maxAttempts - 1, 'retry_after' => 0];
            }

            $attempts = (int) $row['attempts'] + 1;
            $retryAfter = max(0, strtotime((string) $row['expires_at']) - $now);

            if ($attempts > $maxAttempts) {
                return ['allowed' => false, 'remaining' => 0, 'retry_after' => $retryAfter];
            }

            $db->execute("UPDATE {$table} SET `attempts` = :attempts WHERE `key_hash` = :hash", [
                'attempts' => $attempts,
                'hash'     => $hash,
            ]);

            return ['allowed' => true, 'remaining' => max(0, $maxAttempts - $attempts), 'retry_after' => $retryAfter];
        } catch (Throwable $e) {
            Logger::warning('Rate limiter unavailable: ' . $e->getMessage());

            // Fail open rather than locking the whole site out.
            return ['allowed' => true, 'remaining' => $maxAttempts, 'retry_after' => 0];
        }
    }

    /**
     * Throttle using a named profile from config/app.php.
     *
     * @throws HttpException when the limit is exceeded
     */
    public static function throttle(string $profile, string $identifier): void
    {
        [$max, $decay] = (array) Config::get('app.throttle.' . $profile, [60, 60]);
        $result = self::hit($profile . ':' . $identifier, (int) $max, (int) $decay);

        if (!$result['allowed']) {
            Logger::warning('Rate limit exceeded', ['profile' => $profile, 'identifier' => substr(hash('sha256', $identifier), 0, 12)]);
            throw HttpException::tooManyRequests(
                'Too many attempts. Please try again in ' . max(1, (int) ceil($result['retry_after'] / 60)) . ' minute(s).'
            );
        }
    }

    public static function attempts(string $key): int
    {
        $db = Database::instance();
        $row = $db->selectOne(
            'SELECT `attempts`, `expires_at` FROM ' . $db->table('rate_limits') . ' WHERE `key_hash` = :hash',
            ['hash' => hash('sha256', $key)]
        );
        if ($row === null || strtotime((string) $row['expires_at']) <= time()) {
            return 0;
        }

        return (int) $row['attempts'];
    }

    public static function clear(string $key): void
    {
        try {
            Database::instance()->delete('rate_limits', ['key_hash' => hash('sha256', $key)]);
        } catch (Throwable) {
            // ignore
        }
    }

    /** Housekeeping: drop expired buckets. */
    public static function purge(): int
    {
        try {
            $db = Database::instance();

            return $db->execute('DELETE FROM ' . $db->table('rate_limits') . ' WHERE `expires_at` < :now', ['now' => now()]);
        } catch (Throwable) {
            return 0;
        }
    }
}
