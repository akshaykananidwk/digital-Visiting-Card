<?php

declare(strict_types=1);

namespace App\Models;

final class Backup extends Model
{
    protected string $table = 'backups';

    protected bool $timestamps = false;

    protected array $intColumns = ['id', 'files_size', 'database_size', 'created_by'];

    protected array $fillable = [
        'name', 'type', 'trigger_source', 'files_path', 'database_path', 'files_size', 'database_size',
        'files_checksum', 'database_checksum', 'app_version', 'status', 'verified', 'error',
        'created_by', 'created_at', 'completed_at',
    ];

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 25): array
    {
        return $this->where([], 'created_at DESC', $limit);
    }

    /** @return array<int,array<string,mixed>> */
    public function completed(): array
    {
        return $this->where(['status' => 'completed'], 'created_at DESC');
    }

    /** @return array<string,mixed>|null */
    public function latestCompleted(): ?array
    {
        $rows = $this->where(['status' => 'completed'], 'created_at DESC', 1);

        return $rows[0] ?? null;
    }

    public function totalSize(): int
    {
        return (int) $this->db->scalar(
            'SELECT COALESCE(SUM(`files_size` + `database_size`), 0) FROM `' . $this->table() . "` WHERE `status` = 'completed'"
        );
    }
}
