<?php
/** @var array<string,mixed> $reseller @var array<string,int> $stats @var array<string,mixed> $wallet
 *  @var array<int,array<string,mixed>> $recentCustomers @var array<int,array<string,mixed>> $recentWallet
 *  @var array<int,array<string,mixed>> $expiring @var array<int,array<string,mixed>> $plans */
$__view->extend('layouts.panel');
?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm" href="<?= e(url('reseller/customers/create')) ?>"><?= icon('user-plus', 16) ?> Add customer</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="grid grid-4 mb-3">
    <div class="stat">
        <div class="stat-icon"><?= icon('wallet', 18) ?></div>
        <div class="stat-label">Wallet balance</div>
        <div class="stat-value"><?= e(money((float) $wallet['balance'])) ?></div>
        <?php if ((float) $wallet['credit'] > 0): ?>
            <div class="stat-meta">+ <?= e(money((float) $wallet['credit'])) ?> credit limit</div>
        <?php endif; ?>
    </div>
    <div class="stat"><div class="stat-icon"><?= icon('users', 18) ?></div><div class="stat-label">Customers</div><div class="stat-value"><?= number_format((int) $stats['customers']) ?></div><div class="stat-meta"><?= (int) $stats['active_customers'] ?> active</div></div>
    <div class="stat"><div class="stat-icon"><?= icon('layout', 18) ?></div><div class="stat-label">Cards</div><div class="stat-value"><?= number_format((int) $stats['cards']) ?></div><div class="stat-meta"><?= (int) $stats['published'] ?> published</div></div>
    <div class="stat"><div class="stat-icon"><?= icon('rupee', 18) ?></div><div class="stat-label">Total sales</div><div class="stat-value" style="font-size:1.4rem"><?= e(money((float) $reseller['total_sales'])) ?></div><div class="stat-meta"><?= e((string) $reseller['commission_rate']) ?>% commission</div></div>
</div>

<?php if ((float) $wallet['balance'] <= 0): ?>
    <div class="alert alert-warning">
        <?= icon('wallet', 18) ?>
        <div>Your wallet is empty. Contact the platform administrator to top it up before activating paid plans for customers.</div>
    </div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h2>Recent customers</h2><a class="small" href="<?= e(url('reseller/customers')) ?>">All</a></div>
        <div class="card-body" style="padding:0">
            <?php if ($recentCustomers === []): ?>
                <div class="empty-state" style="padding:32px 20px">
                    <div class="icon"><?= icon('users', 24) ?></div>
                    <h3 style="font-size:1rem">No customers yet</h3>
                    <a class="btn btn-sm" href="<?= e(url('reseller/customers/create')) ?>">Add your first customer</a>
                </div>
            <?php else: ?>
                <table class="table">
                    <tbody>
                        <?php foreach ($recentCustomers as $customer): ?>
                            <tr>
                                <td>
                                    <a class="small bold" href="<?= e(url('reseller/customers/' . (int) $customer['id'])) ?>"><?= e((string) $customer['name']) ?></a>
                                    <div class="tiny muted"><?= e((string) $customer['email']) ?></div>
                                </td>
                                <td class="text-right">
                                    <span class="badge <?= (string) $customer['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= e((string) $customer['status']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Wallet activity</h2><a class="small" href="<?= e(url('reseller/wallet')) ?>">All</a></div>
        <div class="card-body" style="padding:0">
            <?php if ($recentWallet === []): ?>
                <p class="small muted" style="padding:18px;margin:0">No wallet activity yet.</p>
            <?php else: ?>
                <table class="table">
                    <tbody>
                        <?php foreach ($recentWallet as $transaction): ?>
                            <tr>
                                <td>
                                    <div class="small"><?= e((string) ($transaction['description'] ?? '')) ?></div>
                                    <div class="tiny muted"><?= e(date('d M, H:i', strtotime((string) $transaction['created_at']))) ?></div>
                                </td>
                                <td class="text-right nowrap">
                                    <strong style="color:<?= (string) $transaction['type'] === 'credit' ? 'var(--success)' : 'var(--danger)' ?>">
                                        <?= (string) $transaction['type'] === 'credit' ? '+' : '−' ?><?= e(money((float) $transaction['amount'])) ?>
                                    </strong>
                                    <div class="tiny muted"><?= e(money((float) $transaction['balance_after'])) ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($expiring !== []): ?>
    <div class="card mt-3">
        <div class="card-header"><h2>Customer plans expiring soon</h2></div>
        <div class="card-body" style="padding:0">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Customer</th><th>Plan</th><th>Expires</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($expiring as $row): ?>
                        <tr>
                            <td class="small"><?= e((string) $row['name']) ?><div class="tiny muted"><?= e((string) $row['email']) ?></div></td>
                            <td class="small"><?= e((string) $row['plan_name']) ?></td>
                            <td class="small nowrap"><?= e(date('d M Y', strtotime((string) $row['ends_at']))) ?></td>
                            <td class="text-right"><a class="btn btn-sm btn-secondary" href="<?= e(url('reseller/customers/' . (int) $row['user_id'])) ?>">Renew</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
<?php endif; ?>
<?php $__view->stop(); ?>
