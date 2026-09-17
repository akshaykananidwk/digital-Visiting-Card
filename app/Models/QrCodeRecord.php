<?php

declare(strict_types=1);

namespace App\Models;

final class QrCodeRecord extends Model
{
    protected string $table = 'qr_codes';

    protected array $intColumns = ['id', 'card_id', 'scan_count'];

    protected array $fillable = [
        'card_id', 'target_url', 'foreground', 'background', 'ecc_level',
        'scan_count', 'last_scanned_at', 'created_at', 'updated_at',
    ];

    /** @return array<string,mixed>|null */
    public function forCard(int $cardId): ?array
    {
        return $this->firstWhere(['card_id' => $cardId]);
    }

    /** Create or refresh the QR record whenever a card URL or style changes. */
    public function sync(int $cardId, string $targetUrl, string $foreground = '#000000', string $background = '#FFFFFF', string $ecc = 'M'): array
    {
        $existing = $this->forCard($cardId);
        $payload = [
            'target_url' => $targetUrl,
            'foreground' => $foreground,
            'background' => $background,
            'ecc_level'  => $ecc,
        ];

        if ($existing === null) {
            $payload['card_id'] = $cardId;
            $payload['created_at'] = now();
            $id = $this->create($payload);

            return (array) $this->find($id);
        }

        $this->updateById((int) $existing['id'], $payload);

        return (array) $this->find((int) $existing['id']);
    }

    public function registerScan(int $cardId): void
    {
        $this->db->execute(
            'UPDATE `' . $this->table() . '` SET `scan_count` = `scan_count` + 1, `last_scanned_at` = :now WHERE `card_id` = :card',
            ['now' => now(), 'card' => $cardId]
        );
    }
}
