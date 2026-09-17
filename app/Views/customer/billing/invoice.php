<?php
/** @var array<string,mixed> $invoice @var array<string,mixed>|null $order @var array<string,string> $seller */
$__view->extend('layouts.panel');
$lineItems = is_array($invoice['line_items'] ?? null) ? $invoice['line_items'] : [];
$hasGst = (float) $invoice['cgst'] + (float) $invoice['sgst'] + (float) $invoice['igst'] > 0;
?>
<?php $__view->start('topbar'); ?>
<button class="btn btn-sm btn-secondary no-print" type="button" onclick="window.print()"><?= icon('file-text', 15) ?> Print / Save PDF</button>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="card" style="max-width:820px">
    <div class="card-body" style="padding:32px">
        <div class="row-between mb-4" style="align-items:flex-start">
            <div>
                <h1 style="font-size:1.5rem;margin-bottom:4px"><?= $hasGst ? 'TAX INVOICE' : 'INVOICE' ?></h1>
                <div class="small muted"><?= e((string) $invoice['invoice_number']) ?></div>
                <div class="small muted">Date: <?= e(date('d M Y', strtotime((string) $invoice['issued_at']))) ?></div>
            </div>
            <div class="text-right small">
                <div class="bold"><?= e($seller['name']) ?></div>
                <?php if ($seller['address'] !== ''): ?><div class="muted" style="white-space:pre-line"><?= e($seller['address']) ?></div><?php endif; ?>
                <?php if ($seller['gstin'] !== ''): ?><div class="muted">GSTIN: <?= e($seller['gstin']) ?></div><?php endif; ?>
                <?php if ($seller['email'] !== ''): ?><div class="muted"><?= e($seller['email']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="grid grid-2 mb-4">
            <div>
                <div class="label">Billed to</div>
                <div class="bold"><?= e((string) $invoice['billing_name']) ?></div>
                <?php if (!empty($invoice['billing_address'])): ?><div class="small muted"><?= e((string) $invoice['billing_address']) ?></div><?php endif; ?>
                <?php if (!empty($invoice['billing_email'])): ?><div class="small muted"><?= e((string) $invoice['billing_email']) ?></div><?php endif; ?>
                <?php if (!empty($invoice['billing_gstin'])): ?><div class="small muted">GSTIN: <?= e((string) $invoice['billing_gstin']) ?></div><?php endif; ?>
            </div>
            <div class="text-right">
                <?php if (!empty($invoice['place_of_supply'])): ?>
                    <div class="label">Place of supply</div>
                    <div class="small"><?= e((string) $invoice['place_of_supply']) ?></div>
                <?php endif; ?>
                <?php if ($order !== null): ?>
                    <div class="label mt-2">Order</div>
                    <div class="small"><?= e((string) $order['order_number']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Description</th><th>HSN/SAC</th><th class="text-right">Qty</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($lineItems as $item): ?>
                        <tr>
                            <td><?= e((string) ($item['description'] ?? '')) ?></td>
                            <td class="small muted"><?= e((string) ($item['hsn'] ?? '')) ?></td>
                            <td class="text-right"><?= (int) ($item['quantity'] ?? 1) ?></td>
                            <td class="text-right"><?= e(money((float) ($item['amount'] ?? 0))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="max-width:320px;margin-left:auto;margin-top:16px">
            <div class="row-between small" style="padding:5px 0"><span>Subtotal</span><span><?= e(money((float) $invoice['subtotal'])) ?></span></div>
            <?php if ((float) $invoice['discount'] > 0): ?>
                <div class="row-between small" style="padding:5px 0"><span>Discount</span><span>−<?= e(money((float) $invoice['discount'])) ?></span></div>
            <?php endif; ?>
            <?php if ((float) $invoice['cgst'] > 0): ?>
                <div class="row-between small" style="padding:5px 0"><span>CGST</span><span><?= e(money((float) $invoice['cgst'])) ?></span></div>
                <div class="row-between small" style="padding:5px 0"><span>SGST</span><span><?= e(money((float) $invoice['sgst'])) ?></span></div>
            <?php elseif ((float) $invoice['igst'] > 0): ?>
                <div class="row-between small" style="padding:5px 0"><span>IGST</span><span><?= e(money((float) $invoice['igst'])) ?></span></div>
            <?php endif; ?>
            <div class="row-between" style="padding:11px 0;border-top:2px solid var(--border);font-size:1.1rem">
                <strong>Total</strong><strong><?= e(money((float) $invoice['total'])) ?></strong>
            </div>
        </div>

        <p class="tiny muted mt-4">This is a computer-generated invoice and does not require a signature.</p>
    </div>
</div>
<?php $__view->stop(); ?>
