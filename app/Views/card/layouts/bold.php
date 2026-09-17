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
<header class="dvc-cover dvc-cover-short" <?= $cover !== null ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>></header>
<div class="dvc-shell">
    <section class="dvc-hero dvc-section">
        <div class="dvc-slab">
            <?= $__view->include('card.partials.hero-core', ['card' => $card]) ?>
        </div>
        <?= $__view->include('card.partials.actions', ['card' => $card, 'variant' => 'tiles']) ?>
    </section>
</div>
