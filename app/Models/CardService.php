<?php

declare(strict_types=1);

namespace App\Models;

final class CardService extends Model
{
    protected string $table = 'card_services';

    protected array $intColumns = ['id', 'card_id', 'sort_order'];

    protected array $floatColumns = ['price'];

    protected array $fillable = [
        'card_id', 'title', 'description', 'icon', 'image', 'price', 'price_label',
        'cta_label', 'cta_link', 'is_active', 'sort_order', 'created_at', 'updated_at',
    ];

    /** @return array<int,array<string,mixed>> */
    public function forCard(int $cardId, bool $activeOnly = false): array
    {
        $conditions = ['card_id' => $cardId];
        if ($activeOnly) {
            $conditions['is_active'] = 1;
        }

        return $this->where($conditions, 'sort_order ASC');
    }

    /** @return array<string,mixed>|null Ownership-checked fetch. */
    public function findForCard(int $id, int $cardId): ?array
    {
        return $this->firstWhere(['id' => $id, 'card_id' => $cardId]);
    }

    public function nextSortOrder(int $cardId): int
    {
        return ((int) $this->db->scalar(
            'SELECT MAX(`sort_order`) FROM `' . $this->table() . '` WHERE `card_id` = :card',
            ['card' => $cardId]
        )) + 1;
    }

    /** @param array<int,int> $orderedIds */
    public function reorder(int $cardId, array $orderedIds): void
    {
        $this->db->transaction(function () use ($cardId, $orderedIds): void {
            foreach (array_values($orderedIds) as $position => $id) {
                $this->db->update($this->table, ['sort_order' => $position + 1], ['id' => (int) $id, 'card_id' => $cardId]);
            }
        });
    }
}
