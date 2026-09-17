<?php
/**
 * @var array<string,mixed> $report
 * @var array<int,array<string,mixed>> $cards
 * @var int $days
 * @var array<string,mixed>|null $card
 */
$__view->extend('layouts.panel');
$totals = $report['totals'] ?? [];
$period = $report['period'] ?? $totals;
$base = $card !== null ? url('analytics/' . (int) $card['id']) : url('analytics');
?>
<?php $__view->start('content'); ?>
<div class="row-between mb-3">
    <div class="row" style="gap:8px;flex-wrap:wrap">
        <a class="chip <?= $card === null ? 'active' : '' ?>" href="<?= e(url('analytics?days=' . $days)) ?>">All cards</a>
        <?php foreach ($cards as $item): ?>
            <a class="chip <?= $card !== null && (int) $card['id'] === (int) $item['id'] ? 'active' : '' ?>"
               href="<?= e(url('analytics/' . (int) $item['id'] . '?days=' . $days)) ?>"><?= e((string) $item['title']) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="row" style="gap:6px">
        <?php foreach ([7 => '7d', 30 => '30d', 90 => '90d', 365 => '1y'] as $value => $label): ?>
            <a class="chip <?= $days === $value ? 'active' : '' ?>" href="<?= e($base . '?days=' . $value) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="grid grid-4 mb-3">
    <?php foreach ([
        ['Views', $period['views'] ?? 0, 'eye'],
        ['Unique visitors', $period['unique_views'] ?? 0, 'users'],
        ['Calls', $period['calls'] ?? 0, 'phone'],
        ['WhatsApp', $period['whatsapp'] ?? 0, 'whatsapp'],
    ] as [$label, $value, $iconName]): ?>
        <div class="stat">
            <div class="stat-icon"><?= icon($iconName, 18) ?></div>
            <div class="stat-label"><?= e($label) ?></div>
            <div class="stat-value"><?= number_format((int) $value) ?></div>
            <div class="stat-meta">last <?= (int) $days ?> days</div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-4 mb-3">
    <?php foreach ([
        ['Contact saves', $period['saves'] ?? 0, 'user-plus'],
        ['Directions', $period['directions'] ?? 0, 'navigation'],
        ['Shares', $period['shares'] ?? 0, 'share'],
        ['Leads', $period['leads'] ?? 0, 'inbox'],
    ] as [$label, $value, $iconName]): ?>
        <div class="stat">
            <div class="stat-icon"><?= icon($iconName, 18) ?></div>
            <div class="stat-label"><?= e($label) ?></div>
            <div class="stat-value"><?= number_format((int) $value) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mb-3">
    <div class="card-header"><h2>Views over time</h2></div>
    <div class="card-body">
        <div class="chart" data-chart='<?= e(json_encode($report['views'] ?? [])) ?>' data-chart-label="Views"></div>
    </div>
</div>

<?php if ($card !== null): ?>
    <div class="grid grid-2">
        <div class="card">
            <div class="card-header"><h3 style="font-size:.98rem">Devices</h3></div>
            <div class="card-body">
                <?php if (($report['devices'] ?? []) === []): ?>
                    <p class="small muted mb-0">No data for this period yet.</p>
                <?php else: ?>
                    <div data-chart='<?= e(json_encode($report['devices'])) ?>' data-chart-type="bar"></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3 style="font-size:.98rem">Traffic sources</h3></div>
            <div class="card-body">
                <?php if (($report['referrers'] ?? []) === []): ?>
                    <p class="small muted mb-0">No data for this period yet.</p>
                <?php else: ?>
                    <div data-chart='<?= e(json_encode($report['referrers'])) ?>' data-chart-type="bar"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header"><h3 style="font-size:.98rem">All-time totals</h3></div>
        <div class="card-body table-wrap">
            <table class="table">
                <tbody>
                    <?php foreach ([
                        'Views' => 'views', 'Unique visitors' => 'unique_views', 'Calls' => 'calls',
                        'WhatsApp clicks' => 'whatsapp', 'Email clicks' => 'emails', 'Website clicks' => 'websites',
                        'Directions' => 'directions', 'Shares' => 'shares', 'Contact saves' => 'saves',
                        'QR scans' => 'qr_scans', 'Leads' => 'leads',
                    ] as $label => $key): ?>
                        <tr><td><?= e($label) ?></td><td class="text-right bold"><?= number_format((int) ($totals[$key] ?? 0)) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<p class="tiny muted mt-3">
    <?= icon('shield', 13) ?> Visitor privacy: we never store IP addresses. Unique visitors are counted with a salted daily hash that cannot be linked back to a person or followed across days.
</p>
<?php $__view->stop(); ?>
