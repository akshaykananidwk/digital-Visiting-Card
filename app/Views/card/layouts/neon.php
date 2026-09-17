<?php
/**
 * Neon: dark surface, glowing edges, contact as full-width bars that read
 * like a control panel.
 *
 * @var App\Services\CardPresenter $card @var App\Core\View $__view
 */
$cover = $card->image('cover_image');
?>
<header class="dvc-cover" <?= $cover !== null ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>></header>
<div class="dvc-shell">
    <section class="dvc-hero dvc-section">
        <div class="dvc-hero-inner">
            <?= $__view->include('card.partials.hero-core', ['card' => $card]) ?>
        </div>
        <?= $__view->include('card.partials.actions', ['card' => $card, 'variant' => 'list']) ?>
    </section>
</div>
