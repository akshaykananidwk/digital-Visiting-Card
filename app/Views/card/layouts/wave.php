<?php
/**
 * Wave: a curved divider separates cover from content, and contact scrolls
 * as a single row along that curve.
 *
 * @var App\Services\CardPresenter $card @var App\Core\View $__view
 */
$cover = $card->image('cover_image');
?>
<header class="dvc-cover dvc-cover-wave" <?= $cover !== null ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>>
    <?php if ($design->motif() !== ''): ?>
        <span class="dvc-cover-motif" aria-hidden="true"><?= icon($design->motif(), 132) ?></span>
    <?php endif; ?>
</header>
<div class="dvc-shell">
    <section class="dvc-hero dvc-section dvc-wave">
        <div class="dvc-hero-inner">
            <?= $__view->include('card.partials.hero-core', ['card' => $card, 'design' => $design ?? null]) ?>
        </div>
        <?= $__view->include('card.partials.actions', ['card' => $card, 'variant' => 'row']) ?>
    </section>
</div>
