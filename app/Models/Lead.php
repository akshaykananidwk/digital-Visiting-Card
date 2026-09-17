<?php

declare(strict_types=1);

namespace App\Models;

final class Lead extends Model
{
    protected string $table = 'leads';

    protected bool $softDeletes = true;

    protected array $intColumns = ['id', 'card_id', 'user_id'];

    protected array $fillable = [
        'card_id', 'user_id', 'name', 'phone', 'email', 'subject', 'message', 'source',
        'status', 'ip_hash', 'user_agent', 'notes', 'read_at', 'created_at', 'updated_at', 'deleted_at',
    ];

    /**
     * @param array{search?:string,status?:string,card_id?:int} $filters
     * @return array{data:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int,from:int,to:int}
     */
    public function forUser(int $userId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $conditions = ['user_id' => $userId];
        if (!empty($filters['status'])) {
            $conditions['status'] = $filters['status'];
        }
        if (!empty($filters['card_id'])) {
            $conditions['card_id'] = (int) $filters['card_id'];
        }
        if (!empty($filters['search'])) {
            $term = '%' . $this->escapeLike((string) $filters['search']) . '%';
            $conditions['__raw'] = ['`name` LIKE :s OR `phone` LIKE :s OR `email` LIKE :s OR `message` LIKE :s', ['s' => $term]];
        }

        return $this->paginate($conditions, $page, $perPage, 'created_at DESC');
    }

    /** @return array<string,mixed>|null Ownership-checked fetch. */
    public function findForUser(int $id, int $userId): ?array
    {
        return $this->firstWhere(['id' => $id, 'user_id' => $userId]);
    }

    public function unreadCount(int $userId): int
    {
        return $this->count(['user_id' => $userId, 'status' => 'new']);
    }

    public function markRead(int $id): int
    {
        return $this->updateById($id, ['status' => 'read', 'read_at' => now()]);
    }

    /** @return array<int,array<string,mixed>> */
    public function recentForUser(int $userId, int $limit = 5): array
    {
        return $this->where(['user_id' => $userId], 'created_at DESC', $limit);
    }

    /**
     * @param array{search?:string,status?:string} $filters
     * @return array{data:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int,from:int,to:int}
     */
    public function searchAll(array $filters, int $page = 1, int $perPage = 25): array
    {
        $conditions = [];
        if (!empty($filters['status'])) {
            $conditions['status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $term = '%' . $this->escapeLike((string) $filters['search']) . '%';
            $conditions['__raw'] = ['`name` LIKE :s OR `phone` LIKE :s OR `email` LIKE :s', ['s' => $term]];
        }

        return $this->paginate($conditions, $page, $perPage, 'created_at DESC');
    }
}
