<?php

declare(strict_types=1);

namespace App\Models;

final class Notification extends Model
{
    protected string $table = 'notifications';

    protected bool $timestamps = false;

    protected array $intColumns = ['id', 'user_id'];

    protected array $fillable = [
        'user_id', 'channel', 'event', 'recipient', 'title', 'body', 'link',
        'status', 'sent_at', 'read_at', 'created_at',
    ];

    /** @return array<int,array<string,mixed>> */
    public function forUser(int $userId, int $limit = 20): array
    {
        return $this->where(['user_id' => $userId, 'channel' => 'in_app'], 'created_at DESC', $limit);
    }

    public function unreadCount(int $userId): int
    {
        return $this->count(['user_id' => $userId, 'channel' => 'in_app', 'status' => 'queued']);
    }

    public function markAllRead(int $userId): int
    {
        return $this->db->execute(
            'UPDATE `' . $this->table() . "` SET `status` = 'read', `read_at` = :now
              WHERE `user_id` = :user AND `channel` = 'in_app' AND `status` <> 'read'",
            ['now' => now(), 'user' => $userId]
        );
    }

    public function push(int $userId, string $event, string $title, string $body = '', ?string $link = null): int
    {
        return $this->create([
            'user_id'    => $userId,
            'channel'    => 'in_app',
            'event'      => $event,
            'title'      => $title,
            'body'       => $body,
            'link'       => $link,
            'status'     => 'queued',
            'created_at' => now(),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 50): array
    {
        return $this->where([], 'created_at DESC', $limit);
    }
}
