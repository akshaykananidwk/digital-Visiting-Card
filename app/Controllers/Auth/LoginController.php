<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\AuditLog;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;

final class LoginController extends Controller
{
    public function show(): Response
    {
        return $this->render('auth.login', ['title' => 'Sign in']);
    }

    public function login(): Response
    {
        $data = $this->validate([
            'email'    => 'required|email',
            'password' => 'required|string|max:200',
        ]);

        $email = strtolower((string) $data['email']);
        $ip = $this->request->ip();

        // Throttle per account as well as per IP so one attacker cannot lock
        // out every account from a single address.
        RateLimiter::throttle('login', 'acct:' . $email);

        $user = Auth::instance()->validateCredentials($email, (string) $data['password']);

        if ($user === null) {
            Logger::warning('Failed login attempt', ['email' => $email, 'ip' => $ip]);
            AuditLog::record('auth.login_failed', 'user', null, ['email' => $email]);
            $this->error('The email address or password is incorrect.');
            Session::flashInput(['email' => $email]);

            return $this->redirect('login');
        }

        if ((string) $user['status'] !== 'active') {
            AuditLog::record('auth.login_blocked', 'user', (int) $user['id'], ['status' => $user['status']]);
            $this->error($user['status'] === 'suspended'
                ? 'Your account has been suspended. Please contact support.'
                : 'Your account is not active yet. Please verify your email address.');

            return $this->redirect('login');
        }

        RateLimiter::clear('login:acct:' . $email);
        RateLimiter::clear('login:ip' . $ip);

        Auth::instance()->login($user);
        AuditLog::record('auth.login', 'user', (int) $user['id']);

        $intended = Session::pull('_intended');
        if (is_string($intended) && $intended !== '' && !str_starts_with($intended, '/login')) {
            return $this->redirectAway(Url::safeRedirect($intended, $this->landingFor($user)));
        }

        return $this->redirect($this->landingFor($user));
    }

    public function logout(): Response
    {
        $id = Auth::instance()->id();
        Auth::instance()->logout();
        Session::invalidate();

        if ($id !== null) {
            AuditLog::record('auth.logout', 'user', $id, [], $id);
        }

        $this->success('You have been signed out.');

        return $this->redirect('login');
    }

    /** @param array<string,mixed> $user */
    private function landingFor(array $user): string
    {
        return match ((string) $user['role']) {
            Auth::ROLE_SUPER_ADMIN, Auth::ROLE_ADMIN => 'admin',
            Auth::ROLE_RESELLER                      => 'reseller',
            default                                  => (int) ($user['onboarding_step'] ?? 0) >= 5 ? 'dashboard' : 'onboarding',
        };
    }
}
