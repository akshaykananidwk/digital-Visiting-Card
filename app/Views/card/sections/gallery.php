<?php
/** @var App\Services\CardPresenter $card @var string $title */
use App\Services\CardPresenter;

$images = $card->galleryImages();
$videos = $card->galleryVideos();
?>
<section class="dvc-section">
    <div class="dvc-panel">
        <h2 class="dvc-section-title"><?= icon('image', 15) ?> <?= e($title) ?></h2>

        <?php if ($images !== []): ?>
            <div class="dvc-gallery">
                <?php foreach ($images as $item): ?>
                    <?php $src = (string) upload_url((string) $item['path']); ?>
                    <button type="button" data-gallery-item data-gallery-type="image"
                            data-gallery-src="<?= e($src) ?>" data-gallery-alt="<?= e((string) ($item['title'] ?? '')) ?>"
                            aria-label="<?= e((string) ($item['title'] ?: 'Open image')) ?>">
                        <img src="<?= e($src) ?>" alt="<?= e((string) ($item['title'] ?? '')) ?>" loading="lazy"
                             width="<?= (int) ($item['width'] ?: 300) ?>" height="<?= (int) ($item['height'] ?: 300) ?>">
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($videos !== []): ?>
            <div class="dvc-gallery" style="margin-top:<?= $images !== [] ? '10px' : '0' ?>">
                <?php foreach ($videos as $item): ?>
                    <?php
                    $isYoutube = (string) $item['type'] === 'youtube';
                    $src = $isYoutube ? (CardPresenter::youtubeEmbed((string) $item['path']) ?? '') : (string) upload_url((string) $item['path']);
                    $thumb = $item['thumbnail'] ? (string) upload_url((string) $item['thumbnail']) : ($isYoutube ? (CardPresenter::youtubeThumb((string) $item['path']) ?? '') : '');
                    if ($src === '') { continue; }
                    ?>
                    <button type="button" data-gallery-item data-gallery-type="<?= e((string) $item['type']) ?>"
                            data-gallery-src="<?= e($src) ?>" aria-label="Play video">
                        <?php if ($thumb !== ''): ?>
                            <img src="<?= e($thumb) ?>" alt="<?= e((string) ($item['title'] ?? 'Video')) ?>" loading="lazy" width="300" height="300">
                        <?php else: ?>
                            <span style="display:block;width:100%;height:100%;background:var(--c-surface-2)"></span>
                        <?php endif; ?>
                        <span class="play"><?= icon('play', 26) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
