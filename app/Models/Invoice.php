<?php

declare(strict_types=1);

namespace App\Models;

final class Invoice extends Model
{
    protected string $table = 'invoices';

    protected array $jsonColumns = ['line_items'];

    protected array $intColumns = ['id', 'order_id', 'user_id'];

    protected array $floatColumns = ['subtotal', 'discount', 'cgst', 'sgst', 'igst', 'total'];

    protected array $fillable = [
        'invoice_number', 'order_id', 'user_id', 'billing_name', 'billing_email', 'billing_phone',
        'billing_address', 'billing_gstin', 'seller_gstin', 'place_of_supply', 'subtotal', 'discount',
        'cgst', 'sgst', 'igst', 'total', 'currency', 'status', 'line_items', 'issued_at', 'created_at',
    ];

    /** @return array<string,mixed>|null */
    public function findForUser(int $id, int $userId): ?array
    {
        return $this->firstWhere(['id' => $id, 'user_id' => $userId]);
    }

    /** @return array<string,mixed>|null */
    public function findByOrder(int $orderId): ?array
    {
        return $this->firstWhere(['order_id' => $orderId]);
    }

    /** @return array<int,array<string,mixed>> */
    public function forUser(int $userId, int $limit = 50): array
    {
        return $this->where(['user_id' => $userId], 'issued_at DESC', $limit);
    }

    /**
     * Sequential, financial-year aware invoice number, e.g. INV/2026-27/000123.
     */
    public function generateNumber(string $prefix = 'INV'): string
    {
        $month = (int) date('n');
        $year = (int) date('Y');
        $startYear = $month >= 4 ? $year : $year - 1;
        $fy = $startYear . '-' . substr((string) ($startYear + 1), -2);

        $like = $prefix . '/' . $fy . '/%';
        $last = (string) $this->db->scalar(
            'SELECT `invoice_number` FROM `' . $this->table() . '` WHERE `invoice_number` LIKE :like ORDER BY `id` DESC LIMIT 1',
            ['like' => $like]
        );

        $sequence = 1;
        if ($last !== '' && preg_match('#/(\d+)$#', $last, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }

        return sprintf('%s/%s/%06d', $prefix, $fy, $sequence);
    }

    /**
     * @param array{search?:string,status?:string} $filters
     * @return array{data:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int,from:int,to:int}
     */
    public function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        $conditions = [];
        if (!empty($filters['status'])) {
            $conditions['status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $term = '%' . $this->escapeLike((string) $filters['search']) . '%';
            $conditions['__raw'] = ['`invoice_number` LIKE :s OR `billing_name` LIKE :s OR `billing_email` LIKE :s', ['s' => $term]];
        }

        return $this->paginate($conditions, $page, $perPage, 'issued_at DESC');
    }
}
