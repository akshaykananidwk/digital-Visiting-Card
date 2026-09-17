<?php

declare(strict_types=1);

namespace App\Models;

final class Template extends Model
{
    protected string $table = 'templates';

    protected bool $softDeletes = true;

    protected array $jsonColumns = ['config'];

    protected array $intColumns = ['id', 'category_id', 'usage_count', 'sort_order'];

    protected array $fillable = [
        'code', 'name', 'category_id', 'layout', 'theme_mode', 'style', 'industry', 'color_family',
        'preview_image', 'config', 'tags', 'is_premium', 'is_active', 'is_featured', 'is_popular',
        'usage_count', 'sort_order', 'created_by', 'created_at', 'updated_at', 'deleted_at',
    ];

    /** @return array<string,mixed>|null */
    public function findByCode(string $code): ?array
    {
        return $this->firstWhere(['code' => $code]);
    }

    /**
     * Marketplace / picker query with search + facets.
     *
     * @param array{search?:string,category?:int|string,premium?:string,style?:string,layout?:string,color?:string,mode?:string,sort?:string,active_only?:bool} $filters
     * @return array{data:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int,from:int,to:int}
     */
    public function browse(array $filters, int $page = 1, int $perPage = 24): array
    {
        $conditions = [];

        if ($filters['active_only'] ?? true) {
            $conditions['is_active'] = 1;
        }
        if (!empty($filters['search'])) {
            $term = '%' . $this->escapeLike((string) $filters['search']) . '%';
            $conditions['__raw'] = ['`name` LIKE :s OR `tags` LIKE :s OR `industry` LIKE :s OR `style` LIKE :s OR `color_family` LIKE :s', ['s' => $term]];
        }
        if (!empty($filters['category'])) {
            $conditions['category_id'] = (int) $filters['category'];
        }
        if (($filters['premium'] ?? '') === 'free') {
            $conditions['is_premium'] = 0;
        } elseif (($filters['premium'] ?? '') === 'premium') {
            $conditions['is_premium'] = 1;
        }
        foreach (['style' => 'style', 'layout' => 'layout', 'color' => 'color_family', 'mode' => 'theme_mode'] as $key => $column) {
            if (!empty($filters[$key])) {
                $conditions[$column] = $filters[$key];
            }
        }

        $orderBy = match ((string) ($filters['sort'] ?? 'featured')) {
            'newest'   => 'created_at DESC',
            'popular'  => 'usage_count DESC',
            'name'     => 'name ASC',
            default    => 'sort_order ASC',
        };

        return $this->paginate($conditions, $page, $perPage, $orderBy);
    }

    /** @return array<int,array<string,mixed>> */
    public function featured(int $limit = 12): array
    {
        return $this->where(['is_active' => 1, 'is_featured' => 1], 'sort_order ASC', $limit);
    }

    /** @return array<int,array<string,mixed>> */
    public function popular(int $limit = 12): array
    {
        return $this->where(['is_active' => 1], 'usage_count DESC', $limit);
    }

    /** Distinct facet values used to build the filter sidebar. @return array<string,array<int,string>> */
    public function facets(): array
    {
        $facets = [];
        foreach (['style', 'layout', 'color_family', 'theme_mode'] as $column) {
            $rows = $this->db->select(
                'SELECT DISTINCT `' . $column . '` AS v FROM `' . $this->table() . '`
                  WHERE `deleted_at` IS NULL AND `is_active` = 1 AND `' . $column . '` IS NOT NULL AND `' . $column . '` <> ""
                  ORDER BY v'
            );
            $facets[$column] = array_map(static fn (array $row): string => (string) $row['v'], $rows);
        }

        return $facets;
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $conditions = ['code' => $code];
        if ($exceptId !== null) {
            $conditions['id'] = ['!=', $exceptId];
        }

        return $this->exists($conditions);
    }

    public function incrementUsage(int $id): void
    {
        $this->increment($id, 'usage_count');
    }

    /** @return array<int,array<string,mixed>> Usage report for the admin dashboard. */
    public function usageReport(int $limit = 10): array
    {
        return $this->db->select(
            'SELECT t.`id`, t.`name`, t.`code`, COUNT(c.`id`) AS cards
               FROM `' . $this->table() . '` t
          LEFT JOIN `' . $this->db->table('cards') . '` c ON c.template_id = t.id AND c.deleted_at IS NULL
              WHERE t.`deleted_at` IS NULL
              GROUP BY t.`id`, t.`name`, t.`code`
              ORDER BY cards DESC, t.`name` ASC
              LIMIT ' . max(1, min(100, $limit))
        );
    }

    public function nextSortOrder(): int
    {
        return ((int) $this->db->scalar('SELECT MAX(`sort_order`) FROM `' . $this->table() . '`')) + 1;
    }
}
