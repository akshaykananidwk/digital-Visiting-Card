<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Response;
use App\Models\Card;
use App\Models\Model;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Reseller;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PlanLimiter;
use App\Services\SubscriptionService;
use Throwable;

final class UserController extends AdminController
{
    public function index(): Response
    {
        $filters = [
            'search' => $this->request->string('q'),
            'role'   => $this->request->string('role'),
            'status' => $this->request->string('status'),
        ];

        return $this->render('admin.users.index', [
            'title'   => 'Users',
            'result'  => (new User())->search($filters, $this->page(), 25),
            'filters' => $filters,
            'stats'   => (new User())->statistics(),
        ]);
    }

    public function create(): Response
    {
        return $this->render('admin.users.create', [
            'title'     => 'Create user',
            'plans'     => (new Plan())->active(),
            'resellers' => (new Reseller())->where(['is_active' => 1], 'company_name ASC'),
        ]);
    }

    public function store(): Response
    {
        $data = $this->validate([
            'name'        => 'required|string|min:2|max:150',
            'email'       => 'required|email|unique:users,email',
            'phone'       => 'nullable|phone|max:25',
            'password'    => 'required|password',
            'role'        => 'required|in:customer,reseller,admin,super_admin',
            'status'      => 'required|in:active,suspended,pending',
            'reseller_id' => 'nullable|integer',
            'plan_id'     => 'nullable|integer',
        ]);

        // Only a super admin may mint another administrator.
        if (in_array((string) $data['role'], [Auth::ROLE_ADMIN, Auth::ROLE_SUPER_ADMIN], true)) {
            $this->requireSuperAdmin();
        }

        $users = new User();
        $userId = $users->create([
            'uuid'        => Model::uuid(),
            'name'        => (string) $data['name'],
            'email'       => strtolower((string) $data['email']),
            'phone'       => $data['phone'] ?? null,
            'password'    => Auth::hash((string) $data['password']),
            'role'        => (string) $data['role'],
            'status'      => (string) $data['status'],
            'reseller_id' => !empty($data['reseller_id']) ? (int) $data['reseller_id'] : null,
            'created_at'  => now(),
        ]);

        if (!empty($data['plan_id'])) {
            try {
                (new SubscriptionService())->grant($userId, (int) $data['plan_id'], 'admin');
            } catch (Throwable $e) {
                $this->error('User created, but the plan could not be assigned: ' . $e->getMessage());
            }
        }

        AuditLog::record('admin.user_created', 'user', $userId, ['email' => $data['email'], 'role' => $data['role']]);
        $this->success('User created.');

        return $this->redirect('admin/users/' . $userId);
    }

    public function show(string $id): Response
    {
        $user = (new User())->find((int) $id);
        if ($user === null) {
            $this->error('User not found.');

            return $this->redirect('admin/users');
        }

        return $this->render('admin.users.show', [
            'title'        => $user['name'],
            'user'         => $user,
            'cards'        => (new Card())->forUser((int) $user['id']),
            'orders'       => (new Order())->forUser((int) $user['id'], 10),
            'subscription' => (new Subscription())->activeFor((int) $user['id']),
            'history'      => (new Subscription())->historyFor((int) $user['id'], 10),
            'plan'         => PlanLimiter::planFor((int) $user['id']),
            'plans'        => (new Plan())->active(),
            'resellers'    => (new Reseller())->where(['is_active' => 1], 'company_name ASC'),
            'canManage'    => Auth::instance()->outranks((string) $user['role']) || (int) $user['id'] === $this->userId(),
        ]);
    }

    public function update(string $id): Response
    {
        $users = new User();
        $user = $users->find((int) $id);
        if ($user === null) {
            return $this->redirect('admin/users');
        }
        $this->assertCanManage($user);

        $data = $this->validate([
            'name'        => 'required|string|min:2|max:150',
            'email'       => 'required|email',
            'phone'       => 'nullable|phone|max:25',
            'company'     => 'nullable|string|max:150',
            'gstin'       => 'nullable|string|max:20',
            'reseller_id' => 'nullable|integer',
        ]);

        if ($users->emailExists((string) $data['email'], (int) $user['id'])) {
            $this->error('Another account already uses that email address.');

            return $this->redirect('admin/users/' . (int) $user['id']);
        }

        $role = $this->request->string('role');
        if ($role !== '' && $role !== (string) $user['role']) {
            if (in_array($role, [Auth::ROLE_ADMIN, Auth::ROLE_SUPER_ADMIN], true) || (string) $user['role'] === Auth::ROLE_SUPER_ADMIN) {
                $this->requireSuperAdmin();
            }
            if (in_array($role, ['customer', 'reseller', 'admin', 'super_admin'], true)) {
                $data['role'] = $role;
            }
        }

        $data['email'] = strtolower((string) $data['email']);
        $data['reseller_id'] = !empty($data['reseller_id']) ? (int) $data['reseller_id'] : null;

        $users->updateById((int) $user['id'], $data);
        AuditLog::record('admin.user_updated', 'user', (int) $user['id'], array_diff_assoc(
            array_map('strval', array_filter($data, 'is_scalar')),
            array_map('strval', array_filter($user, 'is_scalar'))
        ));

        $this->success('User updated.');

        return $this->redirect('admin/users/' . (int) $user['id']);
    }

