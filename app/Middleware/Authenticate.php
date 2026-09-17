<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/** Requires an authenticated, active user. */
final class Authenticate implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, ?string $parameter = null): Response
    {
        if (Auth::instance()->guest()) {
            Session::put('_intended', $request->path());
            throw HttpException::unauthorized('Please sign in to continue.');
        }

        return $next($request);
    }
}
