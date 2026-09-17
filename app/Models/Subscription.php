<?php

declare(strict_types=1);

namespace App\Models;

final class Subscription extends Model
{
    protected string $table = 'subscriptions';

    protected array $jsonColumns = ['snapshot'];

    protected array $intColumns = ['id', 'user_id', 'plan_id', 'order_id', 'reseller_id'];

    protected array $floatColumns = ['amount'];

    protected array $fillable = [
        'uuid', 'user_id', 'plan_id', 'order_id', 'reseller_id', 'status', 'starts_at', 'ends_at',
        'grace_until', 'cancelled_at', 'amount', 'source', 'auto_renew', 'snapshot',
        'created_at', 'updated_at',
    ];

    /**
     * The subscription that currently governs a user's limits.
     *
     * @return array<string,mixed>|null
     */
    public function activeFor(int $userId): ?array
    {
        return $this->hydrate($this->db->selectOne(
            'SELECT * FROM `' . $this->table() . '`
              WHERE `user_id` = :user AND `status` = :status
                AND (`ends_at` IS NULL OR `ends_at` >= :now)
              ORDER BY `ends_at` IS NULL DESC, `ends_at` DESC, `id` DESC
              LIMIT 1',
            ['user' => $userId, 'status' => 'active', 'now' => now()]
        ));
    }

    /** @return array<int,array<string,mixed>> */
    public function historyFor(int $userId, int $limit = 50): array
    {
        return $this->where(['user_id' => $userId], 'created_at DESC', $limit);
    }

    /**
     * Subscriptions whose term has ended but which are still flagged active.
     *
     * @return array<int,array<string,mixed>>
     */
    public function dueForExpiry(int $limit = 500): array
    {
        return $this->hydrateAll($this->db->select(
            'SELECT * FROM `' . $this->table() . '`
              WHERE `status` = :status AND `ends_at` IS NOT NULL AND `ends_at` < :now
              ORDER BY `ends_at` ASC LIMIT ' . max(1, min(2000, $limit)),
            ['status' => 'active', 'now' => now()]
        ));
    }

    /** @return array<int,array<string,mixed>> */
    public function expiringWithin(int $days = 7, int $limit = 200): array
    {
        return $this->hydrateAll($this->db->select(
            'SELECT s.*, u.`name` AS user_name, u.`email` AS user_email, p.`name` AS plan_name
               FROM `' . $this->table() . '` s
               JOIN `' . $this->db->table('users') . '` u ON u.id = s.user_id
               JOIN `' . $this->db->table('plans') . '` p ON p.id = s.plan_id
              WHERE s.`status` = :status AND s.`ends_at` BETWEEN :now AND DATE_ADD(:now2, INTERVAL :days DAY)
              ORDER BY s.`ends_at` ASC LIMIT ' . max(1, min(500, $limit)),
            ['status' => 'active', 'now' => now(), 'now2' => now(), 'days' => $days]
        ));
    }

    public function markExpired(int $id): int
    {
        return $this->updateById($id, ['status' => 'expired']);
    }
}
