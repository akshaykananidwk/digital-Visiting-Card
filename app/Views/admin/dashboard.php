<?php
/**
 * @var array<string,int> $userStats @var array<string,int> $cardStats
 * @var array<string,float|int> $revenue @var array<string,mixed> $resellers
 * @var array<string,int> $templates @var array<string,array<int,mixed>> $charts
 * @var array<int,array<string,mixed>> $expiring @var array<int,array<string,mixed>> $topTemplates
 * @var array<int,array<string,mixed>> $recentAudit
 */
$__view->extend('layouts.admin');
?>
<?php $__view->start('content'); ?>
<div class="grid grid-4 mb-3">
    <?php foreach ([
        ['Total revenue', money((float) $revenue['total_revenue']), 'rupee', 'This month: ' . money((float) $revenue['month_revenue'])],
        ['Users', number_format((int) $userStats['total']), 'users', $userStats['active'] . ' active'],
        ['Cards', number_format((int) $cardStats['total']), 'layout', $cardStats['published'] . ' published'],
        ['Total views', number_format((int) $totalViews), 'eye', number_format((int) $leads) . ' leads'],
    ] as [$label, $value, $iconName, $meta]): ?>
        <div class="stat">
            <div class="stat-icon"><?= icon($iconName, 18) ?></div>
            <div class="stat-label"><?= e($label) ?></div>
            <div class="stat-value"><?= $value ?></div>
            <div class="stat-meta"><?= e($meta) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-4 mb-3">
    <?php foreach ([
        ['Paid orders', number_format((int) $revenue['paid_orders']), 'package'],
        ['Pending orders', number_format((int) $revenue['pending_orders']), 'clock'],
        ['Resellers', number_format((int) $resellers['total']), 'briefcase'],
        ['Templates', number_format((int) $templates['active']), 'layers'],
    ] as [$label, $value, $iconName]): ?>
        <div class="stat">
            <div class="stat-icon"><?= icon($iconName, 18) ?></div>
            <div class="stat-label"><?= e($label) ?></div>
            <div class="stat-value"><?= $value ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-2 mb-3">
    <div class="card">
        <div class="card-header"><h2>Revenue — last 30 days</h2></div>
        <div class="card-body">
            <div class="chart" data-chart='<?= e(json_encode($charts['revenue'])) ?>' data-chart-color="#059669" data-chart-label="Daily revenue"></div>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Card views — last 30 days</h2></div>
        <div class="card-body">
            <div class="chart" data-chart='<?= e(json_encode($charts['views'])) ?>' data-chart-label="Daily views"></div>
        </div>
    </div>
</div>

<div class="grid grid-2 mb-3">
    <div class="card">
        <div class="card-header"><h2>New users</h2></div>
        <div class="card-body">
            <div class="chart" data-chart='<?= e(json_encode($charts['users'])) ?>' data-chart-color="#7c3aed"></div>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>New cards</h2></div>
        <div class="card-body">
            <div class="chart" data-chart='<?= e(json_encode($charts['cards'])) ?>' data-chart-color="#ea580c"></div>
        </div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h2>Expiring within 14 days</h2><a class="small" href="<?= e(url('admin/users')) ?>">All users</a></div>
        <div class="card-body" style="padding:0">
            <?php if ($expiring === []): ?>
                <p class="small muted" style="padding:18px;margin:0">No subscriptions expiring soon.</p>
            <?php else: ?>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Customer</th><th>Plan</th><th>Expires</th></tr></thead>
                    <tbody>
                        <?php foreach ($expiring as $row): ?>
                            <tr>
                                <td class="small">
                                    <a href="<?= e(url('admin/users/' . (int) $row['user_id'])) ?>"><?= e((string) $row['user_name']) ?></a>
                                    <div class="tiny muted"><?= e((string) $row['user_email']) ?></div>
                                </td>
                                <td class="small"><?= e((string) $row['plan_name']) ?></td>
                                <td class="small nowrap"><?= e(date('d M Y', strtotime((string) $row['ends_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Most used templates</h2><a class="small" href="<?= e(url('admin/templates')) ?>">All templates</a></div>
        <div class="card-body">
            <?php if ($topTemplates === []): ?>
                <p class="small muted mb-0">No template usage yet.</p>
            <?php else: ?>
                <div data-chart='<?= e(json_encode(array_map(static fn (array $row): array => ['label' => $row['name'], 'value' => (int) $row['cards']], $topTemplates))) ?>' data-chart-type="bar"></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h2>Recent admin activity</h2><a class="small" href="<?= e(url('admin/logs/audit')) ?>">Full audit trail</a></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Action</th><th>Entity</th><th>IP</th><th>When</th></tr></thead>
            <tbody>
                <?php foreach ($recentAudit as $entry): ?>
                    <tr>
                        <td class="small bold"><?= e((string) $entry['action']) ?></td>
                        <td class="small muted"><?= e((string) ($entry['entity_type'] ?? '')) ?><?= $entry['entity_id'] !== null ? ' #' . (int) $entry['entity_id'] : '' ?></td>
                        <td class="small muted"><?= e((string) ($entry['ip_address'] ?? '')) ?></td>
                        <td class="small nowrap muted"><?= e(date('d M, H:i', strtotime((string) $entry['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</div>
<?php $__view->stop(); ?>
