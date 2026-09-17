<?php
/**
 * Minimal: no cover at all. Large type carries the card and contact is a
 * quiet stacked list.
 *
 * @var App\Services\CardPresenter $card @var App\Core\View $__view
 */
?>
<div class="dvc-shell">
    <section class="dvc-hero dvc-section" style="padding-top:34px">
        <div class="dvc-hero-inner">
            <?= $__view->include('card.partials.hero-core', ['card' => $card]) ?>
        </div>
        <?= $__view->include('card.partials.actions', ['card' => $card, 'variant' => 'list']) ?>
    </section>
</div>
