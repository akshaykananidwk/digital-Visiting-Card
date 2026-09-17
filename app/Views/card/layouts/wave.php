<?php
/**
 * Wave: a curved divider separates cover from content, and contact scrolls
 * as a single row along that curve.
 *
 * @var App\Services\CardPresenter $card @var App\Core\View $__view
 */
$cover = $card->image('cover_image');
?>
<header class="dvc-cover dvc-cover-wave" <?= $cover !== null ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>></header>
<div class="dvc-shell">
    <section class="dvc-hero dvc-section dvc-wave">
        <div class="dvc-hero-inner">
            <?= $__view->include('card.partials.hero-core', ['card' => $card]) ?>
        </div>
        <?= $__view->include('card.partials.actions', ['card' => $card, 'variant' => 'row']) ?>
    </section>
</div>
