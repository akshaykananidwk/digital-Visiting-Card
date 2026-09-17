<?php /** @var array<string,mixed> $result */ $__view->extend('layouts.admin'); ?>
<?php $__view->start('content'); ?>
<div style="max-width:820px">
    <?php if ($result['error'] !== null): ?>
        <div class="alert alert-error"><?= icon('alert', 18) ?><div><?= e((string) $result['error']) ?></div></div>
        <a class="btn btn-secondary" href="<?= e(url('admin/updates')) ?>">Back to updates</a>
    <?php else: ?>
        <div class="card mb-3">
            <div class="card-header">
                <h2><?= $result['available'] ? 'Update available' : 'You are up to date' ?></h2>
                <span class="badge <?= $result['available'] ? 'badge-warning' : 'badge-success' ?>">
                    <?= $result['available'] ? 'action needed' : 'current' ?>
                </span>
            </div>
            <div class="card-body">
                <div class="grid grid-2 mb-3">
                    <div>
                        <div class="label">Installed</div>
                        <div class="bold">v<?= e((string) $result['current_version']) ?></div>
                        <div class="tiny muted" style="font-family:ui-monospace,monospace"><?= e(substr((string) $result['current_commit'], 0, 12) ?: '—') ?></div>
                    </div>
                    <div>
                        <div class="label">Available</div>
                        <div class="bold">v<?= e((string) $result['latest_version']) ?></div>
                        <div class="tiny muted" style="font-family:ui-monospace,monospace"><?= e((string) ($result['commit']['short'] ?? '—')) ?></div>
                    </div>
                </div>

                <?php if (!empty($result['commit'])): ?>
                    <div class="card" style="background:var(--surface-2)">
                        <div class="card-body">
                            <div class="small bold">Latest commit</div>
                            <p class="small" style="white-space:pre-line;margin:6px 0"><?= e((string) ($result['commit']['message'] ?? '')) ?></p>
                            <div class="tiny muted">
                                by <?= e((string) ($result['commit']['author'] ?? '')) ?>
                                <?php if (!empty($result['commit']['date'])): ?>
                                    on <?= e(date('d M Y, H:i', strtotime((string) $result['commit']['date']))) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (($result['commits'] ?? []) !== []): ?>
            <div class="card mb-3">
                <div class="card-header"><h3 style="font-size:.98rem">Changelog (<?= count($result['commits']) ?> commits)</h3></div>
                <div class="card-body" style="padding:0">
                    <div class="table-wrap"><table class="table">
                        <tbody>
                            <?php foreach (array_slice($result['commits'], 0, 40) as $commit): ?>
                                <tr>
                                    <td class="tiny" style="font-family:ui-monospace,monospace;width:90px"><?= e((string) $commit['sha']) ?></td>
                                    <td class="small"><?= e((string) $commit['message']) ?></td>
                                    <td class="tiny muted nowrap"><?= e((string) $commit['author']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (($result['files'] ?? []) !== []): ?>
            <div class="card mb-3">
                <div class="card-header"><h3 style="font-size:.98rem">Changed files (<?= count($result['files']) ?>)</h3></div>
                <div class="card-body" style="max-height:320px;overflow:auto">
                    <?php foreach ($result['files'] as $file): ?>
                        <div class="small" style="padding:3px 0;font-family:ui-monospace,monospace">
                            <?php
                            $colour = match ((string) $file['status']) {
                                'added' => 'var(--success)', 'removed' => 'var(--danger)', default => 'var(--muted)',
                            };
                            ?>
                            <span style="color:<?= $colour ?>;display:inline-block;width:80px"><?= e((string) $file['status']) ?></span>
                            <?= e((string) $file['filename']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="row" style="gap:10px">
            <a class="btn btn-secondary" href="<?= e(url('admin/updates')) ?>">Back</a>
            <?php if ($result['available'] && auth()->isSuperAdmin()): ?>
                <form method="post" action="<?= e(url('admin/updates/run')) ?>" class="row" style="gap:8px"
                      data-confirm="Install this update now?">
                    <?= csrf_field() ?>
                    <input class="input" type="text" name="confirm" placeholder="Type UPDATE" required style="max-width:180px">
                    <button class="btn btn-success" type="submit"><?= icon('rocket', 16) ?> Update now</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php $__view->stop(); ?>
