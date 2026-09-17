<?php
/**
 * @var array<int,array<string,mixed>> $cards
 * @var array<string,int> $counts
 * @var array<string,int> $totals
 * @var array<int,array{label:string,value:int}> $series
 * @var array<int,array<string,mixed>> $recentLeads
 * @var array<string,mixed> $subscription
 * @var array{allowed:bool,used:int,limit:int,message:string} $canCreate
 */
$__view->extend('layouts.panel');
?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm" href="<?= e(url('cards/create')) ?>"><?= icon('plus', 16) ?> New card</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>

<?php if (!empty($subscription['is_expiring'])): ?>
    <div class="alert alert-warning">
        <?= icon('clock', 18) ?>
        <div>
            Your <strong><?= e((string) $subscription['plan']['name']) ?></strong> plan expires in
            <strong><?= (int) $subscription['days_left'] ?> day(s)</strong>.
            <a href="<?= e(url('billing')) ?>">Renew now</a> to keep your cards online.
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-4 mb-3">
    <?php
    $tiles = [
        ['Total cards', $counts['total'], 'layout', url('cards')],
        ['Published', $counts['published'], 'check-circle', url('cards')],
        ['Total views', $totals['views'] ?? 0, 'eye', url('analytics')],
        ['Leads', $totals['leads'] ?? 0, 'inbox', url('leads')],
    ];
    foreach ($tiles as [$label, $value, $iconName, $href]): ?>
        <a class="stat card-hover" href="<?= e($href) ?>" style="text-decoration:none;color:inherit">
            <div class="stat-icon"><?= icon($iconName, 18) ?></div>
            <div class="stat-label"><?= e($label) ?></div>
            <div class="stat-value"><?= number_format((int) $value) ?></div>
        </a>
    <?php endforeach; ?>
</div>

<div class="grid grid-4 mb-3">
    <?php
    $engagement = [
        ['Calls', $totals['calls'] ?? 0, 'phone'],
        ['WhatsApp', $totals['whatsapp'] ?? 0, 'whatsapp'],
        ['Contact saves', $totals['saves'] ?? 0, 'user-plus'],
        ['Shares', $totals['shares'] ?? 0, 'share'],
    ];
    foreach ($engagement as [$label, $value, $iconName]): ?>
        <div class="stat">
            <div class="stat-icon"><?= icon($iconName, 18) ?></div>
            <div class="stat-label"><?= e($label) ?></div>
            <div class="stat-value"><?= number_format((int) $value) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-split">
    <div class="card">
        <div class="card-header"><h2>Views — last 30 days</h2></div>
        <div class="card-body">
            <div class="chart" data-chart='<?= e(json_encode($series)) ?>' data-chart-label="Daily views"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Recent leads</h2>
            <a class="small" href="<?= e(url('leads')) ?>">View all</a>
        </div>
        <div class="card-body" style="padding:0">
            <?php if ($recentLeads === []): ?>
                <p class="muted small" style="padding:20px;margin:0">No enquiries yet. Share your card to start receiving leads.</p>
            <?php else: ?>
                <?php foreach ($recentLeads as $lead): ?>
                    <a href="<?= e(url('leads/' . (int) $lead['id'])) ?>" style="display:block;padding:13px 18px;border-bottom:1px solid var(--border);color:inherit;text-decoration:none">
                        <div class="row-between">
                            <strong class="small"><?= e((string) $lead['name']) ?></strong>
                            <?php if ((string) $lead['status'] === 'new'): ?><span class="badge badge-danger">New</span><?php endif; ?>
                        </div>
                        <div class="tiny muted"><?= e((string) ($lead['phone'] ?? '')) ?> · <?= e(date('d M, H:i', strtotime((string) $lead['created_at']))) ?></div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h2>My cards</h2>
        <?php if ($canCreate['allowed']): ?>
            <a class="btn btn-sm btn-secondary" href="<?= e(url('cards/create')) ?>"><?= icon('plus', 15) ?> Create</a>
        <?php else: ?>
            <a class="btn btn-sm btn-secondary" href="<?= e(url('billing')) ?>">Upgrade for more cards</a>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding:0">
        <?php if ($cards === []): ?>
            <div class="empty-state">
                <div class="icon"><?= icon('layout', 28) ?></div>
                <h3>You have not created a card yet</h3>
                <p class="muted">It takes about five minutes from start to shareable link.</p>
                <a class="btn" href="<?= e(url('cards/create')) ?>"><?= icon('plus', 16) ?> Create my first card</a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Card</th><th>Link</th><th>Status</th><th>Views</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($cards as $card): ?>
                            <tr>
                                <td>
                                    <div class="bold"><?= e((string) $card['title']) ?></div>
                                    <div class="tiny muted"><?= e((string) ($card['business_name'] ?? '')) ?></div>
                                </td>
                                <td class="small"><a href="<?= e(url('card/' . $card['slug'])) ?>" target="_blank" rel="noopener">/<?= e((string) $card['slug']) ?></a></td>
                                <td>
                                    <?php
                                    $badge = match ((string) $card['status']) {
                                        'published' => 'badge-success',
                                        'expired'   => 'badge-warning',
                                        'suspended' => 'badge-danger',
                                        default     => '',
                                    };
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= e((string) $card['status']) ?></span>
                                </td>
                                <td><?= number_format((int) $card['views_count']) ?></td>
                                <td class="text-right nowrap">
                                    <a class="btn btn-sm btn-secondary" href="<?= e(url('cards/' . (int) $card['id'] . '/editor')) ?>"><?= icon('edit', 14) ?> Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $__view->stop(); ?>
