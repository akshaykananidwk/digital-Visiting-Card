<?php /** @var App\Services\CardPresenter $card @var string $title */ ?>
<section class="dvc-section">
    <div class="dvc-panel">
        <h2 class="dvc-section-title"><?= icon('zap', 15) ?> <?= e($title) ?></h2>
        <div class="dvc-service-list">
            <?php foreach ($card->services() as $service): ?>
                <article class="dvc-service">
                    <span class="dvc-service-icon">
                        <?php if (!empty($service['image'])): ?>
                            <img src="<?= e((string) upload_url((string) $service['image'])) ?>" alt="" loading="lazy" width="44" height="44">
                        <?php elseif (!empty($service['icon']) && App\Core\Icon::exists((string) $service['icon'])): ?>
                            <?= icon((string) $service['icon'], 21) ?>
                        <?php else: ?>
                            <?= e(mb_strtoupper(mb_substr((string) $service['title'], 0, 1))) ?>
                        <?php endif; ?>
                    </span>
                    <div class="flex-1" style="min-width:0">
                        <h4><?= e((string) $service['title']) ?></h4>
                        <?php if (!empty($service['description'])): ?>
                            <p><?= e((string) $service['description']) ?></p>
                        <?php endif; ?>
                        <?php if ($service['price'] !== null && (float) $service['price'] > 0): ?>
                            <div class="dvc-service-price"><?= e((string) ($service['price_label'] ?: 'From')) ?> <?= e(money((float) $service['price'])) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($service['cta_link'])): ?>
                            <a class="dvc-btn ghost" style="min-height:36px;font-size:.78rem;margin-top:9px;padding:6px 14px"
                               href="<?= e((string) $service['cta_link']) ?>" target="_blank" rel="noopener"
                               data-track="service_click" data-track-label="<?= e((string) $service['title']) ?>">
                                <?= e((string) ($service['cta_label'] ?: 'Learn more')) ?>
                            </a>
                        <?php elseif ($card->whatsappLink() !== null): ?>
                            <a class="dvc-btn ghost" style="min-height:36px;font-size:.78rem;margin-top:9px;padding:6px 14px"
                               href="<?= e((string) $card->whatsappLink('Hello, I am interested in: ' . (string) $service['title'])) ?>"
                               target="_blank" rel="noopener"
                               data-track="service_click" data-track-label="<?= e((string) $service['title']) ?>">
                                <?= icon('whatsapp', 14) ?> Enquire
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