    public function updateStatus(string $id): Response
    {
        $users = new User();
        $user = $users->find((int) $id);
        if ($user === null) {
            return $this->redirect('admin/users');
        }
        $this->assertCanManage($user);

        if ((int) $user['id'] === $this->userId()) {
            $this->error('You cannot change the status of your own account.');

            return $this->redirect('admin/users/' . (int) $user['id']);
        }

        $status = $this->request->string('status');
        if (!in_array($status, ['active', 'suspended', 'pending'], true)) {
            $this->error('Unknown status.');

            return $this->redirect('admin/users/' . (int) $user['id']);
        }

        $users->updateById((int) $user['id'], ['status' => $status]);

        // Suspending an account must take its cards offline immediately.
        if ($status !== 'active') {
            $this->db()->execute(
                'UPDATE `' . $this->db()->table('cards') . "` SET `status` = 'suspended', `updated_at` = :now
                  WHERE `user_id` = :user AND `status` = 'published'",
                ['now' => now(), 'user' => (int) $user['id']]
            );
        }

        AuditLog::record('admin.user_status_changed', 'user', (int) $user['id'], ['status' => $status]);
        $this->success('Account status changed to ' . $status . '.');

        return $this->redirect('admin/users/' . (int) $user['id']);
    }

    public function resetPassword(string $id): Response
    {
        $users = new User();
        $user = $users->find((int) $id);
        if ($user === null) {
            return $this->redirect('admin/users');
        }
        $this->assertCanManage($user);

        $data = $this->validate(['password' => 'required|password']);
        $users->updatePassword((int) $user['id'], (string) $data['password']);

        AuditLog::record('admin.user_password_reset', 'user', (int) $user['id']);
        $this->success('Password reset. The user has been signed out of every device.');

        return $this->redirect('admin/users/' . (int) $user['id']);
    }

    public function assignPlan(string $id): Response
    {
        $user = (new User())->find((int) $id);
        if ($user === null) {
            return $this->redirect('admin/users');
        }
        $this->assertCanManage($user);

        $planId = $this->request->int('plan_id');
        if ($planId <= 0) {
            $this->error('Choose a plan.');

            return $this->redirect('admin/users/' . (int) $user['id']);
        }

        try {
            (new SubscriptionService())->grant((int) $user['id'], $planId, 'admin');
            $this->success('Plan assigned.');
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }

        return $this->redirect('admin/users/' . (int) $user['id']);
    }

    public function extendSubscription(string $id): Response
    {
        $user = (new User())->find((int) $id);
        if ($user === null) {
            return $this->redirect('admin/users');
        }
        $this->assertCanManage($user);

        $days = max(1, min(3650, $this->request->int('days', 30)));
        $subscription = (new Subscription())->activeFor((int) $user['id']);

        if ($subscription === null) {
            $this->error('This user has no active subscription to extend. Assign a plan first.');

            return $this->redirect('admin/users/' . (int) $user['id']);
        }

        (new SubscriptionService())->extend((int) $subscription['id'], $days);
        $this->success('Subscription extended by ' . $days . ' day(s).');

        return $this->redirect('admin/users/' . (int) $user['id']);
    }

    public function destroy(string $id): Response
    {
        $this->requireSuperAdmin();

        $users = new User();
        $user = $users->find((int) $id);
        if ($user === null) {
            return $this->redirect('admin/users');
        }
        if ((int) $user['id'] === $this->userId()) {
            $this->error('You cannot delete your own account.');

            return $this->redirect('admin/users/' . (int) $user['id']);
        }
        if ($this->request->string('confirm_email') !== (string) $user['email']) {
            $this->error('Type the exact email address to confirm deletion.');

            return $this->redirect('admin/users/' . (int) $user['id']);
        }

        AuditLog::record('admin.user_deleted', 'user', (int) $user['id'], ['email' => $user['email']]);
        $users->purge((int) $user['id']);

        $this->success('Account deleted and its personal data removed.');

        return $this->redirect('admin/users');
    }
}
