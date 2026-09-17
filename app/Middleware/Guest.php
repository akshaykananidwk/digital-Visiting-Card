<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;

/** Redirects authenticated users away from login/register screens. */
final class Guest implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, ?string $parameter = null): Response
    {
        $auth = Auth::instance();
        if ($auth->check()) {
            return Response::redirect(Url::to(match (true) {
                $auth->isAdmin()    => 'admin',
                $auth->isReseller() => 'reseller',
                default             => 'dashboard',
            }));
        }

        return $next($request);
    }
}
