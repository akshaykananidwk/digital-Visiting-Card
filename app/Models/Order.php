<?php

declare(strict_types=1);

namespace App\Models;

final class Order extends Model
{
    protected string $table = 'orders';

    protected array $jsonColumns = ['meta'];

    protected array $intColumns = ['id', 'user_id', 'plan_id', 'reseller_id', 'subscription_id'];

    protected array $floatColumns = ['subtotal', 'discount', 'tax_rate', 'tax_amount', 'total'];

    protected array $fillable = [
        'order_number', 'user_id', 'plan_id', 'reseller_id', 'subscription_id', 'status', 'gateway',
        'gateway_order_id', 'subtotal', 'discount', 'tax_rate', 'tax_amount', 'total', 'currency',
        'customer_gstin', 'notes', 'meta', 'paid_at', 'created_at', 'updated_at',
    ];

    /** @return array<string,mixed>|null */
    public function findByGatewayOrderId(string $gatewayOrderId): ?array
    {
        return $this->firstWhere(['gateway_order_id' => $gatewayOrderId]);
    }

    /** @return array<string,mixed>|null */
    public function findByNumber(string $number): ?array
    {
        return $this->firstWhere(['order_number' => $number]);
    }

    /** @return array<string,mixed>|null Ownership-checked fetch. */
    public function findForUser(int $id, int $userId): ?array
    {
        return $this->firstWhere(['id' => $id, 'user_id' => $userId]);
    }

    public function generateNumber(): string
    {
        $prefix = 'ORD' . date('ymd');
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $prefix . strtoupper(bin2hex(random_bytes(3)));
            if (!$this->exists(['order_number' => $candidate])) {
                return $candidate;
            }
        }

        return $prefix . strtoupper(bin2hex(random_bytes(6)));
    }

    /** @return array<int,array<string,mixed>> */
    public function forUser(int $userId, int $limit = 50): array
    {
        return $this->where(['user_id' => $userId], 'created_at DESC', $limit);
    }

    /**
     * @param array{search?:string,status?:string,user_id?:int,reseller_id?:int} $filters
     * @return array{data:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int,from:int,to:int}
     */
    public function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        $conditions = [];
        if (!empty($filters['status'])) {
            $conditions['status'] = $filters['status'];
        }
        foreach (['user_id', 'reseller_id'] as $key) {
            if (!empty($filters[$key])) {
                $conditions[$key] = (int) $filters[$key];
            }
        }
        if (!empty($filters['search'])) {
            $term = '%' . $this->escapeLike((string) $filters['search']) . '%';
            $conditions['__raw'] = ['`order_number` LIKE :s OR `gateway_order_id` LIKE :s', ['s' => $term]];
        }

        return $this->paginate($conditions, $page, $perPage, 'created_at DESC');
    }

    /** @return array<string,float|int> */
    public function revenueStatistics(): array
    {
        $table = $this->table();

        return [
            'total_revenue' => (float) $this->db->scalar("SELECT COALESCE(SUM(`total`),0) FROM `{$table}` WHERE `status` = 'paid'"),
            'month_revenue' => (float) $this->db->scalar("SELECT COALESCE(SUM(`total`),0) FROM `{$table}` WHERE `status` = 'paid' AND `paid_at` >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
            'today_revenue' => (float) $this->db->scalar("SELECT COALESCE(SUM(`total`),0) FROM `{$table}` WHERE `status` = 'paid' AND DATE(`paid_at`) = CURDATE()"),
            'paid_orders'   => (int) $this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `status` = 'paid'"),
            'pending_orders'=> (int) $this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `status` = 'pending'"),
            'failed_orders' => (int) $this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `status` = 'failed'"),
        ];
    }

    /** @return array<int,array{label:string,value:float}> */
    public function revenueSeries(int $days = 30): array
    {
        $rows = $this->db->select(
            "SELECT DATE(`paid_at`) AS d, COALESCE(SUM(`total`),0) AS c FROM `" . $this->table() . "`
              WHERE `status` = 'paid' AND `paid_at` >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
              GROUP BY DATE(`paid_at`) ORDER BY d",
            ['days' => $days]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['d']] = (float) $row['c'];
        }
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime('-' . $i . ' days'));
            $series[] = ['label' => $date, 'value' => $map[$date] ?? 0.0];
        }

        return $series;
    }
}
