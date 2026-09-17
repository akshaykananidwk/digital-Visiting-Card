<?php /** @var array{data:array<int,array<string,mixed>>,total:int,page:int,pages:int,from:int,to:int} $result */ $__view->extend('layouts.admin'); ?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm btn-secondary" href="<?= e(url('admin/updates')) ?>"><?= icon('arrow-left', 15) ?> Updates</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="card"><div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>#</th><th>Version</th><th>Commit</th><th>Files</th><th>Migrations</th><th>Health</th><th>Status</th><th>Started</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $log): ?>
                <tr>
                    <td class="small"><?= (int) $log['id'] ?></td>
                    <td class="small"><?= e((string) ($log['from_version'] ?? '')) ?> → <?= e((string) ($log['to_version'] ?? '')) ?></td>
                    <td class="tiny" style="font-family:ui-monospace,monospace"><?= e(substr((string) ($log['commit_hash'] ?? ''), 0, 8) ?: '—') ?></td>
                    <td class="small"><?= (int) $log['files_changed'] ?></td>
                    <td class="small"><?= e((string) ($log['migration_status'] ?? '—')) ?></td>
                    <td>
                        <?php if (($log['health_status'] ?? '') === 'pass'): ?><span class="badge badge-success">pass</span>
                        <?php elseif (($log['health_status'] ?? '') === 'fail'): ?><span class="badge badge-danger">fail</span>
                        <?php else: ?><span class="badge">—</span><?php endif; ?>
                    </td>
                    <td>
                        <?php $badge = match ((string) $log['status']) { 'success' => 'badge-success', 'failed' => 'badge-danger', 'rolled_back' => 'badge-warning', default => 'badge-info' }; ?>
                        <span class="badge <?= $badge ?>"><?= e(str_replace('_', ' ', (string) $log['status'])) ?></span>
                    </td>
                    <td class="small nowrap"><?= e(date('d M Y, H:i', strtotime((string) $log['started_at']))) ?></td>
                    <td class="text-right"><a class="btn btn-sm btn-secondary" href="<?= e(url('admin/updates/history/' . (int) $log['id'])) ?>">Details</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($result['data'] === []): ?><tr><td colspan="9" class="table-empty">No update has been run yet.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></div>
<?= $__view->include('partials.pagination', ['paginator' => $result, 'baseUrl' => url('admin/updates/history')]) ?>
<?php $__view->stop(); ?>
