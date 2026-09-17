<?php
/**
 * Glass: a translucent panel floats over the cover, contact in two columns
 * inside the panel.
 *
 * @var App\Services\CardPresenter $card @var App\Core\View $__view
 */
$cover = $card->image('cover_image');
?>
<header class="dvc-cover dvc-cover-tall" <?= $cover !== null ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>></header>
<div class="dvc-shell">
    <section class="dvc-hero dvc-section">
        <div class="dvc-panel dvc-glass-panel">
            <div class="dvc-hero-inner" style="margin-top:0">
                <?= $__view->include('card.partials.hero-core', ['card' => $card]) ?>
            </div>
            <?= $__view->include('card.partials.actions', ['card' => $card, 'variant' => 'tiles']) ?>
        </div>
    </section>
</div>
