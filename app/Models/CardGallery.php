<?php

declare(strict_types=1);

namespace App\Models;

final class CardGallery extends Model
{
    protected string $table = 'card_gallery';

    protected array $intColumns = ['id', 'card_id', 'sort_order', 'width', 'height', 'filesize'];

    protected array $fillable = [
        'card_id', 'type', 'path', 'thumbnail', 'title', 'caption',
        'width', 'height', 'filesize', 'sort_order', 'created_at',
    ];

    /** @return array<int,array<string,mixed>> */
    public function forCard(int $cardId, ?string $type = null): array
    {
        $conditions = ['card_id' => $cardId];
        if ($type !== null) {
            $conditions['type'] = $type;
        }

        return $this->where($conditions, 'sort_order ASC');
    }

    /** @return array<string,mixed>|null */
    public function findForCard(int $id, int $cardId): ?array
    {
        return $this->firstWhere(['id' => $id, 'card_id' => $cardId]);
    }

    public function countForCard(int $cardId, ?string $type = null): int
    {
        $conditions = ['card_id' => $cardId];
        if ($type !== null) {
            $conditions['type'] = $type;
        }

        return $this->count($conditions);
    }

    public function nextSortOrder(int $cardId): int
    {
        return ((int) $this->db->scalar(
            'SELECT MAX(`sort_order`) FROM `' . $this->table() . '` WHERE `card_id` = :card',
            ['card' => $cardId]
        )) + 1;
    }

    /** Total bytes stored for a card's gallery (storage quota accounting). */
    public function storageUsed(int $userId): int
    {
        return (int) $this->db->scalar(
            'SELECT COALESCE(SUM(g.`filesize`), 0) FROM `' . $this->table() . '` g
               JOIN `' . $this->db->table('cards') . '` c ON c.id = g.card_id
              WHERE c.`user_id` = :user AND c.`deleted_at` IS NULL',
            ['user' => $userId]
        );
    }
}
