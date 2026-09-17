<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Response;
use App\Core\Session;
use App\Core\Uploader;
use App\Models\Notification;
use App\Models\User;
use App\Services\SubscriptionService;
use Throwable;

final class AccountController extends PanelController
{
    public function index(): Response
    {
        return $this->render('customer.account', [
            'title'   => 'Account',
            'user'    => $this->currentUser(),
            'summary' => (new SubscriptionService())->summaryFor($this->userId()),
        ]);
    }

    public function updateProfile(): Response
    {
        $userId = $this->userId();

        $data = $this->validate([
            'name'    => 'required|string|min:2|max:150',
            'phone'   => 'required|phone|max:25',
            'company' => 'nullable|string|max:150',
            'gstin'   => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city'    => 'nullable|string|max:100',
            'state'   => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:12',
        ]);

        $file = $this->request->file('avatar');
        if ($file !== null) {
            try {
                $path = (new Uploader())->image($file, 'users', 400);
                $current = $this->currentUser();
                if (!empty($current['avatar'])) {
                    Uploader::delete((string) $current['avatar']);
                }
                $data['avatar'] = $path;
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
        }

        (new User())->updateById($userId, $data);
        AuditLog::record('user.profile_updated', 'user', $userId);

        $this->success('Your profile has been updated.');

        return $this->redirect('account');
    }

    public function updatePassword(): Response
    {
        $user = $this->currentUser();

        $data = $this->validate([
            'current_password' => 'required|string',
            'password'         => 'required|password|confirmed',
        ]);

        if (!password_verify((string) $data['current_password'], (string) $user['password'])) {
            $this->error('Your current password is incorrect.');

            return $this->redirect('account');
        }

        (new User())->updatePassword((int) $user['id'], (string) $data['password']);
        AuditLog::record('user.password_changed', 'user', (int) $user['id']);

        // The auth stamp changes with the password, ending every other session.
        Session::invalidate();
        Auth::instance()->logout();

        $this->success('Your password has been changed. Please sign in again.');

        return $this->redirect('login');
    }

    public function destroy(): Response
    {
        $user = $this->currentUser();

        if ($this->request->string('confirm') !== 'DELETE') {
            $this->error('Type DELETE to confirm that you want to remove your account.');

            return $this->redirect('account');
        }
        if (!password_verify($this->request->string('password'), (string) $user['password'])) {
            $this->error('Your password is incorrect.');

            return $this->redirect('account');
        }

        AuditLog::record('user.account_deleted', 'user', (int) $user['id'], ['email' => $user['email']], (int) $user['id']);
        (new User())->purge((int) $user['id']);

        Auth::instance()->logout();
        Session::invalidate();

        $this->success('Your account and all of its cards have been deleted.');

        return $this->redirect('/');
    }

    public function markNotificationsRead(): Response
    {
        (new Notification())->markAllRead($this->userId());

        return $this->ok('Notifications marked as read.');
    }
}
