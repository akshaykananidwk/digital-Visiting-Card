<?php

declare(strict_types=1);

namespace App\Models;

final class CardView extends Model
{
    protected string $table = 'card_views';

    protected bool $timestamps = false;

    protected array $intColumns = ['id', 'card_id'];

    protected array $fillable = ['card_id', 'visitor_hash', 'referrer_host', 'device_type', 'source', 'country', 'viewed_on', 'created_at'];

    public function seenToday(int $cardId, string $visitorHash): bool
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM `' . $this->table() . '` WHERE `card_id` = :card AND `visitor_hash` = :hash AND `viewed_on` = CURDATE()',
            ['card' => $cardId, 'hash' => $visitorHash]
        ) > 0;
    }
}
