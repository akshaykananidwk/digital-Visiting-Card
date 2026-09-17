<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Models\User;

/**
 * "Log in as customer" support tool.
 *
 * Every entry and exit is written to the audit trail, an administrator can
 * only impersonate an account they outrank, and the impersonation banner is
 * always visible while the session is active.
 */
final class ImpersonationController extends Controller
{
    public function start(string $id): Response
    {
        $auth = Auth::instance();
        $adminId = $auth->id();

        if ($adminId === null || !$auth->isAdmin()) {
            $this->error('Only an administrator can use this feature.');

            return $this->redirect('admin');
        }
        if ($auth->isImpersonating()) {
            $this->error('You are already impersonating another account. Exit first.');

            return $this->redirect('dashboard');
        }

        $target = (new User())->find((int) $id);
        if ($target === null) {
            $this->error('User not found.');

            return $this->redirect('admin/users');
        }

        if (!$auth->startImpersonation((int) $target['id'])) {
            $this->error('You cannot sign in as that account.');

            return $this->redirect('admin/users/' . (int) $target['id']);
        }

        AuditLog::record('admin.impersonation_started', 'user', (int) $target['id'], [
            'admin_id' => $adminId,
            'email'    => $target['email'],
        ], $adminId);

        $this->info('You are now signed in as ' . (string) $target['name'] . '. Use "Exit impersonation" to return to your account.');

        return $this->redirect('dashboard');
    }

    public function stop(): Response
    {
        $auth = Auth::instance();
        $impersonatedId = $auth->id();
        $adminId = $auth->impersonatorId();

        if (!$auth->stopImpersonation()) {
            $this->error('You are not impersonating anyone.');

            return $this->redirect('dashboard');
        }

        AuditLog::record('admin.impersonation_stopped', 'user', $impersonatedId, ['admin_id' => $adminId], $adminId);
        $this->success('You are back in your own account.');

        return $this->redirect('admin');
    }
}
