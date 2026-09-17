<?php /** @var array<string,mixed> $customer @var array<int,array<string,mixed>> $cards
 *  @var array<string,mixed>|null $subscription @var array<string,mixed> $plan
 *  @var array<int,array<string,mixed>> $plans @var float $balance */
$__view->extend('layouts.panel');
$customerId = (int) $customer['id'];
?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm btn-secondary" href="<?= e(url('reseller/customers')) ?>"><?= icon('arrow-left', 15) ?> Customers</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="grid" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr);align-items:start">
    <div class="stack">
        <div class="card">
            <div class="card-header">
                <h2><?= e((string) $customer['name']) ?></h2>
                <span class="badge <?= (string) $customer['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= e((string) $customer['status']) ?></span>
            </div>
            <div class="card-body">
                <table class="table">
                    <tbody>
                        <tr><td>Email</td><td class="text-right"><?= e((string) $customer['email']) ?></td></tr>
                        <tr><td>Phone</td><td class="text-right"><?= e((string) ($customer['phone'] ?? '—')) ?></td></tr>
                        <tr><td>Joined</td><td class="text-right"><?= e(date('d M Y', strtotime((string) $customer['created_at']))) ?></td></tr>
                        <tr><td>Last login</td><td class="text-right"><?= !empty($customer['last_login_at']) ? e(date('d M Y, H:i', strtotime((string) $customer['last_login_at']))) : '—' ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 style="font-size:.98rem">Cards (<?= count($cards) ?>)</h3></div>
            <div class="card-body" style="padding:0">
                <?php if ($cards === []): ?>
                    <p class="small muted" style="padding:18px;margin:0">This customer has not created a card yet.</p>
                <?php else: ?>
                    <table class="table">
                        <thead><tr><th>Card</th><th>Status</th><th>Views</th></tr></thead>
                        <tbody>
                            <?php foreach ($cards as $card): ?>
                                <tr>
                                    <td class="small">
                                        <div class="bold"><?= e((string) $card['title']) ?></div>
                                        <a class="tiny" href="<?= e(url('card/' . $card['slug'])) ?>" target="_blank" rel="noopener">/<?= e((string) $card['slug']) ?></a>
                                    </td>
                                    <td><span class="badge <?= (string) $card['status'] === 'published' ? 'badge-success' : '' ?>"><?= e((string) $card['status']) ?></span></td>
                                    <td class="small"><?= number_format((int) $card['views_count']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="stack">
        <form method="post" action="<?= e(url_path('reseller/customers/' . $customerId . '/plan')) ?>" class="card"
              data-confirm="Activate this plan? Your reseller price will be deducted from your wallet.">
            <?= csrf_field() ?>
            <div class="card-header"><h3 style="font-size:.98rem">Plan</h3></div>
            <div class="card-body">
                <div class="bold" style="font-size:1.05rem"><?= e((string) $plan['name']) ?></div>
                <?php if ($subscription !== null && !empty($subscription['ends_at'])): ?>
                    <div class="small muted mb-2">Until <?= e(date('d M Y', strtotime((string) $subscription['ends_at']))) ?></div>
                <?php endif; ?>
                <div class="tiny muted mb-2">Wallet: <?= e(money($balance)) ?></div>

                <div class="field">
                    <select name="plan_id" required>
                        <?php foreach ($plans as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= (float) $item['your_price'] > $balance && (float) $item['your_price'] > 0 ? 'disabled' : '' ?>>
                                <?= e((string) $item['name']) ?> — <?= e(money((float) $item['your_price'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-sm btn-block" type="submit">Activate / renew plan</button>
            </div>
        </form>

        <form method="post" action="<?= e(url_path('reseller/customers/' . $customerId . '/status')) ?>" class="card">
            <?= csrf_field() ?>
            <div class="card-header"><h3 style="font-size:.98rem">Status</h3></div>
            <div class="card-body">
                <div class="field">
                    <select name="status">
                        <option value="active" <?= (string) $customer['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="suspended" <?= (string) $customer['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                    <div class="hint">Suspending takes their published cards offline immediately.</div>
                </div>
                <button class="btn btn-sm btn-secondary btn-block" type="submit">Update status</button>
            </div>
        </form>

        <form method="post" action="<?= e(url_path('reseller/customers/' . $customerId . '/password')) ?>" class="card"
              data-confirm="Reset this customer's password?">
            <?= csrf_field() ?>
            <div class="card-header"><h3 style="font-size:.98rem">Reset password</h3></div>
            <div class="card-body">
                <div class="field">
                    <input class="input" type="text" name="password" placeholder="New password" required minlength="8">
                </div>
                <button class="btn btn-sm btn-secondary btn-block" type="submit">Reset password</button>
            </div>
        </form>
    </div>
</div>
<?php $__view->stop(); ?>
