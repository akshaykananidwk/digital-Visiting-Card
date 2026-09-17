<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;

/** Applies a named throttle profile: Throttle:api */
final class Throttle implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, ?string $parameter = null): Response
    {
        $profile = $parameter ?? 'api';
        $identity = Auth::instance()->id() !== null ? 'u' . Auth::instance()->id() : 'ip' . $request->ip();

        RateLimiter::throttle($profile, $identity);

        return $next($request);
    }
}
