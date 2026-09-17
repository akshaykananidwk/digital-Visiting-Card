<?php /** @var App\Services\CardPresenter $card @var string $title */ $embed = $card->mapEmbedUrl(); ?>
<section class="dvc-section">
    <div class="dvc-panel">
        <h2 class="dvc-section-title"><?= icon('map-pin', 15) ?> <?= e($title) ?></h2>
        <?php if ($card->fullAddress() !== ''): ?>
            <p style="margin:0 0 12px;font-size:.9rem;color:var(--c-muted)"><?= e($card->fullAddress()) ?></p>
        <?php endif; ?>
        <?php if ($embed !== null): ?>
            <div class="dvc-map">
                <iframe src="<?= e($embed) ?>" title="Location map" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            </div>
        <?php endif; ?>
        <?php if ($card->directionsLink() !== null): ?>
            <a class="dvc-btn block" style="margin-top:12px" href="<?= e((string) $card->directionsLink()) ?>" target="_blank" rel="noopener"
               data-track="directions" data-track-label="map_section">
                <?= icon('navigation', 17) ?> Get directions
            </a>
        <?php endif; ?>
    </div>
</section>
