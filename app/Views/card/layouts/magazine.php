<?php
/**
 * Magazine: editorial framing. The name is set as display type over a rule,
 * and contact is a read-down list rather than a grid of tiles.
 *
 * @var App\Services\CardPresenter $card @var App\Core\View $__view
 */
$cover = $card->image('cover_image');
?>
<header class="dvc-cover dvc-cover-tall" <?= $cover !== null ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>>
    <?php if ($design->motif() !== ''): ?>
        <span class="dvc-cover-motif" aria-hidden="true"><?= icon($design->motif(), 132) ?></span>
    <?php endif; ?>
</header>
<div class="dvc-shell">
    <section class="dvc-hero dvc-section dvc-magazine">
        <div class="dvc-hero-inner">
            <?= $__view->include('card.partials.hero-core', ['card' => $card, 'design' => $design ?? null]) ?>
        </div>
        <div class="dvc-magazine-rule" aria-hidden="true"></div>
        <?= $__view->include('card.partials.actions', ['card' => $card, 'variant' => 'list']) ?>
    </section>
</div>
