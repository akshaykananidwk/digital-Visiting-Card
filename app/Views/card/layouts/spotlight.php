<?php
/** Spotlight: oversized portrait lit by a radial glow. */
/** @var App\Services\CardPresenter $card @var App\Core\View $__view */
$cover = $card->image('cover_image');
?>
<header class="dvc-cover" <?= $cover !== null ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>></header>
<div class="dvc-shell">
    <section class="dvc-hero dvc-section">
        <div class="dvc-hero-inner">
            <?= $__view->include('card.partials.hero-core', ['card' => $card]) ?>
            <?= $__view->include('card.partials.actions', ['card' => $card]) ?>
        </div>
    </section>
</div>
