<?php
/**
 * Hero: the identity sits on top of a full-bleed cover, so the photograph or
 * gradient is the first thing seen rather than a band above the card.
 *
 * @var App\Services\CardPresenter $card @var App\Core\View $__view
 */
$cover = $card->image('cover_image');
?>
<header class="dvc-cover dvc-cover-full" <?= $cover !== null ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>>
    <?php if (($design->motif() ?? '') !== ''): ?>
        <span class="dvc-cover-motif" aria-hidden="true"><?= icon($design->motif(), 132) ?></span>
    <?php endif; ?>
    <div class="dvc-cover-overlay">
        <?= $__view->include('card.partials.hero-core', ['card' => $card, 'design' => $design ?? null]) ?>
    </div>
</header>
<div class="dvc-shell">
    <section class="dvc-hero dvc-section dvc-hero-underlay">
        <?= $__view->include('card.partials.actions', ['card' => $card, 'variant' => 'grid']) ?>
    </section>
</div>
