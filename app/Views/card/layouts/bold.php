<?php
/**
 * Bold: the identity sits in a solid colour slab and contact is four large
 * blocks, for trades and shopfronts that want the card readable at arm's
 * length.
 *
 * @var App\Services\CardPresenter $card @var App\Core\View $__view
 */
$cover = $card->image('cover_image');
?>
<header class="dvc-cover dvc-cover-short" <?= $cover !== null ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>>
    <?php if ($design->motif() !== ''): ?>
        <span class="dvc-cover-motif" aria-hidden="true"><?= icon($design->motif(), 132) ?></span>
    <?php endif; ?>
</header>
<div class="dvc-shell">
    <section class="dvc-hero dvc-section">
        <div class="dvc-slab">
            <?= $__view->include('card.partials.hero-core', ['card' => $card, 'design' => $design ?? null]) ?>
        </div>
        <?= $__view->include('card.partials.actions', ['card' => $card, 'variant' => 'tiles']) ?>
    </section>
</div>
