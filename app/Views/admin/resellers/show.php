<?php /** @var array<string,mixed> $reseller @var array<string,mixed>|null $user
 *  @var array{data:array<int,array<string,mixed>>,total:int} $customers
 *  @var array{data:array<int,array<string,mixed>>,total:int,page:int,pages:int,from:int,to:int} $transactions
 *  @var array<string,float> $totals @var int $cardCount */
$__view->extend('layouts.admin');
$resellerId = (int) $reseller['id'];
?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm btn-secondary" href="<?= e(url('admin/resellers')) ?>"><?= icon('arrow-left', 15) ?> Resellers</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="grid grid-4 mb-3">
    <div class="stat"><div class="stat-label">Wallet balance</div><div class="stat-value"><?= e(money((float) $reseller['wallet_balance'])) ?></div></div>
    <div class="stat"><div class="stat-label">Customers</div><div class="stat-value"><?= number_format((int) $customers['total']) ?></div></div>
    <div class="stat"><div class="stat-label">Cards</div><div class="stat-value"><?= number_format($cardCount) ?></div></div>
    <div class="stat"><div class="stat-label">Total sales</div><div class="stat-value" style="font-size:1.35rem"><?= e(money((float) $reseller['total_sales'])) ?></div></div>
</div>

<div class="grid" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr);align-items:start">
    <div class="stack">
        <form method="post" action="<?= e(url('admin/resellers/' . $resellerId)) ?>" class="card">
            <?= csrf_field() ?>
            <div class="card-header"><h2><?= e((string) $reseller['company_name']) ?></h2>
                <span class="badge <?= (int) $reseller['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>"><?= (int) $reseller['is_active'] === 1 ? 'active' : 'inactive' ?></span>
            </div>
            <div class="card-body">
                <div class="grid grid-2" style="gap:0 16px">
                    <div class="field"><label class="required" for="company_name">Company name</label><input class="input" type="text" id="company_name" name="company_name" value="<?= e((string) $reseller['company_name']) ?>" required></div>
                    <div class="field"><label for="brand_name">Brand name</label><input class="input" type="text" id="brand_name" name="brand_name" value="<?= e((string) ($reseller['brand_name'] ?? '')) ?>"></div>
                </div>
                <div class="grid grid-2" style="gap:0 16px">
                    <div class="field"><label for="support_email">Support email</label><input class="input" type="email" id="support_email" name="support_email" value="<?= e((string) ($reseller['support_email'] ?? '')) ?>"></div>
                    <div class="field"><label for="support_phone">Support phone</label><input class="input" type="tel" id="support_phone" name="support_phone" value="<?= e((string) ($reseller['support_phone'] ?? '')) ?>"></div>
                </div>
                <div class="grid grid-2" style="gap:0 16px">
                    <div class="field"><label class="required" for="commission_rate">Commission (%)</label><input class="input" type="number" id="commission_rate" name="commission_rate" value="<?= e((string) $reseller['commission_rate']) ?>" min="0" max="90" step="0.01" required></div>
                    <div class="field"><label class="required" for="credit_limit">Credit limit</label><input class="input" type="number" id="credit_limit" name="credit_limit" value="<?= e((string) $reseller['credit_limit']) ?>" min="0" step="0.01" required></div>
                </div>
                <div class="field"><label for="notes">Internal notes</label><textarea class="textarea" id="notes" name="notes" rows="3"><?= e((string) ($reseller['notes'] ?? '')) ?></textarea></div>
                <label class="switch"><input type="checkbox" name="is_active" value="1" <?= (int) $reseller['is_active'] === 1 ? 'checked' : '' ?>><span class="track"></span><span class="small bold">Account active</span></label>
            </div>
            <div class="card-footer text-right"><button class="btn" type="submit">Save reseller</button></div>
        </form>

        <div class="card">
            <div class="card-header"><h3 style="font-size:.98rem">Wallet transactions</h3></div>
            <div class="card-body" style="padding:0">
                <?php if ($transactions['data'] === []): ?>
                    <p class="small muted" style="padding:18px;margin:0">No wallet activity yet.</p>
                <?php else: ?>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>Description</th><th>Type</th><th>Amount</th><th>Balance</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($transactions['data'] as $transaction): ?>
                                <tr>
                                    <td class="small"><?= e((string) ($transaction['description'] ?? '')) ?></td>
                                    <td><span class="badge <?= (string) $transaction['type'] === 'credit' ? 'badge-success' : 'badge-warning' ?>"><?= e((string) $transaction['type']) ?></span></td>
                                    <td class="small bold"><?= e(money((float) $transaction['amount'])) ?></td>
                                    <td class="small"><?= e(money((float) $transaction['balance_after'])) ?></td>
                                    <td class="small nowrap"><?= e(date('d M, H:i', strtotime((string) $transaction['created_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 style="font-size:.98rem">Customers (<?= (int) $customers['total'] ?>)</h3></div>
            <div class="card-body" style="padding:0">
                <?php if ($customers['data'] === []): ?>
                    <p class="small muted" style="padding:18px;margin:0">No customers yet.</p>
                <?php else: ?>
                    <div class="table-wrap"><table class="table">
                        <tbody>
                            <?php foreach ($customers['data'] as $customer): ?>
                                <tr>
                                    <td class="small">
                                        <a href="<?= e(url('admin/users/' . (int) $customer['id'])) ?>"><?= e((string) $customer['name']) ?></a>
                                        <div class="tiny muted"><?= e((string) $customer['email']) ?></div>
                                    </td>
                                    <td class="text-right"><span class="badge <?= (string) $customer['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= e((string) $customer['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="stack">
        <form method="post" action="<?= e(url('admin/resellers/' . $resellerId . '/wallet')) ?>" class="card">
            <?= csrf_field() ?>
            <div class="card-header"><h3 style="font-size:.98rem">Adjust wallet</h3></div>
            <div class="card-body">
                <div class="field">
                    <label for="type">Action</label>
                    <select id="type" name="type">
                        <option value="credit">Credit (add funds)</option>
                        <option value="debit">Debit (remove funds)</option>
                    </select>
                </div>
                <div class="field"><label class="required" for="amount">Amount</label><input class="input" type="number" id="amount" name="amount" step="0.01" min="0.01" required></div>
                <div class="field"><label for="description">Reference / note</label><input class="input" type="text" id="description" name="description" maxlength="250" placeholder="e.g. NEFT ref 123456"></div>
                <button class="btn btn-sm btn-block" type="submit">Apply adjustment</button>
            </div>
        </form>

        <div class="card"><div class="card-body small">
            <div class="row-between" style="padding:4px 0"><span class="muted">Code</span><strong><?= e((string) $reseller['code']) ?></strong></div>
            <div class="row-between" style="padding:4px 0"><span class="muted">Total credited</span><strong><?= e(money((float) $totals['credited'])) ?></strong></div>
            <div class="row-between" style="padding:4px 0"><span class="muted">Total spent</span><strong><?= e(money((float) $totals['debited'])) ?></strong></div>
            <?php if ($user !== null): ?>
                <div class="row-between" style="padding:4px 0"><span class="muted">Sign-in</span><a href="<?= e(url('admin/users/' . (int) $user['id'])) ?>"><?= e((string) $user['email']) ?></a></div>
            <?php endif; ?>
        </div></div>

        <?php if (!empty($reseller['domain'])): ?>
            <form method="post" action="<?= e(url('admin/resellers/' . $resellerId . '/domain')) ?>" class="card">
                <?= csrf_field() ?>
                <div class="card-header"><h3 style="font-size:.98rem">White-label domain</h3></div>
                <div class="card-body">
                    <div class="bold small"><?= e((string) $reseller['domain']) ?></div>
                    <?php $badge = match ((string) $reseller['domain_status']) { 'verified' => 'badge-success', 'pending' => 'badge-warning', 'failed' => 'badge-danger', default => '' }; ?>
                    <span class="badge <?= $badge ?> mb-2"><?= e((string) $reseller['domain_status']) ?></span>
                    <div class="row" style="gap:6px;margin-top:10px">
                        <button class="btn btn-sm btn-secondary flex-1" type="submit" name="action" value="verify">Verify DNS</button>
                        <?php if (auth()->isSuperAdmin()): ?>
                            <button class="btn btn-sm btn-ghost" type="submit" name="action" value="force"
                                    onclick="return confirm('Mark this domain as verified without a DNS check?')">Force verify</button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php $__view->stop(); ?>
