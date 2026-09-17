<?php /** @var array{data:array<int,array<string,mixed>>,total:int,page:int,pages:int,from:int,to:int} $result @var array<string,mixed> $filters */
$__view->extend('layouts.admin');
$query = array_filter(['q' => $filters['search'], 'status' => $filters['status']]);
?>
<?php $__view->start('content'); ?>
<form method="get" action="<?= e(url('admin/invoices')) ?>" class="card mb-3">
    <div class="card-body filter-bar">
        <div class="input-group flex-1" style="min-width:220px">
            <span class="addon"><?= icon('search', 16) ?></span>
            <input class="input" type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Invoice number, name or email…" data-live-search>
        </div>
    </div>
</form>

<div class="card"><div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Tax</th><th>Issued</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $invoice): ?>
                <tr>
                    <td class="small bold"><?= e((string) $invoice['invoice_number']) ?></td>
                    <td class="small"><?= e((string) $invoice['billing_name']) ?><div class="tiny muted"><?= e((string) ($invoice['billing_email'] ?? '')) ?></div></td>
                    <td class="small bold"><?= e(money((float) $invoice['total'])) ?></td>
                    <td class="small"><?= e(money((float) $invoice['cgst'] + (float) $invoice['sgst'] + (float) $invoice['igst'])) ?></td>
                    <td class="small nowrap"><?= e(date('d M Y', strtotime((string) $invoice['issued_at']))) ?></td>
                    <td class="text-right"><a class="btn btn-sm btn-secondary" href="<?= e(url('admin/invoices/' . (int) $invoice['id'])) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($result['data'] === []): ?><tr><td colspan="6" class="table-empty">No invoices yet.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></div>
<?= $__view->include('partials.pagination', ['paginator' => $result, 'baseUrl' => url('admin/invoices'), 'query' => $query]) ?>
<?php $__view->stop(); ?>
