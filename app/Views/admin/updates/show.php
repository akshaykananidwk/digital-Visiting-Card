<?php /** @var array<string,mixed> $log @var array<string,mixed>|null $backup */ $__view->extend('layouts.admin');
$steps = is_array($log['steps'] ?? null) ? $log['steps'] : [];
$health = is_array($log['health_report'] ?? null) ? $log['health_report'] : [];
$files = is_array($log['files_list'] ?? null) ? $log['files_list'] : [];
?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm btn-secondary" href="<?= e(url('admin/updates/history')) ?>"><?= icon('arrow-left', 15) ?> History</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="grid" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr);align-items:start">
    <div class="stack">
        <div class="card">
            <div class="card-header">
                <h2>Update #<?= (int) $log['id'] ?></h2>
                <?php $badge = match ((string) $log['status']) { 'success' => 'badge-success', 'failed' => 'badge-danger', 'rolled_back' => 'badge-warning', default => 'badge-info' }; ?>
                <span class="badge <?= $badge ?>"><?= e(str_replace('_', ' ', (string) $log['status'])) ?></span>
            </div>
            <div class="card-body">
                <table class="table">
                    <tbody>
                        <tr><td>Version</td><td class="text-right bold"><?= e((string) ($log['from_version'] ?? '')) ?> → <?= e((string) ($log['to_version'] ?? '')) ?></td></tr>
                        <tr><td>Branch</td><td class="text-right"><?= e((string) ($log['branch'] ?? '')) ?></td></tr>
                        <tr><td>Commit</td><td class="text-right tiny" style="font-family:ui-monospace,monospace"><?= e((string) ($log['commit_hash'] ?? '—')) ?></td></tr>
                        <tr><td>Files changed</td><td class="text-right"><?= (int) $log['files_changed'] ?></td></tr>
                        <tr><td>Migrations</td><td class="text-right"><?= e((string) ($log['migration_status'] ?? '—')) ?></td></tr>
                        <tr><td>Backup</td><td class="text-right"><?= e((string) ($log['backup_status'] ?? '—')) ?></td></tr>
                        <tr><td>Health check</td><td class="text-right"><?= e((string) ($log['health_status'] ?? '—')) ?></td></tr>
                        <tr><td>Rollback</td><td class="text-right"><?= e((string) ($log['rollback_status'] ?? '—')) ?></td></tr>
                        <tr><td>Duration</td><td class="text-right"><?= $log['duration_seconds'] !== null ? (int) $log['duration_seconds'] . 's' : '—' ?></td></tr>
                    </tbody>
                </table>

                <?php if (!empty($log['commit_message'])): ?>
                    <div class="label mt-3">Commit message</div>
                    <p class="small" style="white-space:pre-line"><?= e((string) $log['commit_message']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 style="font-size:.98rem">Steps</h3></div>
            <div class="card-body" style="padding:0">
                <?php if ($steps === []): ?>
                    <p class="small muted" style="padding:18px;margin:0">No step information recorded.</p>
                <?php else: ?>
                    <table class="table">
                        <tbody>
                            <?php foreach ($steps as $step): ?>
                                <tr>
                                    <td style="width:34px">
                                        <?php $status = (string) ($step['status'] ?? 'ok'); ?>
                                        <span class="check-status <?= $status === 'ok' ? 'pass' : ($status === 'warn' ? 'warn' : 'fail') ?>">
                                            <?= $status === 'ok' ? '✓' : ($status === 'warn' ? '!' : '✕') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="small bold"><?= e(str_replace('_', ' ', (string) ($step['step'] ?? ''))) ?></div>
                                        <div class="tiny muted"><?= e((string) ($step['detail'] ?? '')) ?></div>
                                    </td>
                                    <td class="tiny muted nowrap text-right"><?= e((string) ($step['at'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($log['error_log'])): ?>
            <div class="card" style="border-color:var(--danger)">
                <div class="card-header"><h3 style="font-size:.98rem;color:var(--danger)">Error log</h3></div>
                <div class="card-body"><pre style="font-size:.72rem;max-height:320px"><?= e((string) $log['error_log']) ?></pre></div>
            </div>
        <?php endif; ?>

        <?php if ($files !== []): ?>
            <div class="card">
                <div class="card-header"><h3 style="font-size:.98rem">Changed files</h3></div>
                <div class="card-body" style="max-height:300px;overflow:auto">
                    <?php foreach ($files as $file): ?>
                        <div class="tiny" style="font-family:ui-monospace,monospace;padding:2px 0">
                            <span class="muted" style="display:inline-block;width:70px"><?= e((string) ($file['status'] ?? '')) ?></span><?= e((string) ($file['filename'] ?? '')) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="stack">
        <?php if ($health !== [] && isset($health['checks'])): ?>
            <div class="card">
                <div class="card-header"><h3 style="font-size:.98rem">Health check result</h3></div>
                <div class="card-body" style="padding:6px 16px">
                    <?php foreach ($health['checks'] as $check): ?>
                        <div class="check-row">
                            <span class="check-status <?= e((string) $check['status']) ?>"><?= $check['status'] === 'pass' ? '✓' : ($check['status'] === 'warn' ? '!' : '✕') ?></span>
                            <div class="flex-1">
                                <div class="small bold"><?= e((string) $check['name']) ?></div>
                                <div class="tiny muted"><?= e((string) $check['message']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($backup !== null): ?>
            <div class="card">
                <div class="card-header"><h3 style="font-size:.98rem">Pre-update backup</h3></div>
                <div class="card-body">
                    <div class="small bold"><?= e((string) $backup['name']) ?></div>
                    <div class="tiny muted mb-2">
                        <?= e(human_size((int) $backup['files_size'] + (int) $backup['database_size'])) ?> ·
                        <?= (int) $backup['verified'] === 1 ? 'verified' : 'not verified' ?>
                    </div>
                    <a class="btn btn-secondary btn-sm btn-block" href="<?= e(url('admin/backups')) ?>">Manage backups</a>
                </div>
            </div>

            <?php if (auth()->isSuperAdmin() && (string) $log['status'] !== 'rolled_back'): ?>
                <form method="post" action="<?= e(url_path('admin/updates/rollback')) ?>" class="card" style="border-color:var(--warning)"
                      data-confirm="Roll back to the pre-update backup? Current files and database will be replaced.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="log_id" value="<?= (int) $log['id'] ?>">
                    <div class="card-header"><h3 style="font-size:.98rem">Roll back</h3></div>
                    <div class="card-body">
                        <p class="tiny muted">Restores the files and database captured before this update, then re-runs the health check.</p>
                        <input class="input mb-2" type="text" name="confirm" placeholder="Type ROLLBACK" required>
                        <button class="btn btn-warning btn-sm btn-block" type="submit"><?= icon('refresh', 15) ?> Roll back now</button>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php $__view->stop(); ?>
