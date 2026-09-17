<?php
/** @var string $version @var string $currentCommit @var string $repo @var string $branch
 *  @var bool $hasToken @var bool $configured @var string $lastCheck @var string $lastUpdate
 *  @var bool $autoBackup @var int $retention @var string $protectedPaths
 *  @var array<int,string> $defaultProtected @var array<int,array<string,mixed>> $history
 *  @var array<int,array<string,mixed>> $backups @var bool $maintenance
 *  @var bool $zipAvailable @var bool $curlAvailable */
$__view->extend('layouts.admin');
?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm btn-secondary" href="<?= e(url('admin/updates/history')) ?>"><?= icon('clipboard', 15) ?> History</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<?php if (!$zipAvailable || !$curlAvailable): ?>
    <div class="alert alert-error">
        <?= icon('alert', 18) ?>
        <div>
            The auto-updater needs both the <strong>ZipArchive</strong> and <strong>cURL</strong> PHP extensions.
            <?= !$zipAvailable ? 'ZipArchive is missing. ' : '' ?><?= !$curlAvailable ? 'cURL is missing. ' : '' ?>
            Enable them in your hosting control panel (Select PHP Version → Extensions).
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-4 mb-3">
    <div class="stat"><div class="stat-label">Installed version</div><div class="stat-value">v<?= e($version) ?></div><div class="stat-meta"><?= e($releaseDate) ?></div></div>
    <div class="stat"><div class="stat-label">Current commit</div><div class="stat-value" style="font-size:1.1rem;font-family:ui-monospace,monospace"><?= e($currentCommit !== '' ? substr($currentCommit, 0, 8) : '—') ?></div></div>
    <div class="stat"><div class="stat-label">Last checked</div><div class="stat-value" style="font-size:1rem"><?= e($lastCheck !== '' ? date('d M, H:i', strtotime($lastCheck)) : 'Never') ?></div></div>
    <div class="stat"><div class="stat-label">Last updated</div><div class="stat-value" style="font-size:1rem"><?= e($lastUpdate !== '' ? date('d M, H:i', strtotime($lastUpdate)) : 'Never') ?></div></div>
</div>

