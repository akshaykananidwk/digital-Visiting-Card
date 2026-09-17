<?php /** @var array<int,array<string,mixed>> $files @var array<int,array<string,mixed>> $notifications
 *  @var array<int,array<string,mixed>> $webhooks */
$__view->extend('layouts.admin'); ?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm btn-secondary" href="<?= e(url('admin/logs/audit')) ?>"><?= icon('shield', 15) ?> Audit trail</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="grid grid-2" style="align-items:start">
    <div class="card">
        <div class="card-header"><h2>Log files</h2></div>
        <div class="card-body" style="padding:0">
            <?php if ($files === []): ?>
                <p class="small muted" style="padding:18px;margin:0">No log files yet — that is a good sign.</p>
            <?php else: ?>
                <table class="table">
                    <tbody>
                        <?php foreach ($files as $file): ?>
                            <tr>
                                <td>
                                    <a class="small bold" href="<?= e(url('admin/logs/file/' . rawurlencode((string) $file['name']))) ?>"><?= e((string) $file['name']) ?></a>
                                    <div class="tiny muted"><?= e(human_size((int) $file['size'])) ?> · <?= e(date('d M Y, H:i', (int) $file['modified'])) ?></div>
                                </td>
                                <td class="text-right"><a class="btn btn-sm btn-secondary" href="<?= e(url('admin/logs/file/' . rawurlencode((string) $file['name']))) ?>">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Recent webhooks</h2></div>
        <div class="card-body" style="padding:0">
            <?php if ($webhooks === []): ?>
                <p class="small muted" style="padding:18px;margin:0">No webhook has been received yet.</p>
            <?php else: ?>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Event</th><th>Status</th><th>Received</th></tr></thead>
                    <tbody>
                        <?php foreach ($webhooks as $hook): ?>
                            <tr>
                                <td class="small"><?= e((string) $hook['event_type']) ?><div class="tiny muted"><?= e(substr((string) $hook['event_id'], 0, 24)) ?></div></td>
                                <td>
                                    <?php $badge = match ((string) $hook['status']) { 'processed' => 'badge-success', 'failed' => 'badge-danger', 'ignored' => 'badge-warning', default => '' }; ?>
                                    <span class="badge <?= $badge ?>"><?= e((string) $hook['status']) ?></span>
                                    <?php if (!empty($hook['error'])): ?><div class="tiny" style="color:var(--danger)"><?= e((string) $hook['error']) ?></div><?php endif; ?>
                                </td>
                                <td class="small nowrap"><?= e(date('d M, H:i', strtotime((string) $hook['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h2>Notification delivery</h2></div>
    <div class="card-body" style="padding:0">
        <?php if ($notifications === []): ?>
            <p class="small muted" style="padding:18px;margin:0">No notifications yet.</p>
        <?php else: ?>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Event</th><th>Channel</th><th>Recipient</th><th>Status</th><th>When</th></tr></thead>
                <tbody>
                    <?php foreach ($notifications as $item): ?>
                        <tr>
                            <td class="small"><?= e((string) $item['title']) ?><div class="tiny muted"><?= e((string) $item['event']) ?></div></td>
                            <td class="small"><?= e((string) $item['channel']) ?></td>
                            <td class="tiny muted"><?= e((string) ($item['recipient'] ?? '—')) ?></td>
                            <td>
                                <?php $badge = match ((string) $item['status']) { 'sent' => 'badge-success', 'failed' => 'badge-danger', default => '' }; ?>
                                <span class="badge <?= $badge ?>"><?= e((string) $item['status']) ?></span>
                            </td>
                            <td class="small nowrap"><?= e(date('d M, H:i', strtotime((string) $item['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
</div>
<?php $__view->stop(); ?>
