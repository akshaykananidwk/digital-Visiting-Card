<?php

declare(strict_types=1);

namespace App\Models;

final class WalletTransaction extends Model
{
    protected string $table = 'wallet_transactions';

    protected bool $timestamps = false;

    protected array $intColumns = ['id', 'reseller_id', 'reference_id', 'created_by'];

    protected array $floatColumns = ['amount', 'balance_before', 'balance_after'];

    protected array $fillable = [
        'reseller_id', 'type', 'amount', 'balance_before', 'balance_after',
        'reference_type', 'reference_id', 'description', 'created_by', 'created_at',
    ];

    /**
     * @return array{data:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int,from:int,to:int}
     */
    public function forReseller(int $resellerId, int $page = 1, int $perPage = 25): array
    {
        return $this->paginate(['reseller_id' => $resellerId], $page, $perPage, 'created_at DESC');
    }

    /** @return array<string,float> */
    public function totals(int $resellerId): array
    {
        $table = $this->table();

        return [
            'credited' => (float) $this->db->scalar("SELECT COALESCE(SUM(`amount`),0) FROM `{$table}` WHERE `reseller_id` = :id AND `type` = 'credit'", ['id' => $resellerId]),
            'debited'  => (float) $this->db->scalar("SELECT COALESCE(SUM(`amount`),0) FROM `{$table}` WHERE `reseller_id` = :id AND `type` = 'debit'", ['id' => $resellerId]),
        ];
    }
}
