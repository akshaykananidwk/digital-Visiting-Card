<?php /** @var array{data:array<int,array<string,mixed>>,total:int,page:int,pages:int,from:int,to:int} $result
 *  @var float $totalSales @var float $commission */
$__view->extend('layouts.panel'); ?>
<?php $__view->start('content'); ?>
<div class="grid grid-3 mb-3">
    <div class="stat"><div class="stat-label">Total sales</div><div class="stat-value"><?= e(money($totalSales)) ?></div></div>
    <div class="stat"><div class="stat-label">Your discount</div><div class="stat-value"><?= e((string) $commission) ?>%</div></div>
    <div class="stat"><div class="stat-label">Plans sold</div><div class="stat-value"><?= number_format((int) $result['total']) ?></div></div>
</div>

<div class="card"><div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Sale</th><th>Charged</th><th>Balance after</th><th>Date</th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $sale): ?>
                <tr>
                    <td class="small"><?= e((string) ($sale['description'] ?? '')) ?></td>
                    <td class="small bold"><?= e(money((float) $sale['amount'])) ?></td>
                    <td class="small"><?= e(money((float) $sale['balance_after'])) ?></td>
                    <td class="small nowrap"><?= e(date('d M Y, H:i', strtotime((string) $sale['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($result['data'] === []): ?><tr><td colspan="4" class="table-empty">No plan sales yet.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></div>
<?= $__view->include('partials.pagination', ['paginator' => $result, 'baseUrl' => url('reseller/orders')]) ?>
<?php $__view->stop(); ?>
