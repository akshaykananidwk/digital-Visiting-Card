<?php /** @var array{data:array<int,array<string,mixed>>,total:int,page:int,pages:int,from:int,to:int} $result
 *  @var array<string,mixed> $filters @var array<string,float|int> $stats */
$__view->extend('layouts.admin');
$query = array_filter(['q' => $filters['search'], 'status' => $filters['status']]);
?>
<?php $__view->start('content'); ?>
<div class="grid grid-4 mb-3">
    <?php foreach ([
        ['Total revenue', money((float) $stats['total_revenue'])],
        ['This month', money((float) $stats['month_revenue'])],
        ['Paid orders', number_format((int) $stats['paid_orders'])],
        ['Failed orders', number_format((int) $stats['failed_orders'])],
    ] as [$label, $value]): ?>
        <div class="stat"><div class="stat-label"><?= e($label) ?></div><div class="stat-value" style="font-size:1.4rem"><?= $value ?></div></div>
    <?php endforeach; ?>
</div>

<form method="get" action="<?= e(url_path('admin/orders')) ?>" class="card mb-3">
    <div class="card-body filter-bar">
        <div class="input-group flex-1" style="min-width:220px">
            <span class="addon"><?= icon('search', 16) ?></span>
            <input class="input" type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Order number or gateway id…" data-live-search>
        </div>
        <select name="status" data-auto-submit aria-label="Status">
            <option value="">All statuses</option>
            <?php foreach (['paid', 'pending', 'failed', 'cancelled', 'refunded'] as $value): ?>
                <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Order</th><th>Total</th><th>Status</th><th>Gateway</th><th>Date</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($result['data'] as $order): ?>
                    <tr>
                        <td class="small bold"><?= e((string) $order['order_number']) ?></td>
                        <td class="small bold"><?= e(money((float) $order['total'])) ?></td>
                        <td>
                            <?php $badge = match ((string) $order['status']) { 'paid' => 'badge-success', 'failed' => 'badge-danger', 'refunded' => 'badge-warning', default => '' }; ?>
                            <span class="badge <?= $badge ?>"><?= e((string) $order['status']) ?></span>
                        </td>
                        <td class="tiny muted"><?= e((string) ($order['gateway_order_id'] ?? '—')) ?></td>
                        <td class="small nowrap"><?= e(date('d M Y, H:i', strtotime((string) $order['created_at']))) ?></td>
                        <td class="text-right"><a class="btn btn-sm btn-secondary" href="<?= e(url('admin/orders/' . (int) $order['id'])) ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($result['data'] === []): ?><tr><td colspan="6" class="table-empty">No orders found.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>
</div>
<?= $__view->include('partials.pagination', ['paginator' => $result, 'baseUrl' => url('admin/orders'), 'query' => $query]) ?>
<?php $__view->stop(); ?>
