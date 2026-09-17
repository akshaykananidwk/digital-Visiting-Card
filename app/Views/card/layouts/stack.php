<?php
/** Stack: every block is its own rounded card. */
/** @var App\Services\CardPresenter $card @var App\Core\View $__view */
$cover = $card->image('cover_image');
?>
<header class="dvc-cover" <?= $cover !== null ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>></header>
<div class="dvc-shell">
    <section class="dvc-hero dvc-section">
        <div class="dvc-panel">
            <div class="dvc-hero-inner" style="margin-top:0">
                <?= $__view->include('card.partials.hero-core', ['card' => $card]) ?>
            </div>
        </div>
        <?= $__view->include('card.partials.actions', ['card' => $card]) ?>
    </section>
</div>
