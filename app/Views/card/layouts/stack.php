<?php
/**
 * Stack: identity and contact are separate cards with a gap between them,
 * so the card reads as a small stack rather than one panel.
 *
 * @var App\Services\CardPresenter $card @var App\Core\View $__view
 */
$cover = $card->image('cover_image');
?>
<header class="dvc-cover dvc-cover-short" <?= $cover !== null ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>>
    <?php if ($design->motif() !== ''): ?>
        <span class="dvc-cover-motif" aria-hidden="true"><?= icon($design->motif(), 132) ?></span>
    <?php endif; ?>
</header>
<div class="dvc-shell">
    <section class="dvc-hero dvc-section dvc-stack">
        <div class="dvc-panel">
            <div class="dvc-hero-inner" style="margin-top:0">
                <?= $__view->include('card.partials.hero-core', ['card' => $card, 'design' => $design ?? null]) ?>
            </div>
        </div>
        <div class="dvc-panel dvc-stack-contact">
            <?= $__view->include('card.partials.actions', ['card' => $card, 'variant' => 'grid']) ?>
        </div>
    </section>
</div>
