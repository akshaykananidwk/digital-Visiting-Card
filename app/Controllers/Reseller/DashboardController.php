<?php

declare(strict_types=1);

namespace App\Controllers\Reseller;

use App\Core\Response;
use App\Models\Card;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WalletTransaction;

final class DashboardController extends ResellerPanelController
{
    public function index(): Response
    {
        $reseller = $this->reseller();
        $resellerId = (int) $reseller['id'];

        $customers = new User();
        $cards = new Card();

        return $this->render('reseller.dashboard', [
            'title'   => 'Reseller dashboard',
            'stats'   => [
                'customers'       => $customers->count(['reseller_id' => $resellerId]),
                'active_customers'=> $customers->count(['reseller_id' => $resellerId, 'status' => 'active']),
                'cards'           => $cards->count(['reseller_id' => $resellerId]),
                'published'       => $cards->count(['reseller_id' => $resellerId, 'status' => 'published']),
            ],
            'wallet'  => [
                'balance' => (float) $reseller['wallet_balance'],
                'credit'  => (float) $reseller['credit_limit'],
                'totals'  => (new WalletTransaction())->totals($resellerId),
            ],
            'recentCustomers' => $customers->where(['reseller_id' => $resellerId], 'created_at DESC', 8),
            'recentWallet'    => (new WalletTransaction())->where(['reseller_id' => $resellerId], 'created_at DESC', 8),
            'expiring'        => $this->expiringCustomers($resellerId),
            'plans'           => (new Plan())->active(),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function expiringCustomers(int $resellerId): array
    {
        return $this->db()->select(
            'SELECT s.`ends_at`, u.`id` AS user_id, u.`name`, u.`email`, p.`name` AS plan_name
               FROM `' . $this->db()->table('subscriptions') . '` s
               JOIN `' . $this->db()->table('users') . '` u ON u.id = s.user_id
               JOIN `' . $this->db()->table('plans') . '` p ON p.id = s.plan_id
              WHERE u.`reseller_id` = :reseller AND s.`status` = :status
                AND s.`ends_at` BETWEEN :now AND DATE_ADD(:now2, INTERVAL 21 DAY)
              ORDER BY s.`ends_at` ASC LIMIT 10',
            ['reseller' => $resellerId, 'status' => 'active', 'now' => now(), 'now2' => now()]
        );
    }
}
