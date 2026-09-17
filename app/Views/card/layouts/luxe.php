<?php
/**
 * Luxe: serif type, hairline rules and a restrained stacked contact list.
 *
 * @var App\Services\CardPresenter $card @var App\Core\View $__view
 */
$cover = $card->image('cover_image');
?>
<header class="dvc-cover dvc-cover-short" <?= $cover !== null ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>></header>
<div class="dvc-shell">
    <section class="dvc-hero dvc-section dvc-luxe">
        <div class="dvc-panel dvc-luxe-panel">
            <div class="dvc-hero-inner" style="margin-top:0">
                <?= $__view->include('card.partials.hero-core', ['card' => $card]) ?>
            </div>
            <?= $__view->include('card.partials.actions', ['card' => $card, 'variant' => 'list']) ?>
        </div>
    </section>
</div>
