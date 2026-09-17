<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;

final class User extends Model
{
    protected string $table = 'users';

    protected bool $softDeletes = true;

    protected array $jsonColumns = ['meta'];

    protected array $intColumns = ['id', 'reseller_id', 'onboarding_step'];

    protected array $fillable = [
        'uuid', 'name', 'email', 'phone', 'password', 'role', 'status', 'reseller_id', 'avatar',
        'company', 'gstin', 'address', 'city', 'state', 'pincode', 'country',
        'email_verified_at', 'onboarding_step', 'meta', 'last_login_at', 'last_login_ip',
        'created_at', 'updated_at', 'deleted_at',
    ];

    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->firstWhere(['email' => strtolower(trim($email))]);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function register(array $attributes): int
    {
        $attributes['uuid'] = self::uuid();
        $attributes['email'] = strtolower(trim((string) $attributes['email']));
        $attributes['password'] = Auth::hash((string) $attributes['password']);
        $attributes['role'] = $attributes['role'] ?? Auth::ROLE_CUSTOMER;
        $attributes['status'] = $attributes['status'] ?? 'active';

        return $this->create($attributes);
    }

    public function updatePassword(int $id, string $plainPassword): int
    {
        return $this->updateById($id, ['password' => Auth::hash($plainPassword)]);
    }

    public function touchLogin(int $id, string $ip): void
    {
        $this->db->update($this->table, [
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ], ['id' => $id]);
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM `' . $this->table() . '` WHERE `email` = :email AND `deleted_at` IS NULL';
        $bindings = ['email' => strtolower(trim($email))];
        if ($exceptId !== null) {
            $sql .= ' AND `id` <> :id';
            $bindings['id'] = $exceptId;
        }

        return (int) $this->db->scalar($sql, $bindings) > 0;
    }

    /**
     * Search + filter list used by the admin and reseller panels.
     *
     * @param array{search?:string,role?:string,status?:string,reseller_id?:int|null,plan_id?:int} $filters
     * @return array{data:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int,from:int,to:int}
     */
    public function search(array $filters, int $page = 1, int $perPage = 20, string $orderBy = 'created_at DESC'): array
    {
        $conditions = [];

        if (!empty($filters['search'])) {
            $term = '%' . $this->escapeLike((string) $filters['search']) . '%';
            $conditions['__raw'] = ['`name` LIKE :s OR `email` LIKE :s OR `phone` LIKE :s OR `company` LIKE :s', ['s' => $term]];
        }
        if (!empty($filters['role'])) {
            $conditions['role'] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            $conditions['status'] = $filters['status'];
        }
        if (array_key_exists('reseller_id', $filters)) {
            $conditions['reseller_id'] = $filters['reseller_id'];
        }

        return $this->paginate($conditions, $page, $perPage, $orderBy);
    }

    /** @return array<string,int> */
    public function statistics(): array
    {
        $table = $this->table();

        return [
            'total'      => (int) $this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `deleted_at` IS NULL"),
            'active'     => (int) $this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `deleted_at` IS NULL AND `status` = 'active'"),
            'suspended'  => (int) $this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `deleted_at` IS NULL AND `status` = 'suspended'"),
            'customers'  => (int) $this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `deleted_at` IS NULL AND `role` = 'customer'"),
            'resellers'  => (int) $this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `deleted_at` IS NULL AND `role` = 'reseller'"),
            'today'      => (int) $this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `deleted_at` IS NULL AND DATE(`created_at`) = CURDATE()"),
            'this_month' => (int) $this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `deleted_at` IS NULL AND `created_at` >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
        ];
    }

    /**
     * New users per day for the admin dashboard chart.
     *
     * @return array<int,array{label:string,value:int}>
     */
    public function growth(int $days = 30): array
    {
        $rows = $this->db->select(
            'SELECT DATE(`created_at`) AS d, COUNT(*) AS c FROM `' . $this->table() . '`
              WHERE `deleted_at` IS NULL AND `created_at` >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
              GROUP BY DATE(`created_at`) ORDER BY d',
            ['days' => $days]
        );

        return self::fillSeries($rows, $days);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array{label:string,value:int}>
     */
    public static function fillSeries(array $rows, int $days): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['d']] = (int) $row['c'];
        }
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime('-' . $i . ' days'));
            $series[] = ['label' => $date, 'value' => $map[$date] ?? 0];
        }

        return $series;
    }

    /** Anonymise and detach a user's personal data (GDPR-style deletion). */
    public function purge(int $id): void
    {
        $this->db->transaction(function () use ($id): void {
            $this->db->execute('DELETE FROM `' . $this->db->table('cards') . '` WHERE `user_id` = :id', ['id' => $id]);
            $this->db->execute('DELETE FROM `' . $this->db->table('leads') . '` WHERE `user_id` = :id', ['id' => $id]);
            $this->db->execute('DELETE FROM `' . $this->db->table('api_tokens') . '` WHERE `user_id` = :id', ['id' => $id]);
            $this->db->update($this->table, [
                'name'          => 'Deleted user',
                'email'         => 'deleted-' . $id . '-' . bin2hex(random_bytes(4)) . '@deleted.invalid',
                'phone'         => null,
                'avatar'        => null,
                'company'       => null,
                'gstin'         => null,
                'address'       => null,
                'city'          => null,
                'state'         => null,
                'pincode'       => null,
                'meta'          => null,
                'status'        => 'suspended',
                'password'      => Auth::hash(bin2hex(random_bytes(16))),
                'deleted_at'    => now(),
            ], ['id' => $id]);
        });
    }
}
