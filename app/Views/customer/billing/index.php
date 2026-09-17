<?php
/**
 * @var array<string,mixed> $summary @var array<int,array<string,mixed>> $plans
 * @var array<int,array<string,mixed>> $orders @var array<int,array<string,mixed>> $invoices
 * @var array<int,array<string,mixed>> $history @var bool $gatewayReady
 */
$__view->extend('layouts.panel');
$plan = $summary['plan'];
$subscription = $summary['subscription'];
?>
<?php $__view->start('content'); ?>
<div class="card mb-3">
    <div class="card-body">
        <div class="row-between">
            <div>
                <div class="label">Current plan</div>
                <h2 style="margin:0"><?= e((string) $plan['name']) ?></h2>
                <?php if ($subscription !== null && !empty($subscription['ends_at'])): ?>
                    <p class="small muted mb-0">
                        Valid until <strong><?= e(date('d M Y', strtotime((string) $subscription['ends_at']))) ?></strong>
                        <?php if ($summary['days_left'] !== null): ?>
                            · <?= (int) $summary['days_left'] ?> day(s) remaining
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="small muted mb-0">No expiry date</p>
                <?php endif; ?>
            </div>
            <div class="text-right">
                <?php if (!empty($summary['is_expiring'])): ?>
                    <span class="badge badge-warning">Expiring soon</span>
                <?php elseif ($subscription !== null): ?>
                    <span class="badge badge-success">Active</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-4 mt-3" style="gap:10px">
            <?php foreach ([
                ['Cards', (int) $plan['card_limit']],
                ['Products', (int) $plan['product_limit']],
                ['Services', (int) $plan['service_limit']],
                ['Gallery', (int) $plan['gallery_limit']],
            ] as [$label, $value]): ?>
                <div style="padding:10px;border-radius:10px;background:var(--surface-2);text-align:center">
                    <div class="bold"><?= $value < 0 ? '∞' : number_format($value) ?></div>
                    <div class="tiny muted"><?= e($label) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if (!$gatewayReady): ?>
    <div class="alert alert-info">
        <?= icon('info', 18) ?>
        <div>Online payments are not configured on this platform yet. Contact support to change your plan.</div>
    </div>
<?php endif; ?>

<h2 class="mb-2">Available plans</h2>
<div class="grid grid-3 mb-4">
    <?php foreach ($plans as $item): ?>
        <?php $isCurrent = (int) $item['id'] === (int) ($plan['id'] ?? 0); ?>
        <div class="card price-card <?= $isCurrent ? 'featured' : '' ?>">
            <?php if ($isCurrent): ?><span class="ribbon">Current plan</span><?php endif; ?>
            <div class="card-body" style="display:flex;flex-direction:column;flex:1">
                <h3 style="margin-bottom:2px"><?= e((string) $item['name']) ?></h3>
                <p class="small muted" style="min-height:36px"><?= e((string) ($item['description'] ?? '')) ?></p>
                <div class="price"><?= (float) $item['price'] <= 0 ? 'Free' : e(money((float) $item['price'])) ?></div>
                <div class="small muted mb-2"><?= (int) $item['duration_days'] ?> days</div>
                <ul>
                    <li><?= icon('check', 15) ?><span><?= (int) $item['card_limit'] < 0 ? 'Unlimited' : (int) $item['card_limit'] ?> card(s)</span></li>
                    <li><?= icon('check', 15) ?><span><?= (int) $item['product_limit'] < 0 ? 'Unlimited' : (int) $item['product_limit'] ?> products</span></li>
                    <?php if ((int) $item['premium_templates'] === 1): ?><li><?= icon('check', 15) ?><span>Premium designs</span></li><?php endif; ?>
                    <?php if ((int) $item['remove_branding'] === 1): ?><li><?= icon('check', 15) ?><span>No platform branding</span></li><?php endif; ?>
                </ul>
                <?php if ($isCurrent): ?>
                    <button class="btn btn-secondary btn-block" type="button" style="margin-top:auto" disabled>Current plan</button>
                <?php else: ?>
                    <a class="btn btn-block" style="margin-top:auto" href="<?= e(url('billing/checkout/' . $item['slug'])) ?>">
                        <?= (float) $item['price'] <= 0 ? 'Switch to Free' : 'Upgrade' ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h3 style="font-size:.98rem">Orders</h3></div>
        <div class="card-body" style="padding:0">
            <?php if ($orders === []): ?>
                <p class="small muted" style="padding:18px;margin:0">No orders yet.</p>
            <?php else: ?>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Order</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td class="small"><?= e((string) $order['order_number']) ?></td>
                                <td class="small bold"><?= e(money((float) $order['total'])) ?></td>
                                <td>
                                    <?php
                                    $badge = match ((string) $order['status']) {
                                        'paid' => 'badge-success', 'failed' => 'badge-danger',
                                        'refunded' => 'badge-warning', default => '',
                                    };
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= e((string) $order['status']) ?></span>
                                </td>
                                <td class="small nowrap"><?= e(date('d M Y', strtotime((string) $order['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 style="font-size:.98rem">Invoices</h3></div>
        <div class="card-body" style="padding:0">
            <?php if ($invoices === []): ?>
                <p class="small muted" style="padding:18px;margin:0">No invoices yet.</p>
            <?php else: ?>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Invoice</th><th>Total</th><th>Date</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td class="small"><?= e((string) $invoice['invoice_number']) ?></td>
                                <td class="small bold"><?= e(money((float) $invoice['total'])) ?></td>
                                <td class="small nowrap"><?= e(date('d M Y', strtotime((string) $invoice['issued_at']))) ?></td>
                                <td class="text-right"><a class="btn btn-sm btn-secondary" href="<?= e(url('billing/invoice/' . (int) $invoice['id'])) ?>">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__view->stop(); ?>
