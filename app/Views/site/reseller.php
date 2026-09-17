<?php $__view->extend('layouts.public'); ?>
<?php $__view->start('content'); ?>
<div class="container section">
    <div class="text-center mb-4">
        <span class="eyebrow">Partner programme</span>
        <h1>Sell digital visiting cards under your own brand</h1>
        <p class="lead" style="margin-inline:auto">Run the platform as your own product — your logo, your domain, your pricing, your customers.</p>
    </div>

    <div class="grid grid-3">
        <?php
        $benefits = [
            ['wallet', 'Prepaid wallet', 'Top up your wallet and activate plans for customers instantly — no payment gateway needed on your side.'],
            ['award', 'Your commission', 'Buy plans at your reseller price and sell at whatever price you choose.'],
            ['users', 'Customer management', 'Create accounts, assign plans, reset passwords and manage every card from one dashboard.'],
            ['layout', 'White-label branding', 'Your logo, business name, support number and email everywhere your customers look.'],
            ['link', 'Your own domain', 'Point cards.yourbusiness.com at the platform — verified with a simple DNS record.'],
            ['bar-chart', 'Full visibility', 'Track sales, wallet transactions, customers and cards in real time.'],
        ];
        foreach ($benefits as [$iconName, $heading, $text]): ?>
            <div class="card card-hover"><div class="card-body">
                <div class="feature-icon"><?= icon($iconName, 22) ?></div>
                <h3 style="font-size:1.02rem"><?= e($heading) ?></h3>
                <p class="small muted mb-0"><?= e($text) ?></p>
            </div></div>
        <?php endforeach; ?>
    </div>

    <div class="card mt-4"><div class="card-body text-center" style="padding:36px 24px">
        <h2>Ready to become a reseller?</h2>
        <p class="muted">Tell us about your business and we will set up your reseller account.</p>
        <a class="btn btn-lg" href="<?= e(url('contact?subject=Reseller+enquiry')) ?>">Apply now</a>
    </div></div>
</div>
<?php $__view->stop(); ?>
