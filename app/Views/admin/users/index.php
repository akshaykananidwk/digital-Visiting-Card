<?php
/** @var array{data:array<int,array<string,mixed>>,total:int,page:int,pages:int,from:int,to:int} $result
 *  @var array<string,mixed> $filters @var array<string,int> $stats */
$__view->extend('layouts.admin');
$query = array_filter(['q' => $filters['search'], 'role' => $filters['role'], 'status' => $filters['status']]);
?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm" href="<?= e(url('admin/users/create')) ?>"><?= icon('plus', 15) ?> New user</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="grid grid-4 mb-3">
    <?php foreach ([
        ['Total', $stats['total']], ['Active', $stats['active']],
        ['Customers', $stats['customers']], ['New this month', $stats['this_month']],
    ] as [$label, $value]): ?>
        <div class="stat"><div class="stat-label"><?= e($label) ?></div><div class="stat-value"><?= number_format((int) $value) ?></div></div>
    <?php endforeach; ?>
</div>

<form method="get" action="<?= e(url_path('admin/users')) ?>" class="card mb-3">
    <div class="card-body filter-bar">
        <div class="input-group flex-1" style="min-width:220px">
            <span class="addon"><?= icon('search', 16) ?></span>
            <input class="input" type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Name, email, phone or company…" data-live-search>
        </div>
        <select name="role" data-auto-submit aria-label="Role">
            <option value="">All roles</option>
            <?php foreach (['customer' => 'Customer', 'reseller' => 'Reseller', 'admin' => 'Admin', 'super_admin' => 'Super admin'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" data-auto-submit aria-label="Status">
            <option value="">All statuses</option>
            <?php foreach (['active' => 'Active', 'suspended' => 'Suspended', 'pending' => 'Pending'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Joined</th><th>Last login</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($result['data'] as $user): ?>
                    <tr>
                        <td>
                            <div class="bold"><?= e((string) $user['name']) ?></div>
                            <div class="tiny muted"><?= e((string) $user['email']) ?><?= !empty($user['phone']) ? ' · ' . e((string) $user['phone']) : '' ?></div>
                        </td>
                        <td><span class="badge"><?= e(str_replace('_', ' ', (string) $user['role'])) ?></span></td>
                        <td>
                            <?php $badge = match ((string) $user['status']) { 'active' => 'badge-success', 'suspended' => 'badge-danger', default => 'badge-warning' }; ?>
                            <span class="badge <?= $badge ?>"><?= e((string) $user['status']) ?></span>
                        </td>
                        <td class="small nowrap"><?= e(date('d M Y', strtotime((string) $user['created_at']))) ?></td>
                        <td class="small nowrap muted"><?= !empty($user['last_login_at']) ? e(date('d M, H:i', strtotime((string) $user['last_login_at']))) : '—' ?></td>
                        <td class="text-right"><a class="btn btn-sm btn-secondary" href="<?= e(url('admin/users/' . (int) $user['id'])) ?>">Manage</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($result['data'] === []): ?>
                    <tr><td colspan="6" class="table-empty">No users matched that search.</td></tr>
                <?php endif; ?>
            </tbody>
        </table></div>
    </div>
</div>
<?= $__view->include('partials.pagination', ['paginator' => $result, 'baseUrl' => url('admin/users'), 'query' => $query]) ?>
<?php $__view->stop(); ?>
