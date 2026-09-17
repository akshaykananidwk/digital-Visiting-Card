<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;

/** Rejects state-changing requests without a valid CSRF token. */
final class VerifyCsrf implements MiddlewareInterface
{
    /** Routes exempted because they authenticate by signature instead. */
    private const EXCLUDED = [
        '/webhooks/razorpay',
    ];

    public function handle(Request $request, callable $next, ?string $parameter = null): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        foreach (self::EXCLUDED as $path) {
            if (str_starts_with($request->path(), $path)) {
                return $next($request);
            }
        }

        // PHP discards the whole request body when it exceeds post_max_size,
        // so the token disappears along with it and the visitor is told their
        // session expired. Naming the real cause keeps someone uploading a
        // large photo from chasing a phantom login problem.
        if ($this->exceededPostLimit($request)) {
            throw HttpException::payloadTooLarge(
                'That upload is larger than this server accepts (' . ini_get('post_max_size')
                . ' maximum). Please choose a smaller file.'
            );
        }

        if (!Csrf::verify(Csrf::fromRequest($request))) {
            Logger::warning('CSRF token mismatch', ['path' => $request->path(), 'ip' => $request->ip()]);
            throw HttpException::tokenMismatch('Your session expired. Please reload the page and try again.');
        }

        return $next($request);
    }

    /**
     * A POST body larger than post_max_size arrives with every field stripped:
     * $_POST and $_FILES are empty while Content-Length still shows the real
     * size.
     */
    private function exceededPostLimit(Request $request): bool
    {
        $length = (int) $request->header('Content-Length', '0');
        if ($length <= 0 || $_POST !== [] || $_FILES !== []) {
            return false;
        }

        $limit = self::toBytes((string) ini_get('post_max_size'));

        return $limit > 0 && $length > $limit;
    }

    /** Convert a php.ini shorthand size such as "8M" to bytes. */
    private static function toBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $number = (int) $value;
        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
