<?php

declare(strict_types=1);

namespace App\Controllers\Reseller;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Response;
use App\Models\Card;
use App\Models\Model;
use App\Models\Plan;
use App\Models\Reseller;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PlanLimiter;
use App\Services\SubscriptionService;
use App\Services\WalletService;
use Throwable;

/** Reseller customer management, with wallet-funded plan activation. */
final class CustomerController extends ResellerPanelController
{
    public function index(): Response
    {
        $filters = [
            'search'      => $this->request->string('q'),
            'status'      => $this->request->string('status'),
            'reseller_id' => $this->resellerId(),
        ];

        return $this->render('reseller.customers.index', [
            'title'   => 'My customers',
            'result'  => (new User())->search($filters, $this->page(), 25),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $reseller = $this->reseller();
        $wallet = new WalletService();
        $plans = (new Plan())->active();

        foreach ($plans as $index => $plan) {
            $plans[$index]['your_price'] = $wallet->resellerPrice($plan, $reseller);
        }

        return $this->render('reseller.customers.create', [
            'title'   => 'Add a customer',
            'plans'   => $plans,
            'balance' => (float) $reseller['wallet_balance'],
        ]);
    }

    public function store(): Response
    {
        $reseller = $this->reseller();

        $data = $this->validate([
            'name'     => 'required|string|min:2|max:150',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'required|phone|max:25',
            'password' => 'required|password',
            'plan_id'  => 'nullable|integer',
        ]);

        $plan = !empty($data['plan_id']) ? (new Plan())->find((int) $data['plan_id']) : null;
        $wallet = new WalletService();
        $price = $plan !== null ? $wallet->resellerPrice($plan, $reseller) : 0.0;

        // Check affordability before creating anything.
        if ($plan !== null && $price > 0 && !$wallet->canAfford($this->resellerId(), $price)) {
            $this->error(sprintf(
                'Your wallet balance (%s) is not enough for the %s plan (%s). Top up your wallet first.',
                money((float) $reseller['wallet_balance']),
                (string) $plan['name'],
                money($price)
            ));

            return $this->redirect('reseller/customers/create');
        }

        try {
            $userId = $this->db()->transaction(function () use ($data, $reseller, $plan, $price, $wallet): int {
                $users = new User();
                $id = $users->create([
                    'uuid'        => Model::uuid(),
                    'name'        => (string) $data['name'],
                    'email'       => strtolower((string) $data['email']),
                    'phone'       => (string) $data['phone'],
                    'password'    => Auth::hash((string) $data['password']),
                    'role'        => Auth::ROLE_CUSTOMER,
                    'status'      => 'active',
                    'reseller_id' => (int) $reseller['id'],
                    'created_at'  => now(),
                ]);

                if ($plan !== null) {
                    if ($price > 0) {
                        $wallet->debit(
                            (int) $reseller['id'],
                            $price,
                            'Plan "' . (string) $plan['name'] . '" for ' . (string) $data['email'],
                            (int) $reseller['user_id'],
                            'plan_sale',
                            $id
                        );
                    }
                    (new SubscriptionService())->grant($id, (int) $plan['id'], 'reseller', null, (int) $reseller['id']);
                } else {
                    $free = (new Plan())->freePlan();
                    if ($free !== null) {
                        (new SubscriptionService())->grant($id, (int) $free['id'], 'reseller', null, (int) $reseller['id']);
                    }
                }

                return $id;
            });
        } catch (Throwable $e) {
            $this->error('Could not create the customer: ' . $e->getMessage());

            return $this->redirect('reseller/customers/create');
        }

        (new Reseller())->refreshCustomerCount($this->resellerId());
        if ($price > 0) {
            $this->db()->execute(
                'UPDATE `' . $this->db()->table('resellers') . '` SET `total_sales` = `total_sales` + :amount WHERE `id` = :id',
                ['amount' => $price, 'id' => $this->resellerId()]
            );
        }

        AuditLog::record('reseller.customer_created', 'user', $userId, [
            'reseller_id' => $this->resellerId(),
            'plan'        => $plan['slug'] ?? 'free',
            'charged'     => $price,
        ]);

        $this->success('Customer created' . ($price > 0 ? ' and ' . money($price) . ' was deducted from your wallet.' : '.'));

        return $this->redirect('reseller/customers/' . $userId);
    }

    public function show(string $id): Response
    {
        $customer = $this->ownedCustomer($id);
        $reseller = $this->reseller();
        $wallet = new WalletService();

        $plans = (new Plan())->active();
        foreach ($plans as $index => $plan) {
            $plans[$index]['your_price'] = $wallet->resellerPrice($plan, $reseller);
        }

        return $this->render('reseller.customers.show', [
            'title'        => (string) $customer['name'],
            'customer'     => $customer,
            'cards'        => (new Card())->forUser((int) $customer['id']),
            'subscription' => (new Subscription())->activeFor((int) $customer['id']),
            'plan'         => PlanLimiter::planFor((int) $customer['id']),
            'plans'        => $plans,
            'balance'      => (float) $reseller['wallet_balance'],
        ]);
    }

    public function updateStatus(string $id): Response
    {
        $customer = $this->ownedCustomer($id);

        $status = $this->request->string('status');
        if (!in_array($status, ['active', 'suspended'], true)) {
            $this->error('Unknown status.');

            return $this->redirect('reseller/customers/' . (int) $customer['id']);
        }

        (new User())->updateById((int) $customer['id'], ['status' => $status]);

        if ($status !== 'active') {
            $this->db()->execute(
                'UPDATE `' . $this->db()->table('cards') . "` SET `status` = 'suspended', `updated_at` = :now
                  WHERE `user_id` = :user AND `status` = 'published'",
                ['now' => now(), 'user' => (int) $customer['id']]
            );
        }

        AuditLog::record('reseller.customer_status_changed', 'user', (int) $customer['id'], ['status' => $status]);
        $this->success('Customer status changed to ' . $status . '.');

        return $this->redirect('reseller/customers/' . (int) $customer['id']);
    }

    public function assignPlan(string $id): Response
    {
        $customer = $this->ownedCustomer($id);
        $reseller = $this->reseller();

        $planId = $this->request->int('plan_id');
        $plan = (new Plan())->find($planId);
        if ($plan === null || (int) $plan['is_active'] !== 1) {
            $this->error('That plan is not available.');

            return $this->redirect('reseller/customers/' . (int) $customer['id']);
        }

        $wallet = new WalletService();
        $price = $wallet->resellerPrice($plan, $reseller);

        if ($price > 0 && !$wallet->canAfford($this->resellerId(), $price)) {
            $this->error('Your wallet balance is not enough for this plan (' . money($price) . ').');

            return $this->redirect('reseller/customers/' . (int) $customer['id']);
        }

        try {
            $this->db()->transaction(function () use ($wallet, $reseller, $customer, $plan, $price): void {
                if ($price > 0) {
                    $wallet->debit(
                        (int) $reseller['id'],
                        $price,
                        'Plan "' . (string) $plan['name'] . '" for ' . (string) $customer['email'],
                        (int) $reseller['user_id'],
                        'plan_sale',
                        (int) $customer['id']
                    );
                }
                (new SubscriptionService())->grant((int) $customer['id'], (int) $plan['id'], 'reseller', null, (int) $reseller['id']);
            });
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return $this->redirect('reseller/customers/' . (int) $customer['id']);
        }

        if ($price > 0) {
            $this->db()->execute(
                'UPDATE `' . $this->db()->table('resellers') . '` SET `total_sales` = `total_sales` + :amount WHERE `id` = :id',
                ['amount' => $price, 'id' => $this->resellerId()]
            );
        }

        AuditLog::record('reseller.plan_assigned', 'user', (int) $customer['id'], [
            'plan' => $plan['slug'], 'charged' => $price,
        ]);

        $this->success('Plan activated' . ($price > 0 ? ' — ' . money($price) . ' deducted from your wallet.' : '.'));

        return $this->redirect('reseller/customers/' . (int) $customer['id']);
    }

    public function resetPassword(string $id): Response
    {
        $customer = $this->ownedCustomer($id);

        $data = $this->validate(['password' => 'required|password']);
        (new User())->updatePassword((int) $customer['id'], (string) $data['password']);

        AuditLog::record('reseller.customer_password_reset', 'user', (int) $customer['id']);
        $this->success('Password reset. Share the new password with your customer securely.');

        return $this->redirect('reseller/customers/' . (int) $customer['id']);
    }
}
