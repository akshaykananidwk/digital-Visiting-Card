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

        if (!Csrf::verify(Csrf::fromRequest($request))) {
            Logger::warning('CSRF token mismatch', ['path' => $request->path(), 'ip' => $request->ip()]);
            throw HttpException::tokenMismatch('Your session expired. Please reload the page and try again.');
        }

        return $next($request);
    }
}
