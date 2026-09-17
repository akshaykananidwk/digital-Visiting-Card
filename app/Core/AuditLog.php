<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/** Immutable trail of sensitive actions performed in the application. */
final class AuditLog
{
    /**
     * @param array<string,mixed> $context
     */
    public static function record(
        string $action,
        string $entityType = '',
        ?int $entityId = null,
        array $context = [],
        ?int $userId = null
    ): void {
        try {
            $request = Request::current();
            Database::instance()->insert('audit_logs', [
                'user_id'     => $userId ?? Auth::instance()->id(),
                'actor_role'  => Auth::instance()->role(),
                'action'      => substr($action, 0, 100),
                'entity_type' => substr($entityType, 0, 60),
                'entity_id'   => $entityId,
                'context'     => $context === [] ? null : json_encode(self::scrub($context), JSON_UNESCAPED_UNICODE),
                'ip_address'  => $request?->ip() ?? '',
                'user_agent'  => $request?->userAgent() ?? '',
                'created_at'  => now(),
            ]);
        } catch (Throwable $e) {
            Logger::warning('Audit log write failed: ' . $e->getMessage(), ['action' => $action]);
        }
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function scrub(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $context[$key] = self::scrub($value);

                continue;
            }
            if (preg_match('/pass|secret|token|key_secret/i', (string) $key) === 1) {
                $context[$key] = '***redacted***';
            }
        }

        return $context;
    }
}
