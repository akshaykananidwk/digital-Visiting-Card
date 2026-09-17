<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Response;
use App\Models\Card;
use App\Models\Model;
use App\Models\Reseller;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Throwable;

final class ResellerController extends AdminController
{
    public function index(): Response
    {
        $filters = ['search' => $this->request->string('q'), 'active' => $this->request->string('active')];

        return $this->render('admin.resellers.index', [
            'title'   => 'Resellers',
            'result'  => (new Reseller())->search($filters, $this->page(), 25),
            'filters' => $filters,
            'stats'   => (new Reseller())->statistics(),
        ]);
    }

    public function create(): Response
    {
        return $this->render('admin.resellers.create', ['title' => 'Create reseller']);
    }

    public function store(): Response
    {
        $data = $this->validate([
            'name'            => 'required|string|min:2|max:150',
            'email'           => 'required|email|unique:users,email',
            'phone'           => 'required|phone|max:25',
            'password'        => 'required|password',
            'company_name'    => 'required|string|min:2|max:150',
            'commission_rate' => 'nullable|numeric|min:0|max:90',
            'credit_limit'    => 'nullable|numeric|min:0|max:1000000',
        ]);

        try {
            $resellerId = $this->db()->transaction(function () use ($data): int {
                $userId = (new User())->create([
                    'uuid'       => Model::uuid(),
                    'name'       => (string) $data['name'],
                    'email'      => strtolower((string) $data['email']),
                    'phone'      => (string) $data['phone'],
                    'password'   => Auth::hash((string) $data['password']),
                    'role'       => Auth::ROLE_RESELLER,
                    'status'     => 'active',
                    'company'    => (string) $data['company_name'],
                    'created_at' => now(),
                ]);

                $resellers = new Reseller();

                return $resellers->create([
                    'user_id'         => $userId,
                    'code'            => $resellers->generateCode((string) $data['company_name']),
                    'company_name'    => (string) $data['company_name'],
                    'brand_name'      => (string) $data['company_name'],
                    'support_email'   => strtolower((string) $data['email']),
                    'support_phone'   => (string) $data['phone'],
                    'commission_rate' => (float) ($data['commission_rate'] ?? 20),
                    'credit_limit'    => (float) ($data['credit_limit'] ?? 0),
                    'is_active'       => 1,
                    'created_at'      => now(),
                ]);
            });
        } catch (Throwable $e) {
            $this->error('Could not create the reseller: ' . $e->getMessage());

            return $this->redirect('admin/resellers/create');
        }

        $openingBalance = $this->request->float('opening_balance');
        if ($openingBalance > 0) {
            try {
                (new WalletService())->credit($resellerId, $openingBalance, 'Opening balance', $this->userId(), 'opening');
            } catch (Throwable $e) {
                $this->error('Reseller created, but the opening balance failed: ' . $e->getMessage());
            }
        }

        AuditLog::record('admin.reseller_created', 'reseller', $resellerId, ['company' => $data['company_name']]);
        $this->success('Reseller created.');

        return $this->redirect('admin/resellers/' . $resellerId);
    }

    public function show(string $id): Response
    {
        $reseller = (new Reseller())->find((int) $id);
        if ($reseller === null) {
            $this->error('Reseller not found.');

            return $this->redirect('admin/resellers');
        }

        $customers = (new User())->search(['reseller_id' => (int) $reseller['id']], 1, 25);

        return $this->render('admin.resellers.show', [
            'title'        => (string) $reseller['company_name'],
            'reseller'     => $reseller,
            'user'         => (new User())->find((int) $reseller['user_id']),
            'customers'    => $customers,
            'transactions' => (new WalletTransaction())->forReseller((int) $reseller['id'], 1, 20),
            'totals'       => (new WalletTransaction())->totals((int) $reseller['id']),
            'cardCount'    => (new Card())->count(['reseller_id' => (int) $reseller['id']]),
        ]);
    }

