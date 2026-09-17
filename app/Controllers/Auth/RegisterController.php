<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\AuditLog;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Tenant;
use App\Core\Url;
use App\Models\Model;
use App\Models\Plan;
use App\Models\User;
use App\Services\SubscriptionService;
use Throwable;

final class RegisterController extends Controller
{
    public function show(): Response
    {
        if (!(bool) Settings::get('allow_registration', true)) {
            $this->error('New registrations are currently closed. Please contact support.');

            return $this->redirect('login');
        }

        return $this->render('auth.register', ['title' => 'Create your account']);
    }

    public function register(): Response
    {
        if (!(bool) Settings::get('allow_registration', true)) {
            $this->error('New registrations are currently closed.');

            return $this->redirect('login');
        }

        $data = $this->validate([
            'name'     => 'required|string|min:2|max:150',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'required|phone|max:25',
            'password' => 'required|password|confirmed',
            'terms'    => 'required',
        ], [
            'terms.required' => 'Please accept the terms of service to continue.',
            'email.unique'   => 'An account with this email address already exists. Try signing in instead.',
        ]);

        $db = Database::instance();

        try {
            $userId = $db->transaction(function () use ($data): int {
                $users = new User();
                $id = $users->create([
                    'uuid'            => Model::uuid(),
                    'name'            => (string) $data['name'],
                    'email'           => strtolower((string) $data['email']),
                    'phone'           => (string) $data['phone'],
                    'password'        => Auth::hash((string) $data['password']),
                    'role'            => Auth::ROLE_CUSTOMER,
                    'status'          => 'active',
                    'reseller_id'     => Tenant::resellerId(),
                    'onboarding_step' => 1,
                    'created_at'      => now(),
                ]);

                // Everyone starts on the free plan so limits resolve from day one.
                $free = (new Plan())->freePlan();
                if ($free !== null) {
                    (new SubscriptionService())->grant($id, (int) $free['id'], 'registration');
                }

                return $id;
            });
        } catch (Throwable $e) {
            Logger::exception($e);
            $this->error('We could not create your account. Please try again.');

            return $this->redirect('register');
        }

        $user = (new User())->find($userId);
        if ($user === null) {
            $this->error('We could not create your account. Please try again.');

            return $this->redirect('register');
        }

        AuditLog::record('user.registered', 'user', $userId, ['email' => $user['email']], $userId);
        $this->sendWelcome($user);
        $this->notifyAdmin($user);

        Auth::instance()->login($user);
        $this->success('Welcome! Let\'s build your first digital card.');

        return $this->redirect('onboarding');
    }

    /** @param array<string,mixed> $user */
    private function sendWelcome(array $user): void
    {
        try {
            $branding = Tenant::branding();
            $body = '<p>Hi ' . e((string) $user['name']) . ',</p>'
                . '<p>Your account on <strong>' . e($branding['name']) . '</strong> is ready. '
                . 'You can create your digital visiting card in about five minutes and share it on WhatsApp instantly.</p>';

            Mailer::send(
                (string) $user['email'],
                'Welcome to ' . $branding['name'],
                Mailer::layout('Welcome aboard', $body, 'Open my dashboard', Url::to('dashboard')),
                null,
                ['user_id' => (int) $user['id'], 'event' => 'user.registered']
            );
        } catch (Throwable $e) {
            Logger::warning('Welcome email failed: ' . $e->getMessage());
        }
    }

    /** @param array<string,mixed> $user */
    private function notifyAdmin(array $user): void
    {
        if (!(bool) Settings::get('notify_admin_on_signup', true)) {
            return;
        }
        $adminEmail = (string) (Settings::get('support_email') ?: '');
        if ($adminEmail === '') {
            return;
        }

        try {
            Mailer::send(
                $adminEmail,
                'New signup: ' . (string) $user['name'],
                Mailer::layout('New customer registered', sprintf(
                    '<p><strong>%s</strong><br>%s<br>%s</p>',
                    e((string) $user['name']),
                    e((string) $user['email']),
                    e((string) ($user['phone'] ?? ''))
                )),
                null,
                ['event' => 'admin.signup_notice']
            );
        } catch (Throwable $e) {
            Logger::warning('Admin signup notice failed: ' . $e->getMessage());
        }
    }
}
