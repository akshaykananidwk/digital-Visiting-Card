<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;

/**
 * Maintenance mode. Administrators keep full access so they can finish an
 * update or a restore while visitors see the maintenance page.
 */
final class Maintenance implements MiddlewareInterface
{
    public const FLAG = 'maintenance.flag';

    public function handle(Request $request, callable $next, ?string $parameter = null): Response
    {
        if (!self::isActive()) {
            return $next($request);
        }

        if (Auth::instance()->isAdmin()) {
            return $next($request);
        }

        $path = $request->path();
        if (str_starts_with($path, '/login') || str_starts_with($path, '/logout') || str_starts_with($path, '/assets')) {
            return $next($request);
        }

        throw HttpException::serviceUnavailable((string) (self::payload()['message'] ?? 'We are performing scheduled maintenance.'));
    }

    public static function isActive(): bool
    {
        if (is_file(self::path())) {
            return true;
        }

        return (bool) Settings::get('maintenance_mode', false);
    }

    /** @param array<string,mixed> $context */
    public static function enable(string $message = 'We are performing scheduled maintenance. Please check back shortly.', array $context = []): bool
    {
        $payload = json_encode(['message' => $message, 'since' => now()] + $context, JSON_UNESCAPED_UNICODE);

        return file_put_contents(self::path(), (string) $payload, LOCK_EX) !== false;
    }

    public static function disable(): bool
    {
        if (!is_file(self::path())) {
            return true;
        }

        return @unlink(self::path());
    }

    /** @return array<string,mixed> */
    public static function payload(): array
    {
        if (!is_file(self::path())) {
            return [];
        }

        return json_decode_safe((string) @file_get_contents(self::path()));
    }

    public static function path(): string
    {
        return STORAGE_PATH . '/maintenance.flag';
    }
}
