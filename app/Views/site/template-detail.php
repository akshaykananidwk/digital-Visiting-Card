<?php
/**
 * @var array<string,mixed> $template
 * @var array<string,mixed>|null $category
 * @var bool $canUse
 * @var array<int,array<string,mixed>> $related
 */
$__view->extend('layouts.public');
?>
<?php $__view->start('content'); ?>
<div class="container section" style="padding-top:32px">
    <p class="small muted"><a href="<?= e(url('templates')) ?>">&larr; All designs</a></p>

    <div class="grid grid-aside-start" style="gap:36px;align-items:start">
        <div>
            <div class="phone-frame">
                <iframe src="<?= e(url_path('templates/preview/' . $template['code'])) ?>" title="Preview of <?= e((string) $template['name']) ?>"></iframe>
            </div>
            <p class="text-center small muted mt-2">Live preview · scroll inside the phone</p>
        </div>

        <div>
            <div class="row mb-2" style="gap:8px">
                <?php if ((int) $template['is_premium'] === 1): ?>
                    <span class="badge badge-warning">Premium design</span>
                <?php else: ?>
                    <span class="badge badge-success">Free design</span>
                <?php endif; ?>
                <span class="badge"><?= e((string) $template['theme_mode']) ?></span>
                <span class="badge"><?= e((string) $template['layout']) ?> layout</span>
            </div>

            <h1><?= e((string) $template['name']) ?></h1>
            <p class="lead"><?= e((string) $template['industry']) ?> · <?= e((string) $template['style']) ?> style · <?= e((string) $template['color_family']) ?> palette</p>

            <div class="card mt-3"><div class="card-body">
                <h3 style="font-size:1rem">Included in every design</h3>
                <div class="grid grid-2" style="gap:10px">
                    <?php foreach ([
                        'Call, WhatsApp and email buttons',
                        'Save contact (vCard) download',
                        'Google Maps directions',
                        'Services and products sections',
                        'Photo and video gallery',
                        'Business hours with open/closed status',
                        'Enquiry form with lead capture',
                        'QR code and share sheet',
                    ] as $feature): ?>
                        <div class="row small" style="gap:8px;flex-wrap:nowrap">
                            <span style="color:var(--success);flex:none"><?= icon('check', 15) ?></span>
                            <span><?= e($feature) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div></div>

            <div class="row mt-3">
                <?php if (auth()->check()): ?>
                    <a class="btn btn-lg" href="<?= e(url('cards/create?template=' . (int) $template['id'])) ?>">Use this design</a>
                <?php else: ?>
                    <a class="btn btn-lg" href="<?= e(url('register')) ?>">Use this design</a>
                <?php endif; ?>
                <a class="btn btn-lg btn-secondary" href="<?= e(url('templates/preview/' . $template['code'])) ?>" target="_blank" rel="noopener">
                    <?= icon('external', 17) ?> Open full preview
                </a>
            </div>

            <?php if (!$canUse && (int) $template['is_premium'] === 1): ?>
                <div class="alert alert-info mt-3">
                    <?= icon('award', 18) ?>
                    <div>This is a premium design. Upgrade to a plan that includes premium designs to publish it.
                        <a href="<?= e(url('pricing')) ?>">See plans</a></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($related !== []): ?>
        <h2 class="mt-4">More <?= e((string) ($category['name'] ?? 'related')) ?> designs</h2>
        <div class="template-grid">
            <?php foreach ($related as $item): ?>
                <a class="template-card" href="<?= e(url('templates/' . $item['code'])) ?>">
                    <div class="template-thumb">
                        <iframe src="<?= e(url_path('templates/preview/' . $item['code'])) ?>" title="<?= e((string) $item['name']) ?>" loading="lazy" tabindex="-1" scrolling="no"></iframe>
                    </div>
                    <div class="template-meta"><div class="name truncate"><?= e((string) $item['name']) ?></div></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php $__view->stop(); ?>
