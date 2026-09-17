<?php
/**
 * Public digital card document.
 *
 * @var App\Core\View $__view
 * @var App\Services\CardPresenter $card
 * @var App\Services\TemplateRenderer $design
 * @var bool $showBranding
 * @var bool $trackingEnabled
 * @var string $qrDataUri
 */
$sections = $card->visibleSections();
$seoTitle = $card->seoTitle();
$seoDescription = $card->seoDescription();
$seoImage = $card->seoImage();
?><!doctype html>
<html lang="<?= e(setting('site_locale') ?: 'en') ?>" data-base="<?= e(url('/')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="<?= e($design->color('primary')) ?>">
<title><?= e($seoTitle) ?></title>
<meta name="description" content="<?= e($seoDescription) ?>">
<?php if ($card->has('seo_keywords')): ?>
<meta name="keywords" content="<?= e((string) $card->get('seo_keywords')) ?>">
<?php endif; ?>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="canonical" href="<?= e($card->url()) ?>">

<meta property="og:type" content="profile">
<meta property="og:site_name" content="<?= e($branding['name']) ?>">
<meta property="og:title" content="<?= e($seoTitle) ?>">
<meta property="og:description" content="<?= e($seoDescription) ?>">
<meta property="og:url" content="<?= e($card->url()) ?>">
<?php if ($seoImage !== null): ?>
<meta property="og:image" content="<?= e($seoImage) ?>">
<meta name="twitter:image" content="<?= e($seoImage) ?>">
<?php endif; ?>
<meta name="twitter:card" content="<?= $seoImage !== null ? 'summary_large_image' : 'summary' ?>">
<meta name="twitter:title" content="<?= e($seoTitle) ?>">
<meta name="twitter:description" content="<?= e($seoDescription) ?>">

<link rel="icon" href="<?= e($card->image('logo_image') ?? $card->image('profile_image') ?? ($branding['favicon'] ?? url('assets/img/icon-192.png'))) ?>">
<link rel="apple-touch-icon" href="<?= e($card->image('profile_image') ?? url('assets/img/icon-192.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= e($design->fontUrl()) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/card.css')) ?>">
<style>:root{<?= $design->cssVariables() ?>}</style>

<script type="application/ld+json"><?= json_encode($card->structuredData(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
</head>
<body>
<div class="<?= e($design->bodyClasses()) ?>"
     data-slug="<?= e((string) $card->card()['slug']) ?>"
     data-track="<?= $trackingEnabled ? '1' : '0' ?>"
     data-reduced-motion="<?= setting('reduced_motion_default') ? '1' : '0' ?>">

    <?= $__view->include('card.layouts.' . $design->layout(), ['card' => $card, 'design' => $design, '__view' => $__view]) ?>

    <div class="dvc-shell">
        <?php foreach ($sections as $section): ?>
            <?= $__view->includeIf('card.sections.' . $section, [
                'card'   => $card,
                'design' => $design,
                'title'  => $card->sectionTitle($section),
                'qrDataUri' => $qrDataUri ?? '',
                '__view' => $__view,
            ]) ?>
        <?php endforeach; ?>

        <footer class="dvc-footer">
            <?php if ($showBranding): ?>
                <p style="margin:0 0 6px">
                    Powered by <a href="<?= e(url('/')) ?>" rel="noopener"><?= e($branding['name']) ?></a>
                </p>
                <p style="margin:0">
                    <a class="dvc-btn ghost" style="min-height:40px;font-size:.8rem" href="<?= e(url('register')) ?>">Create your own digital card</a>
                </p>
            <?php endif; ?>
            <p style="margin:10px 0 0" class="tiny">
                <button type="button" data-motion-toggle aria-pressed="false" style="background:none;border:0;color:inherit;text-decoration:underline;font-size:inherit;padding:0">
                    Toggle animations
                </button>
            </p>
        </footer>
    </div>

    <?= $__view->include('card.partials.sticky', ['card' => $card]) ?>
</div>

<?= $__view->include('card.partials.share', ['card' => $card]) ?>

<div class="dvc-lightbox" data-lightbox>
    <button class="close" type="button" data-lightbox-close aria-label="Close"><?= icon('x', 22) ?></button>
    <div data-lightbox-content></div>
</div>

<script src="<?= e(asset('assets/js/card.js')) ?>" defer></script>
</body>
</html>
