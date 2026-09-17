<?php /** @var array<string,mixed> $order @var array<string,mixed>|null $plan @var array<string,mixed>|null $invoice @var array<string,mixed> $summary */ $__view->extend('layouts.panel'); ?>
<?php $__view->start('content'); ?>
<div class="container-sm" style="margin:0">
    <div class="card">
        <div class="card-body text-center" style="padding:40px 24px">
            <div class="feature-icon" style="margin:0 auto 18px;width:68px;height:68px;background:var(--success-soft);color:var(--success)">
                <?= icon('check-circle', 32) ?>
            </div>
            <h1 style="font-size:1.6rem">Payment successful</h1>
            <p class="muted">
                Your <strong><?= e((string) ($plan['name'] ?? 'plan')) ?></strong> plan is now active.
                <?php if (!empty($summary['subscription']['ends_at'])): ?>
                    Valid until <strong><?= e(date('d M Y', strtotime((string) $summary['subscription']['ends_at']))) ?></strong>.
                <?php endif; ?>
            </p>

            <div class="card mt-3" style="text-align:left">
                <div class="card-body small">
                    <div class="row-between" style="padding:6px 0"><span class="muted">Order number</span><strong><?= e((string) $order['order_number']) ?></strong></div>
                    <div class="row-between" style="padding:6px 0"><span class="muted">Amount paid</span><strong><?= e(money((float) $order['total'])) ?></strong></div>
                    <div class="row-between" style="padding:6px 0"><span class="muted">Date</span><strong><?= e(date('d M Y, H:i', strtotime((string) ($order['paid_at'] ?: $order['created_at'])))) ?></strong></div>
                </div>
            </div>

            <div class="row" style="justify-content:center;gap:10px;margin-top:22px">
                <a class="btn" href="<?= e(url('dashboard')) ?>">Go to dashboard</a>
                <?php if ($invoice !== null): ?>
                    <a class="btn btn-secondary" href="<?= e(url('billing/invoice/' . (int) $invoice['id'])) ?>"><?= icon('file-text', 16) ?> View invoice</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php $__view->stop(); ?>
