<?php

declare(strict_types=1);

namespace App\Models;

final class Payment extends Model
{
    protected string $table = 'payments';

    protected array $jsonColumns = ['raw_response'];

    protected array $intColumns = ['id', 'order_id', 'user_id'];

    protected array $floatColumns = ['amount'];

    protected array $fillable = [
        'order_id', 'user_id', 'gateway', 'gateway_payment_id', 'gateway_order_id', 'gateway_signature',
        'method', 'amount', 'currency', 'status', 'error_code', 'error_description', 'verified_at',
        'raw_response', 'created_at', 'updated_at',
    ];

    /** @return array<string,mixed>|null */
    public function findByGatewayPaymentId(string $paymentId): ?array
    {
        return $this->firstWhere(['gateway_payment_id' => $paymentId]);
    }

    /** @return array<int,array<string,mixed>> */
    public function forOrder(int $orderId): array
    {
        return $this->where(['order_id' => $orderId], 'created_at DESC');
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
            $conditions['__raw'] = ['`gateway_payment_id` LIKE :s OR `gateway_order_id` LIKE :s', ['s' => $term]];
        }

        return $this->paginate($conditions, $page, $perPage, 'created_at DESC');
    }
}
