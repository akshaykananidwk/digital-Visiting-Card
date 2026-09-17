<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;

/** Shared behaviour for every admin screen. */
abstract class AdminController extends Controller
{
    /** @return array<string,mixed> */
    protected function currentUser(): array
    {
        $user = Auth::instance()->user();
        if ($user === null) {
            throw HttpException::unauthorized();
        }

        return $user;
    }

    protected function userId(): int
    {
        return (int) $this->currentUser()['id'];
    }

    protected function requireSuperAdmin(): void
    {
        if (!Auth::instance()->isSuperAdmin()) {
            throw HttpException::forbidden('Only a super administrator can perform this action.');
        }
    }

    /** Guard against an administrator acting on an account that outranks them. */
    protected function assertCanManage(array $target): void
    {
        $auth = Auth::instance();

        if ((int) $target['id'] === $auth->id()) {
            return;
        }
        if (!$auth->outranks((string) $target['role'])) {
            throw HttpException::forbidden('You cannot manage an account at or above your own role.');
        }
    }

    protected function page(): int
    {
        return max(1, $this->request->int('page', 1));
    }
}
