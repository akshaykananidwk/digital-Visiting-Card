<?php
/** @var array<string,mixed> $plan */
$features = is_array($plan['features'] ?? null) ? $plan['features'] : [];
$isFree = (int) ($plan['is_free'] ?? 0) === 1 || (float) $plan['price'] <= 0;
?>
<div class="card price-card <?= (int) ($plan['is_featured'] ?? 0) === 1 ? 'featured' : '' ?>">
    <?php if ((int) ($plan['is_featured'] ?? 0) === 1): ?><span class="ribbon">Most popular</span><?php endif; ?>
    <div class="card-body" style="display:flex;flex-direction:column;flex:1">
        <h3 style="margin-bottom:2px"><?= e((string) $plan['name']) ?></h3>
        <p class="small muted" style="min-height:38px"><?= e((string) ($plan['description'] ?? '')) ?></p>

        <div class="price">
            <?= $isFree ? 'Free' : e(money((float) $plan['price'])) ?>
            <?php if (!$isFree): ?>
                <span class="small muted" style="font-weight:500">/ <?= (int) $plan['duration_days'] ?> days</span>
            <?php endif; ?>
        </div>
        <?php if (!$isFree && !empty($plan['mrp']) && (float) $plan['mrp'] > (float) $plan['price']): ?>
            <div class="small muted"><del><?= e(money((float) $plan['mrp'])) ?></del> save <?= (int) round((1 - (float) $plan['price'] / (float) $plan['mrp']) * 100) ?>%</div>
        <?php endif; ?>

        <ul>
            <li><?= icon('check', 15) ?> <span><?= (int) $plan['card_limit'] < 0 ? 'Unlimited' : (int) $plan['card_limit'] ?> digital card<?= (int) $plan['card_limit'] === 1 ? '' : 's' ?></span></li>
            <li><?= icon('check', 15) ?> <span><?= (int) $plan['product_limit'] < 0 ? 'Unlimited' : (int) $plan['product_limit'] ?> products &amp; <?= (int) $plan['service_limit'] < 0 ? 'unlimited' : (int) $plan['service_limit'] ?> services</span></li>
            <li><?= icon('check', 15) ?> <span><?= (int) $plan['gallery_limit'] < 0 ? 'Unlimited' : (int) $plan['gallery_limit'] ?> gallery images</span></li>
            <?php foreach (array_slice($features, 0, 4) as $feature): ?>
                <li><?= icon('check', 15) ?> <span><?= e((string) $feature) ?></span></li>
            <?php endforeach; ?>
            <?php if ((int) ($plan['premium_templates'] ?? 0) === 1): ?>
                <li><?= icon('check', 15) ?> <span>Premium &amp; 3D designs</span></li>
            <?php endif; ?>
            <?php if ((int) ($plan['remove_branding'] ?? 0) === 1): ?>
                <li><?= icon('check', 15) ?> <span>Remove platform branding</span></li>
            <?php endif; ?>
            <?php if ((int) ($plan['custom_domain'] ?? 0) === 1): ?>
                <li><?= icon('check', 15) ?> <span>Custom domain ready</span></li>
            <?php endif; ?>
        </ul>

        <a class="btn <?= (int) ($plan['is_featured'] ?? 0) === 1 ? '' : 'btn-secondary' ?> btn-block" style="margin-top:auto"
           href="<?= e(auth()->check() ? url('billing/checkout/' . $plan['slug']) : url('register')) ?>">
            <?= $isFree ? 'Start free' : 'Choose ' . e((string) $plan['name']) ?>
        </a>
    </div>
</div>
