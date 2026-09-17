<?php
/** Glassmorphism: frosted panel floating over a blurred cover. */
/** @var App\Services\CardPresenter $card @var App\Core\View $__view */
$cover = $card->image('cover_image');
?>
<header class="dvc-cover" <?= $cover !== null ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>></header>
<div class="dvc-shell">
    <section class="dvc-hero dvc-section">
        <div class="dvc-hero-inner">
            <div class="dvc-panel">
                <?= $__view->include('card.partials.hero-core', ['card' => $card]) ?>
            </div>
            <?= $__view->include('card.partials.actions', ['card' => $card]) ?>
        </div>
    </section>
</div>
