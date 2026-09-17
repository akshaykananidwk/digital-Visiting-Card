<?php /** @var array<string,mixed> $order @var array<string,mixed>|null $user @var array<string,mixed>|null $plan
 *  @var array<int,array<string,mixed>> $payments @var array<string,mixed>|null $invoice */
$__view->extend('layouts.admin'); ?>
<?php $__view->start('content'); ?>
<div class="grid" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr);align-items:start">
    <div class="stack">
        <div class="card">
            <div class="card-header">
                <h2>Order <?= e((string) $order['order_number']) ?></h2>
                <span class="badge <?= (string) $order['status'] === 'paid' ? 'badge-success' : '' ?>"><?= e((string) $order['status']) ?></span>
            </div>
            <div class="card-body">
                <table class="table">
                    <tbody>
                        <tr><td>Plan</td><td class="text-right bold"><?= e((string) ($plan['name'] ?? '—')) ?></td></tr>
                        <tr><td>Subtotal</td><td class="text-right"><?= e(money((float) $order['subtotal'])) ?></td></tr>
                        <?php if ((float) $order['tax_amount'] > 0): ?>
                            <tr><td>GST (<?= e((string) $order['tax_rate']) ?>%)</td><td class="text-right"><?= e(money((float) $order['tax_amount'])) ?></td></tr>
                        <?php endif; ?>
                        <tr><td class="bold">Total</td><td class="text-right bold"><?= e(money((float) $order['total'])) ?></td></tr>
                        <tr><td>Created</td><td class="text-right small"><?= e(date('d M Y, H:i', strtotime((string) $order['created_at']))) ?></td></tr>
                        <?php if (!empty($order['paid_at'])): ?>
                            <tr><td>Paid</td><td class="text-right small"><?= e(date('d M Y, H:i', strtotime((string) $order['paid_at']))) ?></td></tr>
                        <?php endif; ?>
                        <tr><td>Gateway order</td><td class="text-right tiny muted"><?= e((string) ($order['gateway_order_id'] ?? '—')) ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 style="font-size:.98rem">Payment attempts</h3></div>
            <div class="card-body" style="padding:0">
                <?php if ($payments === []): ?>
                    <p class="small muted" style="padding:18px;margin:0">No payment attempts recorded.</p>
                <?php else: ?>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>Payment ID</th><th>Method</th><th>Amount</th><th>Status</th><th>Verified</th></tr></thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td class="tiny"><?= e((string) ($payment['gateway_payment_id'] ?? '—')) ?></td>
                                    <td class="small"><?= e((string) ($payment['method'] ?? '—')) ?></td>
                                    <td class="small"><?= e(money((float) $payment['amount'])) ?></td>
                                    <td>
                                        <?php $badge = match ((string) $payment['status']) { 'captured' => 'badge-success', 'failed' => 'badge-danger', 'refunded' => 'badge-warning', default => '' }; ?>
                                        <span class="badge <?= $badge ?>"><?= e((string) $payment['status']) ?></span>
                                    </td>
                                    <td class="tiny muted"><?= !empty($payment['verified_at']) ? e(date('d M, H:i', strtotime((string) $payment['verified_at']))) : '—' ?></td>
                                </tr>
                                <?php if (!empty($payment['error_description'])): ?>
                                    <tr><td colspan="5" class="tiny" style="color:var(--danger)"><?= e((string) $payment['error_code']) ?>: <?= e((string) $payment['error_description']) ?></td></tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="stack">
        <div class="card"><div class="card-body">
            <div class="label">Customer</div>
            <?php if ($user !== null): ?>
                <a class="bold" href="<?= e(url('admin/users/' . (int) $user['id'])) ?>"><?= e((string) $user['name']) ?></a>
                <div class="small muted"><?= e((string) $user['email']) ?></div>
                <div class="small muted"><?= e((string) ($user['phone'] ?? '')) ?></div>
            <?php else: ?>
                <div class="muted">Account removed</div>
            <?php endif; ?>
        </div></div>

        <?php if ($invoice !== null): ?>
            <div class="card"><div class="card-body">
                <div class="label">Invoice</div>
                <div class="bold"><?= e((string) $invoice['invoice_number']) ?></div>
                <a class="btn btn-secondary btn-sm btn-block mt-2" href="<?= e(url('admin/invoices/' . (int) $invoice['id'])) ?>"><?= icon('file-text', 14) ?> View invoice</a>
            </div></div>
        <?php endif; ?>

        <?php if ((string) $order['status'] === 'paid'): ?>
            <form method="post" action="<?= e(url_path('admin/orders/' . (int) $order['id'] . '/refund')) ?>" class="card" style="border-color:var(--warning)"
                  data-confirm="Refund this payment through the gateway and cancel the subscription?">
                <?= csrf_field() ?>
                <div class="card-header"><h3 style="font-size:.98rem">Refund</h3></div>
                <div class="card-body">
                    <div class="field">
                        <input class="input" type="text" name="reason" placeholder="Reason (optional)" maxlength="250">
                    </div>
                    <button class="btn btn-warning btn-sm btn-block" type="submit">Refund <?= e(money((float) $order['total'])) ?></button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php $__view->stop(); ?>
