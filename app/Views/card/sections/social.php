<?php
/** @var App\Services\CardPresenter $card @var string $title */
use App\Models\CardSocialLink;

$iconFor = ['x' => 'x-social', 'google' => 'google', 'threads' => 'threads', 'snapchat' => 'snapchat'];
?>
<section class="dvc-section">
    <div class="dvc-panel">
        <h2 class="dvc-section-title"><?= icon('share', 15) ?> <?= e($title) ?></h2>
        <div class="dvc-social">
            <?php foreach ($card->social() as $platform => $link): ?>
                <?php $meta = CardSocialLink::PLATFORMS[$platform] ?? null; if ($meta === null) { continue; } ?>
                <a href="<?= e($link) ?>" target="_blank" rel="noopener me"
                   aria-label="<?= e($meta['label']) ?>" title="<?= e($meta['label']) ?>"
                   data-track="social_click" data-track-label="<?= e($platform) ?>">
                    <?= icon($iconFor[$platform] ?? $platform, 22) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
