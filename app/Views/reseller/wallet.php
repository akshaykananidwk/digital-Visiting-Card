<?php /** @var float $balance @var float $creditLimit @var array<string,float> $totals
 *  @var array{data:array<int,array<string,mixed>>,total:int,page:int,pages:int,from:int,to:int} $result
 *  @var string $supportEmail @var string $supportPhone */
$__view->extend('layouts.panel'); ?>
<?php $__view->start('content'); ?>
<div class="grid grid-4 mb-3">
    <div class="stat"><div class="stat-icon"><?= icon('wallet', 18) ?></div><div class="stat-label">Balance</div><div class="stat-value"><?= e(money($balance)) ?></div></div>
    <div class="stat"><div class="stat-label">Credit limit</div><div class="stat-value" style="font-size:1.4rem"><?= e(money($creditLimit)) ?></div></div>
    <div class="stat"><div class="stat-label">Total credited</div><div class="stat-value" style="font-size:1.4rem;color:var(--success)"><?= e(money((float) $totals['credited'])) ?></div></div>
    <div class="stat"><div class="stat-label">Total spent</div><div class="stat-value" style="font-size:1.4rem;color:var(--danger)"><?= e(money((float) $totals['debited'])) ?></div></div>
</div>

<div class="card mb-3">
    <div class="card-body row-between">
        <div>
            <strong class="small">Need to top up?</strong>
            <div class="tiny muted">Wallet top-ups are processed by the platform administrator.</div>
        </div>
        <div class="row" style="gap:8px">
            <?php if ($supportPhone !== ''): ?>
                <a class="btn btn-sm btn-secondary" href="tel:<?= e($supportPhone) ?>"><?= icon('phone', 14) ?> Call support</a>
            <?php endif; ?>
            <?php if ($supportEmail !== ''): ?>
                <a class="btn btn-sm" href="mailto:<?= e($supportEmail) ?>?subject=Wallet%20top-up%20request"><?= icon('mail', 14) ?> Request top-up</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card"><div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Description</th><th>Type</th><th>Amount</th><th>Balance after</th><th>Date</th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $transaction): ?>
                <tr>
                    <td class="small"><?= e((string) ($transaction['description'] ?? '')) ?><div class="tiny muted"><?= e((string) ($transaction['reference_type'] ?? '')) ?></div></td>
                    <td><span class="badge <?= (string) $transaction['type'] === 'credit' ? 'badge-success' : 'badge-warning' ?>"><?= e((string) $transaction['type']) ?></span></td>
                    <td class="small bold" style="color:<?= (string) $transaction['type'] === 'credit' ? 'var(--success)' : 'var(--danger)' ?>">
                        <?= (string) $transaction['type'] === 'credit' ? '+' : '−' ?><?= e(money((float) $transaction['amount'])) ?>
                    </td>
                    <td class="small"><?= e(money((float) $transaction['balance_after'])) ?></td>
                    <td class="small nowrap"><?= e(date('d M Y, H:i', strtotime((string) $transaction['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($result['data'] === []): ?><tr><td colspan="5" class="table-empty">No wallet activity yet.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></div>
<?= $__view->include('partials.pagination', ['paginator' => $result, 'baseUrl' => url('reseller/wallet')]) ?>
<?php $__view->stop(); ?>
