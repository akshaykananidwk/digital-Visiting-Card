<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\AuditLog;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\Response;
use App\Core\Url;
use App\Models\User;
use Throwable;

/** Optional email verification. */
final class VerificationController extends Controller
{
    public function verify(string $token): Response
    {
        $db = Database::instance();
        $record = $db->selectOne(
            'SELECT * FROM `' . $db->table('email_verifications') . '`
              WHERE `token_hash` = :hash AND `verified_at` IS NULL AND `expires_at` > :now LIMIT 1',
            ['hash' => hash('sha256', $token), 'now' => now()]
        );

        if ($record === null) {
            $this->error('That verification link is invalid or has expired.');

            return $this->redirect('login');
        }

        $db->transaction(function () use ($db, $record): void {
            $db->update('email_verifications', ['verified_at' => now()], ['id' => (int) $record['id']]);
            $db->update('users', ['email_verified_at' => now()], ['id' => (int) $record['user_id']]);
        });

        AuditLog::record('user.email_verified', 'user', (int) $record['user_id'], [], (int) $record['user_id']);
        $this->success('Your email address has been verified.');

        return $this->redirect(auth()->check() ? 'dashboard' : 'login');
    }

    public function resend(): Response
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->redirect('login');
        }
        if (!empty($user['email_verified_at'])) {
            $this->info('Your email address is already verified.');

            return $this->back('account');
        }

        try {
            $token = bin2hex(random_bytes(32));
            $db = Database::instance();
            $db->insert('email_verifications', [
                'user_id'    => (int) $user['id'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + 86400),
                'created_at' => now(),
            ]);

            Mailer::send(
                (string) $user['email'],
                'Verify your email address',
                Mailer::layout(
                    'Confirm your email',
                    '<p>Please confirm your email address so we can send you lead notifications and invoices.</p>',
                    'Verify email address',
                    Url::to('verify-email/' . $token)
                ),
                null,
                ['user_id' => (int) $user['id'], 'event' => 'user.verify_email']
            );

            $this->success('We have sent a verification link to ' . (string) $user['email'] . '.');
        } catch (Throwable $e) {
            Logger::exception($e);
            $this->error('We could not send the verification email right now.');
        }

        return $this->back('account');
    }
}