    public function update(string $id): Response
    {
        $resellers = new Reseller();
        $reseller = $resellers->find((int) $id);
        if ($reseller === null) {
            return $this->redirect('admin/resellers');
        }

        $data = $this->validate([
            'company_name'    => 'required|string|min:2|max:150',
            'brand_name'      => 'nullable|string|max:150',
            'support_email'   => 'nullable|email',
            'support_phone'   => 'nullable|phone|max:25',
            'commission_rate' => 'required|numeric|min:0|max:90',
            'credit_limit'    => 'required|numeric|min:0|max:1000000',
            'notes'           => 'nullable|string|max:2000',
        ]);

        $data['is_active'] = $this->request->bool('is_active', true) ? 1 : 0;

        $resellers->updateById((int) $reseller['id'], $data);
        AuditLog::record('admin.reseller_updated', 'reseller', (int) $reseller['id']);

        $this->success('Reseller updated.');

        return $this->redirect('admin/resellers/' . (int) $reseller['id']);
    }

    public function adjustWallet(string $id): Response
    {
        $reseller = (new Reseller())->find((int) $id);
        if ($reseller === null) {
            return $this->redirect('admin/resellers');
        }

        $amount = $this->request->float('amount');
        $type = $this->request->string('type', 'credit');
        $description = $this->request->string('description') ?: 'Manual adjustment by administrator';

        if ($amount <= 0) {
            $this->error('Enter an amount greater than zero.');

            return $this->redirect('admin/resellers/' . (int) $reseller['id']);
        }

        try {
            $wallet = new WalletService();
            if ($type === 'debit') {
                $wallet->debit((int) $reseller['id'], $amount, $description, $this->userId(), 'adjustment');
                $this->success(money($amount) . ' debited from the wallet.');
            } else {
                $wallet->credit((int) $reseller['id'], $amount, $description, $this->userId(), 'adjustment');
                $this->success(money($amount) . ' credited to the wallet.');
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }

        return $this->redirect('admin/resellers/' . (int) $reseller['id']);
    }

    /** Verify a reseller's white-label domain (DNS TXT record check). */
    public function verifyDomain(string $id): Response
    {
        $resellers = new Reseller();
        $reseller = $resellers->find((int) $id);
        if ($reseller === null) {
            return $this->redirect('admin/resellers');
        }

        $domain = (string) ($reseller['domain'] ?? '');
        if ($domain === '') {
            $this->error('This reseller has not set a domain yet.');

            return $this->redirect('admin/resellers/' . (int) $reseller['id']);
        }

        $action = $this->request->string('action', 'verify');

        if ($action === 'force') {
            $this->requireSuperAdmin();
            $resellers->updateById((int) $reseller['id'], ['domain_status' => 'verified']);
            AuditLog::record('admin.reseller_domain_forced', 'reseller', (int) $reseller['id'], ['domain' => $domain]);
            $this->success('Domain marked as verified manually.');

            return $this->redirect('admin/resellers/' . (int) $reseller['id']);
        }

        $verified = $this->checkDomainToken($domain, (string) ($reseller['domain_token'] ?? ''));
        $resellers->updateById((int) $reseller['id'], ['domain_status' => $verified ? 'verified' : 'failed']);

        if ($verified) {
            $this->success('Domain verified — ' . $domain . ' now serves this reseller\'s branding.');
        } else {
            $this->error('The verification TXT record was not found on ' . $domain . '. DNS changes can take up to an hour to propagate.');
        }

        return $this->redirect('admin/resellers/' . (int) $reseller['id']);
    }

    private function checkDomainToken(string $domain, string $token): bool
    {
        if ($token === '' || !function_exists('dns_get_record')) {
            return false;
        }

        $records = @dns_get_record('_dvc-verify.' . $domain, DNS_TXT);
        if (!is_array($records)) {
            return false;
        }

        foreach ($records as $record) {
            if (isset($record['txt']) && trim((string) $record['txt']) === $token) {
                return true;
            }
        }

        return false;
    }
}
