<?php

declare(strict_types=1);

namespace App\Models;

final class AuditLogEntry extends Model
{
    protected string $table = 'audit_logs';

    protected bool $timestamps = false;

    protected array $jsonColumns = ['context'];

    protected array $intColumns = ['id', 'user_id', 'entity_id'];

    protected array $fillable = [
        'user_id', 'actor_role', 'action', 'entity_type', 'entity_id',
        'context', 'ip_address', 'user_agent', 'created_at',
    ];

    /**
     * @param array{search?:string,action?:string,user_id?:int} $filters
     * @return array{data:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int,from:int,to:int}
     */
    public function search(array $filters, int $page = 1, int $perPage = 50): array
    {
        $conditions = [];
        if (!empty($filters['action'])) {
            $conditions['action'] = $filters['action'];
        }
        if (!empty($filters['user_id'])) {
            $conditions['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['search'])) {
            $term = '%' . $this->escapeLike((string) $filters['search']) . '%';
            $conditions['__raw'] = ['`action` LIKE :s OR `entity_type` LIKE :s OR `ip_address` LIKE :s', ['s' => $term]];
        }

        return $this->paginate($conditions, $page, $perPage, 'created_at DESC');
    }

    /** @return array<int,string> */
    public function distinctActions(int $limit = 100): array
    {
        $rows = $this->db->select('SELECT DISTINCT `action` FROM `' . $this->table() . '` ORDER BY `action` LIMIT ' . max(1, min(500, $limit)));

        return array_map(static fn (array $row): string => (string) $row['action'], $rows);
    }
}
