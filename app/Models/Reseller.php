<?php

declare(strict_types=1);

namespace App\Models;

final class Reseller extends Model
{
    protected string $table = 'resellers';

    protected bool $softDeletes = true;

    protected array $intColumns = ['id', 'user_id', 'total_customers'];

    protected array $floatColumns = ['commission_rate', 'wallet_balance', 'credit_limit', 'total_sales'];

    protected array $fillable = [
        'user_id', 'code', 'company_name', 'brand_name', 'brand_logo', 'brand_favicon', 'tagline',
        'support_email', 'support_phone', 'support_whatsapp', 'domain', 'domain_status', 'domain_token',
        'commission_rate', 'wallet_balance', 'credit_limit', 'total_customers', 'total_sales',
        'is_active', 'notes', 'created_at', 'updated_at', 'deleted_at',
    ];

    /** @return array<string,mixed>|null */
    public function findByUserId(int $userId): ?array
    {
        return $this->firstWhere(['user_id' => $userId]);
    }

    /** @return array<string,mixed>|null */
    public function findByCode(string $code): ?array
    {
        return $this->firstWhere(['code' => strtoupper($code)]);
    }

    /** @return array<string,mixed>|null */
    public function findByDomain(string $domain): ?array
    {
        return $this->firstWhere(['domain' => strtolower($domain)]);
    }

    public function generateCode(string $companyName): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $companyName) ?: 'RSL', 0, 4));
        $base = str_pad($base, 3, 'X');

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = $base . strtoupper(bin2hex(random_bytes(2)));
            if (!$this->exists(['code' => $candidate])) {
                return $candidate;
            }
        }

        return $base . strtoupper(bin2hex(random_bytes(4)));
    }

    public function domainExists(string $domain, ?int $exceptId = null): bool
    {
        $conditions = ['domain' => strtolower($domain)];
        if ($exceptId !== null) {
            $conditions['id'] = ['!=', $exceptId];
        }

        return $this->exists($conditions);
    }

    public function refreshCustomerCount(int $resellerId): void
    {
        $count = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM `' . $this->db->table('users') . '` WHERE `reseller_id` = :id AND `deleted_at` IS NULL',
            ['id' => $resellerId]
        );
        $this->updateById($resellerId, ['total_customers' => $count]);
    }

    /**
     * @param array{search?:string,active?:string} $filters
     * @return array{data:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int,from:int,to:int}
     */
    public function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        $conditions = [];
        if (isset($filters['active']) && $filters['active'] !== '') {
            $conditions['is_active'] = (int) $filters['active'];
        }
        if (!empty($filters['search'])) {
            $term = '%' . $this->escapeLike((string) $filters['search']) . '%';
            $conditions['__raw'] = ['`company_name` LIKE :s OR `code` LIKE :s OR `brand_name` LIKE :s OR `domain` LIKE :s', ['s' => $term]];
        }

        return $this->paginate($conditions, $page, $perPage, 'created_at DESC');
    }

    /** @return array<string,mixed> */
    public function statistics(): array
    {
        $table = $this->table();

        return [
            'total'        => (int) $this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `deleted_at` IS NULL"),
            'active'       => (int) $this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `deleted_at` IS NULL AND `is_active` = 1"),
            'wallet_total' => (float) $this->db->scalar("SELECT COALESCE(SUM(`wallet_balance`),0) FROM `{$table}` WHERE `deleted_at` IS NULL"),
            'sales_total'  => (float) $this->db->scalar("SELECT COALESCE(SUM(`total_sales`),0) FROM `{$table}` WHERE `deleted_at` IS NULL"),
        ];
    }
}
