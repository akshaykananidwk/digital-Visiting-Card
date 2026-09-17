<?php
/**
 * Rail: contact lives in a column beside the identity instead of below it.
 * On a phone the rail drops under the name, because a two-column card at
 * 360px would leave neither column usable.
 *
 * @var App\Services\CardPresenter $card @var App\Core\View $__view
 */
$cover = $card->image('cover_image');
?>
<header class="dvc-cover" <?= $cover !== null ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>></header>
<div class="dvc-shell">
    <section class="dvc-hero dvc-section dvc-rail-hero">
        <div class="dvc-rail-identity">
            <?= $__view->include('card.partials.hero-core', ['card' => $card]) ?>
        </div>
        <aside class="dvc-rail-contact" aria-label="Contact">
            <?= $__view->include('card.partials.actions', ['card' => $card, 'variant' => 'rail']) ?>
        </aside>
    </section>
</div>
