<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/** Role gate: RequireRole:admin,super_admin */
final class RequireRole implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, ?string $parameter = null): Response
    {
        $auth = Auth::instance();

        if ($auth->guest()) {
            Session::put('_intended', $request->path());
            throw HttpException::unauthorized('Please sign in to continue.');
        }

        $roles = array_filter(array_map('trim', explode(',', (string) $parameter)));
        if ($roles !== [] && !$auth->is(...$roles)) {
            throw HttpException::forbidden('Your account does not have access to this area.');
        }

        return $next($request);
    }
}
