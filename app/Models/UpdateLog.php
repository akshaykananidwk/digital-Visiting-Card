<?php

declare(strict_types=1);

namespace App\Models;

final class UpdateLog extends Model
{
    protected string $table = 'update_logs';

    protected bool $timestamps = false;

    protected array $jsonColumns = ['files_list', 'migrations_run', 'health_report', 'steps'];

    protected array $intColumns = ['id', 'files_changed', 'backup_id', 'duration_seconds', 'initiated_by'];

    protected array $fillable = [
        'from_version', 'to_version', 'commit_hash', 'commit_message', 'commit_date', 'branch',
        'status', 'stage', 'files_changed', 'files_list', 'migration_status', 'migrations_run',
        'backup_id', 'backup_status', 'health_status', 'health_report', 'rollback_status',
        'error_log', 'steps', 'duration_seconds', 'initiated_by', 'started_at', 'finished_at',
    ];

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 25): array
    {
        return $this->where([], 'started_at DESC', $limit);
    }

    /** @return array<string,mixed>|null */
    public function latest(): ?array
    {
        $rows = $this->where([], 'started_at DESC', 1);

        return $rows[0] ?? null;
    }

    /** @return array<string,mixed>|null An update left in the "running" state. */
    public function runningUpdate(): ?array
    {
        return $this->firstWhere(['status' => 'running']);
    }

    /** Append a step entry to an in-flight update log. */
    public function addStep(int $id, string $step, string $status = 'ok', string $detail = ''): void
    {
        $row = $this->find($id);
        $steps = is_array($row['steps'] ?? null) ? $row['steps'] : [];
        $steps[] = [
            'step'   => $step,
            'status' => $status,
            'detail' => $detail,
            'at'     => now(),
        ];
        $this->updateById($id, ['steps' => $steps, 'stage' => $step]);
    }
}
