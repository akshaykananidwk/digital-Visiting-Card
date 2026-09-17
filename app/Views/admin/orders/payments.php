<?php /** @var array{data:array<int,array<string,mixed>>,total:int,page:int,pages:int,from:int,to:int} $result @var array<string,mixed> $filters */
$__view->extend('layouts.admin');
$query = array_filter(['q' => $filters['search'], 'status' => $filters['status']]);
?>
<?php $__view->start('content'); ?>
<form method="get" action="<?= e(url('admin/payments')) ?>" class="card mb-3">
    <div class="card-body filter-bar">
        <div class="input-group flex-1" style="min-width:220px">
            <span class="addon"><?= icon('search', 16) ?></span>
            <input class="input" type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Payment or order id…" data-live-search>
        </div>
        <select name="status" data-auto-submit aria-label="Status">
            <option value="">All statuses</option>
            <?php foreach (['captured', 'authorized', 'failed', 'refunded', 'created'] as $value): ?>
                <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="card"><div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Payment ID</th><th>Order</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $payment): ?>
                <tr>
                    <td class="tiny"><?= e((string) ($payment['gateway_payment_id'] ?? '—')) ?></td>
                    <td class="small"><a href="<?= e(url('admin/orders/' . (int) $payment['order_id'])) ?>">#<?= (int) $payment['order_id'] ?></a></td>
                    <td class="small bold"><?= e(money((float) $payment['amount'])) ?></td>
                    <td class="small"><?= e((string) ($payment['method'] ?? '—')) ?></td>
                    <td>
                        <?php $badge = match ((string) $payment['status']) { 'captured' => 'badge-success', 'failed' => 'badge-danger', 'refunded' => 'badge-warning', default => '' }; ?>
                        <span class="badge <?= $badge ?>"><?= e((string) $payment['status']) ?></span>
                    </td>
                    <td class="small nowrap"><?= e(date('d M, H:i', strtotime((string) $payment['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($result['data'] === []): ?><tr><td colspan="6" class="table-empty">No payments recorded yet.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></div>
<?= $__view->include('partials.pagination', ['paginator' => $result, 'baseUrl' => url('admin/payments'), 'query' => $query]) ?>
<?php $__view->stop(); ?>
