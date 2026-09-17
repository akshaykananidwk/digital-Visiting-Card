<?php /** @var array{data:array<int,array<string,mixed>>,total:int,page:int,pages:int,from:int,to:int} $result
 *  @var array<string,mixed> $filters @var array<string,mixed> $stats */
$__view->extend('layouts.admin');
$query = array_filter(['q' => $filters['search'], 'active' => $filters['active']]);
?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm" href="<?= e(url('admin/resellers/create')) ?>"><?= icon('plus', 15) ?> New reseller</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="grid grid-4 mb-3">
    <div class="stat"><div class="stat-label">Resellers</div><div class="stat-value"><?= number_format((int) $stats['total']) ?></div></div>
    <div class="stat"><div class="stat-label">Active</div><div class="stat-value"><?= number_format((int) $stats['active']) ?></div></div>
    <div class="stat"><div class="stat-label">Wallet float</div><div class="stat-value" style="font-size:1.35rem"><?= e(money((float) $stats['wallet_total'])) ?></div></div>
    <div class="stat"><div class="stat-label">Total sales</div><div class="stat-value" style="font-size:1.35rem"><?= e(money((float) $stats['sales_total'])) ?></div></div>
</div>

<form method="get" action="<?= e(url('admin/resellers')) ?>" class="card mb-3">
    <div class="card-body filter-bar">
        <div class="input-group flex-1" style="min-width:220px">
            <span class="addon"><?= icon('search', 16) ?></span>
            <input class="input" type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Company, code, brand or domain…" data-live-search>
        </div>
        <select name="active" data-auto-submit aria-label="Status">
            <option value="">All</option>
            <option value="1" <?= (string) $filters['active'] === '1' ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= (string) $filters['active'] === '0' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>
</form>

<div class="card"><div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Reseller</th><th>Code</th><th>Wallet</th><th>Customers</th><th>Sales</th><th>Domain</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $reseller): ?>
                <tr>
                    <td>
                        <div class="bold"><?= e((string) $reseller['company_name']) ?></div>
                        <div class="tiny muted"><?= e((string) ($reseller['support_email'] ?? '')) ?></div>
                    </td>
                    <td class="small" style="font-family:ui-monospace,monospace"><?= e((string) $reseller['code']) ?></td>
                    <td class="small bold"><?= e(money((float) $reseller['wallet_balance'])) ?></td>
                    <td class="small"><?= number_format((int) $reseller['total_customers']) ?></td>
                    <td class="small"><?= e(money((float) $reseller['total_sales'])) ?></td>
                    <td class="small">
                        <?php if (!empty($reseller['domain'])): ?>
                            <?= e((string) $reseller['domain']) ?>
                            <?php $badge = match ((string) $reseller['domain_status']) { 'verified' => 'badge-success', 'pending' => 'badge-warning', 'failed' => 'badge-danger', default => '' }; ?>
                            <span class="badge <?= $badge ?>"><?= e((string) $reseller['domain_status']) ?></span>
                        <?php else: ?><span class="muted">—</span><?php endif; ?>
                    </td>
                    <td class="text-right"><a class="btn btn-sm btn-secondary" href="<?= e(url('admin/resellers/' . (int) $reseller['id'])) ?>">Manage</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($result['data'] === []): ?><tr><td colspan="7" class="table-empty">No resellers yet.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></div>
<?= $__view->include('partials.pagination', ['paginator' => $result, 'baseUrl' => url('admin/resellers'), 'query' => $query]) ?>
<?php $__view->stop(); ?>
