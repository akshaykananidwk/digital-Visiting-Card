<?php

declare(strict_types=1);

namespace App\Models;

final class CardSection extends Model
{
    protected string $table = 'card_sections';

    protected array $jsonColumns = ['settings'];

    protected array $intColumns = ['id', 'card_id', 'sort_order'];

    protected array $fillable = ['card_id', 'section', 'title', 'is_enabled', 'sort_order', 'settings', 'created_at', 'updated_at'];

    /** Canonical section order a new card starts with. */
    public const DEFAULTS = [
        'hero'      => 'Profile',
        'actions'   => 'Quick actions',
        'about'     => 'About',
        'contact'   => 'Contact details',
        'services'  => 'Services',
        'products'  => 'Products',
        'gallery'   => 'Gallery',
        'hours'     => 'Business hours',
        'social'    => 'Social media',
        'payment'   => 'Payment / UPI',
        'map'       => 'Location',
        'enquiry'   => 'Enquiry form',
        'qr'        => 'QR code',
    ];

    /** @return array<int,array<string,mixed>> */
    public function forCard(int $cardId): array
    {
        return $this->where(['card_id' => $cardId], 'sort_order ASC');
    }

    /** @return array<string,array<string,mixed>> section key => row */
    public function mapForCard(int $cardId): array
    {
        $map = [];
        foreach ($this->forCard($cardId) as $row) {
            $map[(string) $row['section']] = $row;
        }

        return $map;
    }

    /** Seed the default section set for a newly created card. */
    public function seedDefaults(int $cardId): void
    {
        $position = 0;
        foreach (self::DEFAULTS as $section => $title) {
            $position++;
            if ($this->exists(['card_id' => $cardId, 'section' => $section])) {
                continue;
            }
            $this->create([
                'card_id'    => $cardId,
                'section'    => $section,
                'title'      => $title,
                'is_enabled' => 1,
                'sort_order' => $position,
                'created_at' => now(),
            ]);
        }
    }

    /** @param array<int,string> $orderedSections */
    public function reorder(int $cardId, array $orderedSections): void
    {
        $this->db->transaction(function () use ($cardId, $orderedSections): void {
            foreach (array_values($orderedSections) as $position => $section) {
                if (!array_key_exists($section, self::DEFAULTS)) {
                    continue;
                }
                $this->db->update($this->table, ['sort_order' => $position + 1], ['card_id' => $cardId, 'section' => $section]);
            }
        });
    }

    public function setEnabled(int $cardId, string $section, bool $enabled): void
    {
        if (!array_key_exists($section, self::DEFAULTS)) {
            return;
        }
        $this->db->update($this->table, ['is_enabled' => $enabled ? 1 : 0, 'updated_at' => now()], ['card_id' => $cardId, 'section' => $section]);
    }
}
