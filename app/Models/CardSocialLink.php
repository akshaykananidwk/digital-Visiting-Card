<?php

declare(strict_types=1);

namespace App\Models;

final class CardSocialLink extends Model
{
    protected string $table = 'card_social_links';

    protected array $intColumns = ['id', 'card_id', 'sort_order'];

    protected array $fillable = ['card_id', 'platform', 'url', 'label', 'is_active', 'sort_order', 'created_at'];

    /** Supported platforms with their display metadata. */
    public const PLATFORMS = [
        'facebook'  => ['label' => 'Facebook',  'icon' => 'facebook',  'color' => '#1877F2'],
        'instagram' => ['label' => 'Instagram', 'icon' => 'instagram', 'color' => '#E4405F'],
        'youtube'   => ['label' => 'YouTube',   'icon' => 'youtube',   'color' => '#FF0000'],
        'linkedin'  => ['label' => 'LinkedIn',  'icon' => 'linkedin',  'color' => '#0A66C2'],
        'x'         => ['label' => 'X',         'icon' => 'x',         'color' => '#000000'],
        'telegram'  => ['label' => 'Telegram',  'icon' => 'telegram',  'color' => '#26A5E4'],
        'google'    => ['label' => 'Google Business', 'icon' => 'google', 'color' => '#4285F4'],
        'pinterest' => ['label' => 'Pinterest', 'icon' => 'pinterest', 'color' => '#BD081C'],
        'threads'   => ['label' => 'Threads',   'icon' => 'threads',   'color' => '#000000'],
        'snapchat'  => ['label' => 'Snapchat',  'icon' => 'snapchat',  'color' => '#FFFC00'],
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

    /**
     * Replace the full social link set for a card in a single transaction.
     *
     * @param array<string,string> $links platform => url
     */
    public function sync(int $cardId, array $links): void
    {
        $this->db->transaction(function () use ($cardId, $links): void {
            $position = 0;
            foreach (self::PLATFORMS as $platform => $meta) {
                $url = trim((string) ($links[$platform] ?? ''));

                if ($url === '') {
                    $this->db->delete($this->table, ['card_id' => $cardId, 'platform' => $platform]);

                    continue;
                }
                if (!preg_match('#^https?://#i', $url)) {
                    $url = 'https://' . ltrim($url, '/');
                }
                if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                    continue;
                }

                $position++;
                $existing = $this->firstWhere(['card_id' => $cardId, 'platform' => $platform]);
                if ($existing !== null) {
                    $this->updateById((int) $existing['id'], ['url' => $url, 'sort_order' => $position, 'is_active' => 1]);
                } else {
                    $this->create([
                        'card_id'    => $cardId,
                        'platform'   => $platform,
                        'url'        => $url,
                        'label'      => $meta['label'],
                        'is_active'  => 1,
                        'sort_order' => $position,
                        'created_at' => now(),
                    ]);
                }
            }
        });
    }

    /** @return array<string,string> platform => url */
    public function mapForCard(int $cardId): array
    {
        $map = [];
        foreach ($this->forCard($cardId) as $row) {
            $map[(string) $row['platform']] = (string) $row['url'];
        }

        return $map;
    }
}
