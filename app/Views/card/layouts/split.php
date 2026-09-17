<?php
/**
 * Split: the avatar sits beside the name rather than above it, so the
 * identity reads as one horizontal band, with contact as a scrolling row
 * underneath.
 *
 * @var App\Services\CardPresenter $card @var App\Core\View $__view
 */
$cover = $card->image('cover_image');
?>
<header class="dvc-cover" <?= $cover !== null ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>>
    <?php if ($design->motif() !== ''): ?>
        <span class="dvc-cover-motif" aria-hidden="true"><?= icon($design->motif(), 132) ?></span>
    <?php endif; ?>
</header>
<div class="dvc-shell">
    <section class="dvc-hero dvc-section">
        <div class="dvc-panel dvc-identity-beside">
            <?= $__view->include('card.partials.hero-core', ['card' => $card, 'design' => $design ?? null]) ?>
        </div>
        <?= $__view->include('card.partials.actions', ['card' => $card, 'variant' => 'row']) ?>
    </section>
</div>
