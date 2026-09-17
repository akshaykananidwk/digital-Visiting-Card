<?php
/** Minimal: no cover, oversized typography, restrained actions. */
/** @var App\Services\CardPresenter $card @var App\Core\View $__view */
?>
<div class="dvc-shell">
    <section class="dvc-hero dvc-section" style="padding-top:34px">
        <div class="dvc-hero-inner">
            <?= $__view->include('card.partials.hero-core', ['card' => $card]) ?>
            <?= $__view->include('card.partials.actions', ['card' => $card]) ?>
        </div>
    </section>
</div>
