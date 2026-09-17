<?php /** @var array<int,array<string,mixed>> $backups @var int $totalSize @var int $retention
 *  @var float $diskFree @var bool $zipAvailable */
$__view->extend('layouts.admin'); ?>
<?php $__view->start('content'); ?>
<?php if (!$zipAvailable): ?>
    <div class="alert alert-error"><?= icon('alert', 18) ?><div>The ZipArchive extension is missing, so file backups cannot be created. Database-only backups still work.</div></div>
<?php endif; ?>

<div class="grid grid-4 mb-3">
    <div class="stat"><div class="stat-label">Backups stored</div><div class="stat-value"><?= count($backups) ?></div></div>
    <div class="stat"><div class="stat-label">Space used</div><div class="stat-value" style="font-size:1.3rem"><?= e(human_size($totalSize)) ?></div></div>
    <div class="stat"><div class="stat-label">Disk free</div><div class="stat-value" style="font-size:1.3rem"><?= e(human_size($diskFree)) ?></div></div>
    <div class="stat"><div class="stat-label">Retention</div><div class="stat-value">Last <?= (int) $retention ?></div></div>
</div>

<div class="card mb-3">
    <div class="card-header"><h2>Create a backup</h2></div>
    <div class="card-body">
        <form method="post" action="<?= e(url_path('admin/backups')) ?>" class="row" style="gap:12px;align-items:flex-end;flex-wrap:wrap"
              data-confirm="Create a backup now? On a large site this can take a minute.">
            <?= csrf_field() ?>
            <div class="field" style="margin:0;min-width:180px">
                <label for="type">What to back up</label>
                <select id="type" name="type">
                    <option value="full">Files + database (recommended)</option>
                    <option value="database">Database only</option>
                    <option value="files">Files only</option>
                </select>
            </div>
            <label class="checkbox" style="margin-bottom:10px">
                <input type="checkbox" name="include_uploads" value="1">
                <span class="small">Include the uploads folder<br><span class="tiny muted">Much larger, but a complete snapshot</span></span>
            </label>
            <button class="btn" type="submit" <?= auth()->isSuperAdmin() ? '' : 'disabled' ?>><?= icon('archive', 16) ?> Create backup</button>
        </form>
        <?php if (!auth()->isSuperAdmin()): ?>
            <p class="tiny muted mt-2">Only a super administrator can create or restore backups.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Stored backups</h2></div>
    <div class="card-body" style="padding:0">
        <?php if ($backups === []): ?>
            <div class="empty-state">
                <div class="icon"><?= icon('archive', 28) ?></div>
                <h3>No backups yet</h3>
                <p class="muted">A backup is created automatically before every update, and you can create one manually at any time.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Backup</th><th>Type</th><th>Size</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td>
                                <div class="bold small"><?= e((string) $backup['name']) ?></div>
                                <div class="tiny muted">v<?= e((string) ($backup['app_version'] ?? '?')) ?> · <?= e((string) $backup['trigger_source']) ?></div>
                            </td>
                            <td class="small"><?= e((string) $backup['type']) ?></td>
                            <td class="small"><?= e(human_size((int) $backup['files_size'] + (int) $backup['database_size'])) ?></td>
                            <td>
                                <?php if ((string) $backup['status'] === 'completed' && (int) $backup['verified'] === 1): ?>
                                    <span class="badge badge-success">verified</span>
                                <?php elseif ((string) $backup['status'] === 'failed'): ?>
                                    <span class="badge badge-danger" title="<?= e((string) ($backup['error'] ?? '')) ?>">failed</span>
                                <?php else: ?>
                                    <span class="badge badge-warning"><?= e((string) $backup['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="small nowrap"><?= e(date('d M Y, H:i', strtotime((string) $backup['created_at']))) ?></td>
                            <td class="nowrap">
                                <?php if (auth()->isSuperAdmin()): ?>
                                    <?php if (!empty($backup['files_path'])): ?>
                                        <a class="btn btn-sm btn-ghost" title="Download files" href="<?= e(url('admin/backups/' . (int) $backup['id'] . '/download/files')) ?>"><?= icon('download', 13) ?> zip</a>
                                    <?php endif; ?>
                                    <?php if (!empty($backup['database_path'])): ?>
                                        <a class="btn btn-sm btn-ghost" title="Download SQL" href="<?= e(url('admin/backups/' . (int) $backup['id'] . '/download/database')) ?>"><?= icon('download', 13) ?> sql</a>
                                    <?php endif; ?>
                                    <form method="post" action="<?= e(url_path('admin/backups/' . (int) $backup['id'] . '/verify')) ?>" style="display:inline">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-ghost" type="submit" title="Verify integrity"><?= icon('shield', 13) ?></button>
                                    </form>
                                    <button class="btn btn-sm btn-secondary" type="button" data-modal-open="restore-modal"
                                            data-modal-action="<?= e(url('admin/backups/' . (int) $backup['id'] . '/restore')) ?>"
                                            data-modal-fill='{"confirm":""}'><?= icon('refresh', 13) ?> Restore</button>
                                    <form method="post" action="<?= e(url_path('admin/backups/' . (int) $backup['id'] . '/delete')) ?>" style="display:inline" data-confirm="Delete this backup permanently?">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-ghost" type="submit"><?= icon('trash', 13) ?></button>
                                    </form>
                                <?php else: ?>
                                    <span class="tiny muted">Super admin only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-backdrop" id="restore-modal">
    <div class="modal" role="dialog" aria-label="Restore backup">
        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="modal-head"><h3>Restore backup</h3><button class="btn btn-ghost btn-icon" type="button" data-modal-close><?= icon('x', 18) ?></button></div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <?= icon('alert', 18) ?>
                    <div>This replaces your current application files and/or database with the contents of the backup. Protected paths (.env, uploads, storage) are never overwritten.</div>
                </div>
                <label class="checkbox"><input type="checkbox" name="restore_files" value="1" checked><span class="small">Restore application files</span></label>
                <label class="checkbox mb-2"><input type="checkbox" name="restore_database" value="1" checked><span class="small">Restore the database</span></label>
                <div class="field">
                    <label class="required" for="restore_confirm">Type RESTORE to confirm</label>
                    <input class="input" type="text" id="restore_confirm" name="confirm" placeholder="RESTORE" required>
                </div>
            </div>
            <div class="modal-foot">
                <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
                <button class="btn btn-warning" type="submit">Restore now</button>
            </div>
        </form>
    </div>
</div>
<?php $__view->stop(); ?>
