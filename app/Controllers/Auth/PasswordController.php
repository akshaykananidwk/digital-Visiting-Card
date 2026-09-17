<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\AuditLog;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\RateLimiter;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Models\User;
use Throwable;

/**
 * Password reset. Tokens are single-use, hashed at rest and expire after one
 * hour. The response is identical whether or not the address exists so the
 * form cannot be used to enumerate accounts.
 */
final class PasswordController extends Controller
{
    private const TOKEN_TTL_MINUTES = 60;

    public function showForgot(): Response
    {
        return $this->render('auth.forgot', ['title' => 'Reset your password']);
    }

    public function sendReset(): Response
    {
        $data = $this->validate(['email' => 'required|email']);
        $email = strtolower((string) $data['email']);

        RateLimiter::throttle('forgot', 'acct:' . $email);

        $user = (new User())->findByEmail($email);

        if ($user !== null && (string) $user['status'] === 'active') {
            try {
                $token = bin2hex(random_bytes(32));
                $db = Database::instance();

                $db->execute(
                    'UPDATE `' . $db->table('password_resets') . '` SET `used_at` = :now WHERE `email` = :email AND `used_at` IS NULL',
                    ['now' => now(), 'email' => $email]
                );

                $db->insert('password_resets', [
                    'email'      => $email,
                    'token_hash' => hash('sha256', $token),
                    'ip_address' => $this->request->ip(),
                    'expires_at' => date('Y-m-d H:i:s', time() + self::TOKEN_TTL_MINUTES * 60),
                    'created_at' => now(),
                ]);

                $link = Url::to('reset-password/' . $token);
                $body = '<p>We received a request to reset the password for your account.</p>'
                    . '<p>This link is valid for ' . self::TOKEN_TTL_MINUTES . ' minutes and can be used once. '
                    . 'If you did not request it you can safely ignore this email.</p>';

                Mailer::send(
                    $email,
                    'Reset your password',
                    Mailer::layout('Password reset', $body, 'Choose a new password', $link),
                    "Reset your password: {$link}",
                    ['user_id' => (int) $user['id'], 'event' => 'password.reset_requested']
                );

                AuditLog::record('password.reset_requested', 'user', (int) $user['id'], [], (int) $user['id']);
            } catch (Throwable $e) {
                Logger::exception($e);
            }
        }

        $this->success('If an account exists for that address, we have sent a password reset link.');

        return $this->redirect('forgot-password');
    }

    public function showReset(string $token): Response
    {
        $record = $this->findToken($token);

        if ($record === null) {
            $this->error('That password reset link is invalid or has expired. Please request a new one.');

            return $this->redirect('forgot-password');
        }

        return $this->render('auth.reset', [
            'title' => 'Choose a new password',
            'token' => $token,
            'email' => (string) $record['email'],
        ]);
    }

    public function resetPassword(): Response
    {
        $data = $this->validate([
            'token'    => 'required|string|max:200',
            'password' => 'required|password|confirmed',
        ]);

        $record = $this->findToken((string) $data['token']);
        if ($record === null) {
            $this->error('That password reset link is invalid or has expired.');

            return $this->redirect('forgot-password');
        }

        $users = new User();
        $user = $users->findByEmail((string) $record['email']);
        if ($user === null) {
            $this->error('That account no longer exists.');

            return $this->redirect('forgot-password');
        }

        $db = Database::instance();
        $db->transaction(function () use ($db, $users, $user, $record, $data): void {
            $users->updatePassword((int) $user['id'], (string) $data['password']);
            $db->update('password_resets', ['used_at' => now()], ['id' => (int) $record['id']]);
        });

        AuditLog::record('password.reset_completed', 'user', (int) $user['id'], [], (int) $user['id']);
        Session::invalidate();

        $this->success('Your password has been updated. Please sign in.');

        return $this->redirect('login');
    }

    /** @return array<string,mixed>|null */
    private function findToken(string $token): ?array
    {
        if ($token === '' || strlen($token) > 200) {
            return null;
        }
        $db = Database::instance();

        return $db->selectOne(
            'SELECT * FROM `' . $db->table('password_resets') . '`
              WHERE `token_hash` = :hash AND `used_at` IS NULL AND `expires_at` > :now LIMIT 1',
            ['hash' => hash('sha256', $token), 'now' => now()]
        );
    }
}
