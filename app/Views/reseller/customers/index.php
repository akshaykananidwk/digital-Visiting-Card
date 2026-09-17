<?php /** @var array{data:array<int,array<string,mixed>>,total:int,page:int,pages:int,from:int,to:int} $result @var array<string,mixed> $filters */
$__view->extend('layouts.panel');
$query = array_filter(['q' => $filters['search'], 'status' => $filters['status']]);
?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm" href="<?= e(url('reseller/customers/create')) ?>"><?= icon('user-plus', 16) ?> Add customer</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<form method="get" action="<?= e(url('reseller/customers')) ?>" class="card mb-3">
    <div class="card-body filter-bar">
        <div class="input-group flex-1" style="min-width:220px">
            <span class="addon"><?= icon('search', 16) ?></span>
            <input class="input" type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Name, email or phone…" data-live-search>
        </div>
        <select name="status" data-auto-submit aria-label="Status">
            <option value="">All statuses</option>
            <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="suspended" <?= $filters['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        </select>
    </div>
</form>

<div class="card"><div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Customer</th><th>Status</th><th>Joined</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $customer): ?>
                <tr>
                    <td>
                        <div class="bold"><?= e((string) $customer['name']) ?></div>
                        <div class="tiny muted"><?= e((string) $customer['email']) ?> · <?= e((string) ($customer['phone'] ?? '')) ?></div>
                    </td>
                    <td><span class="badge <?= (string) $customer['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= e((string) $customer['status']) ?></span></td>
                    <td class="small nowrap"><?= e(date('d M Y', strtotime((string) $customer['created_at']))) ?></td>
                    <td class="text-right"><a class="btn btn-sm btn-secondary" href="<?= e(url('reseller/customers/' . (int) $customer['id'])) ?>">Manage</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($result['data'] === []): ?><tr><td colspan="4" class="table-empty">No customers yet.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></div>
<?= $__view->include('partials.pagination', ['paginator' => $result, 'baseUrl' => url('reseller/customers'), 'query' => $query]) ?>
<?php $__view->stop(); ?>
