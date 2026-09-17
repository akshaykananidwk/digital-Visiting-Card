<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/** Permission gate: RequirePermission:admin.templates */
final class RequirePermission implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, ?string $parameter = null): Response
    {
        $auth = Auth::instance();

        if ($auth->guest()) {
            Session::put('_intended', $request->path());
            throw HttpException::unauthorized('Please sign in to continue.');
        }

        foreach (array_filter(array_map('trim', explode(',', (string) $parameter))) as $permission) {
            if ($auth->cannot($permission)) {
                throw HttpException::forbidden('You do not have permission to perform this action.');
            }
        }

        return $next($request);
    }
}
