<?php

declare(strict_types=1);

namespace App\Models;

final class TemplateCategory extends Model
{
    protected string $table = 'template_categories';

    protected array $intColumns = ['id', 'parent_id', 'template_count', 'sort_order'];

    protected array $fillable = [
        'parent_id', 'slug', 'name', 'icon', 'description', 'template_count',
        'is_active', 'sort_order', 'created_at', 'updated_at',
    ];

    /** @return array<int,array<string,mixed>> */
    public function active(): array
    {
        return $this->where(['is_active' => 1], 'sort_order ASC');
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->firstWhere(['slug' => $slug]);
    }

    /** Categories grouped by their parent for the filter UI. @return array<int,array<string,mixed>> */
    public function tree(): array
    {
        $all = $this->where([], 'sort_order ASC');
        $byParent = [];
        foreach ($all as $row) {
            $byParent[(int) ($row['parent_id'] ?? 0)][] = $row;
        }
        $tree = [];
        foreach ($byParent[0] ?? [] as $parent) {
            $parent['children'] = $byParent[(int) $parent['id']] ?? [];
            $tree[] = $parent;
        }

        return $tree;
    }

    /** Recalculate the cached template counters. */
    public function refreshCounts(): void
    {
        $this->db->execute(
            'UPDATE `' . $this->table() . '` c
                SET c.`template_count` = (
                    SELECT COUNT(*) FROM `' . $this->db->table('templates') . '` t
                     WHERE t.`category_id` = c.`id` AND t.`deleted_at` IS NULL AND t.`is_active` = 1
                )'
        );
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $conditions = ['slug' => $slug];
        if ($exceptId !== null) {
            $conditions['id'] = ['!=', $exceptId];
        }

        return $this->exists($conditions);
    }
}