<div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,360px);align-items:start">
    <div class="stack">
        <div class="card">
            <div class="card-header"><h2>Install an update</h2>
                <?php if ($maintenance): ?><span class="badge badge-danger">Maintenance mode ON</span><?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$configured): ?>
                    <div class="alert alert-info" style="margin:0"><?= icon('info', 18) ?><div>Configure the GitHub repository on the right before checking for updates.</div></div>
                <?php else: ?>
                    <p class="small muted">
                        Connected to <strong><?= e($repo) ?></strong> on branch <strong><?= e($branch) ?></strong><?= $hasToken ? ' with an access token' : ' (public access)' ?>.
                    </p>

                    <form method="post" action="<?= e(url_path('admin/updates/check')) ?>" class="mb-3">
                        <?= csrf_field() ?>
                        <button class="btn" type="submit"><?= icon('refresh', 16) ?> Check for update</button>
                    </form>

                    <div class="alert alert-warning">
                        <?= icon('shield', 18) ?>
                        <div>
                            <strong>What "Update now" does</strong>
                            <ol style="margin:6px 0 0;padding-left:18px" class="small">
                                <li>Turns on maintenance mode</li>
                                <li>Creates and verifies a full backup (files + database)</li>
                                <li>Downloads and validates the package from GitHub</li>
                                <li>Applies the files, preserving every protected path</li>
                                <li>Runs any pending database migrations</li>
                                <li>Clears caches and runs the health check</li>
                                <li>Turns maintenance mode off — or rolls everything back if any step fails</li>
                            </ol>
                        </div>
                    </div>

                    <form method="post" action="<?= e(url_path('admin/updates/run')) ?>"
                          data-confirm="Install the update now? The site goes into maintenance mode for about a minute.">
                        <?= csrf_field() ?>
                        <div class="row" style="gap:8px">
                            <input class="input flex-1" type="text" name="confirm" placeholder="Type UPDATE to confirm" required style="max-width:240px">
                            <button class="btn btn-success" type="submit" <?= (auth()->isSuperAdmin() && $zipAvailable && $curlAvailable) ? '' : 'disabled' ?>>
                                <?= icon('rocket', 16) ?> Update now
                            </button>
                        </div>
                        <?php if (!auth()->isSuperAdmin()): ?>
                            <p class="tiny muted mt-1">Only a super administrator can install updates.</p>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 style="font-size:.98rem">Recent updates</h3><a class="small" href="<?= e(url('admin/updates/history')) ?>">All</a></div>
            <div class="card-body" style="padding:0">
                <?php if ($history === []): ?>
                    <p class="small muted" style="padding:18px;margin:0">No updates have been run yet.</p>
                <?php else: ?>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>Version</th><th>Commit</th><th>Status</th><th>When</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($history as $log): ?>
                                <tr>
                                    <td class="small"><?= e((string) ($log['from_version'] ?? '')) ?> → <?= e((string) ($log['to_version'] ?? '')) ?></td>
                                    <td class="tiny" style="font-family:ui-monospace,monospace"><?= e(substr((string) ($log['commit_hash'] ?? ''), 0, 8)) ?></td>
                                    <td>
                                        <?php $badge = match ((string) $log['status']) { 'success' => 'badge-success', 'failed' => 'badge-danger', 'rolled_back' => 'badge-warning', default => '' }; ?>
                                        <span class="badge <?= $badge ?>"><?= e(str_replace('_', ' ', (string) $log['status'])) ?></span>
                                    </td>
                                    <td class="small nowrap"><?= e(date('d M, H:i', strtotime((string) $log['started_at']))) ?></td>
                                    <td class="text-right"><a class="btn btn-sm btn-secondary" href="<?= e(url('admin/updates/history/' . (int) $log['id'])) ?>">Details</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="stack">
        <form method="post" action="<?= e(url_path('admin/updates/settings')) ?>" class="card">
            <?= csrf_field() ?>
            <div class="card-header"><h3 style="font-size:.98rem">GitHub repository</h3></div>
            <div class="card-body">
                <div class="field">
                    <label for="github_repo">Repository</label>
                    <input class="input" type="text" id="github_repo" name="github_repo" value="<?= e($repo) ?>" placeholder="owner/repository">
                </div>
                <div class="field">
                    <label for="github_branch">Branch</label>
                    <input class="input" type="text" id="github_branch" name="github_branch" value="<?= e($branch) ?>" placeholder="main">
                </div>
                <div class="field">
                    <label for="github_token">Personal access token</label>
                    <input class="input" type="password" id="github_token" name="github_token" placeholder="<?= $hasToken ? 'Stored — leave blank to keep' : 'Required for private repositories' ?>" autocomplete="off">
                    <div class="hint">Needs only <code>Contents: read</code>. Stored encrypted with your APP_KEY. Enter <code>-</code> to remove it.</div>
                </div>

                <hr>

                <label class="switch mb-2">
                    <input type="checkbox" name="auto_backup_before_update" value="1" <?= $autoBackup ? 'checked' : '' ?>>
                    <span class="track"></span><span class="small bold">Always back up before updating</span>
                </label>
                <div class="field">
                    <label for="backup_retention">Backups to keep</label>
                    <input class="input" type="number" id="backup_retention" name="backup_retention" min="1" max="50" value="<?= (int) $retention ?>">
                </div>
                <div class="field">
                    <label for="protected_paths">Protected paths (one per line)</label>
                    <textarea class="textarea" id="protected_paths" name="protected_paths" rows="5"><?= e($protectedPaths) ?></textarea>
                    <div class="hint">
                        Never overwritten. Always protected:
                        <code><?= e(implode(', ', $defaultProtected)) ?></code>
                    </div>
                </div>
            </div>
            <div class="card-footer text-right"><button class="btn btn-sm" type="submit" <?= auth()->isSuperAdmin() ? '' : 'disabled' ?>>Save &amp; test</button></div>
        </form>

        <form method="post" action="<?= e(url_path('admin/updates/maintenance')) ?>" class="card">
            <?= csrf_field() ?>
            <div class="card-header"><h3 style="font-size:.98rem">Maintenance mode</h3></div>
            <div class="card-body">
                <p class="tiny muted">Visitors see a maintenance page; administrators keep full access.</p>
                <input type="hidden" name="enabled" value="<?= $maintenance ? '0' : '1' ?>">
                <?php if (!$maintenance): ?>
                    <div class="field">
                        <input class="input" type="text" name="message" placeholder="Message shown to visitors" maxlength="200">
                    </div>
                <?php endif; ?>
                <button class="btn btn-sm btn-block <?= $maintenance ? 'btn-success' : 'btn-warning' ?>" type="submit">
                    <?= $maintenance ? 'Turn maintenance mode OFF' : 'Turn maintenance mode ON' ?>
                </button>
            </div>
        </form>

        <div class="card">
            <div class="card-header"><h3 style="font-size:.98rem">Recent backups</h3><a class="small" href="<?= e(url('admin/backups')) ?>">All</a></div>
            <div class="card-body" style="padding:0">
                <?php if ($backups === []): ?>
                    <p class="small muted" style="padding:16px;margin:0">No backups yet.</p>
                <?php else: ?>
                    <table class="table">
                        <tbody>
                            <?php foreach ($backups as $backup): ?>
                                <tr>
                                    <td class="tiny">
                                        <div class="bold"><?= e((string) $backup['name']) ?></div>
                                        <div class="muted"><?= e(date('d M, H:i', strtotime((string) $backup['created_at']))) ?> · <?= e(human_size((int) $backup['files_size'] + (int) $backup['database_size'])) ?></div>
                                    </td>
                                    <td class="text-right">
                                        <?php if ((int) $backup['verified'] === 1): ?>
                                            <span class="badge badge-success">verified</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning"><?= e((string) $backup['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php $__view->stop(); ?>
