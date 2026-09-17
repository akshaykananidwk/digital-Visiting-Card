<?php /** @var App\Services\CardPresenter $card @var string $title */ ?>
<section class="dvc-section">
    <div class="dvc-panel">
        <h2 class="dvc-section-title"><?= icon('package', 15) ?> <?= e($title) ?></h2>
        <div class="dvc-product-grid">
            <?php foreach ($card->products() as $product): ?>
                <?php
                $price = $product['price'] !== null ? (float) $product['price'] : null;
                $discount = $product['discount_price'] !== null ? (float) $product['discount_price'] : null;
                $hasDiscount = $discount !== null && $price !== null && $discount > 0 && $discount < $price;

                $ctaHref = null;
                $ctaLabel = 'Enquire';
                if ((string) $product['cta_type'] === 'whatsapp' && $card->whatsappLink() !== null) {
                    $ctaHref = $card->whatsappLink('Hello, I would like to know more about: ' . (string) $product['name']);
                    $ctaLabel = 'WhatsApp';
                } elseif ((string) $product['cta_type'] === 'call' && $card->telLink() !== null) {
                    $ctaHref = $card->telLink();
                    $ctaLabel = 'Call';
                } elseif ((string) $product['cta_type'] === 'link' && !empty($product['cta_link'])) {
                    $ctaHref = (string) $product['cta_link'];
                    $ctaLabel = 'View';
                }
                ?>
                <article class="dvc-product">
                    <div class="dvc-product-img">
                        <?php if (!empty($product['image'])): ?>
                            <img src="<?= e((string) upload_url((string) $product['image'])) ?>" alt="<?= e((string) $product['name']) ?>" loading="lazy" width="300" height="225">
                        <?php else: ?>
                            <div style="width:100%;height:100%;display:grid;place-items:center;color:var(--c-muted)"><?= icon('package', 28) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="dvc-product-body">
                        <div class="dvc-product-name"><?= e((string) $product['name']) ?></div>
                        <?php if (!empty($product['description'])): ?>
                            <p style="margin:0;font-size:.76rem;color:var(--c-muted)"><?= e(mb_substr((string) $product['description'], 0, 90)) ?></p>
                        <?php endif; ?>
                        <?php if ($price !== null && $price > 0): ?>
                            <div class="dvc-product-price">
                                <?= e(money($hasDiscount ? $discount : $price)) ?>
                                <?php if ($hasDiscount): ?><del><?= e(money($price)) ?></del><?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ((string) $product['stock_status'] === 'out_of_stock'): ?>
                            <span class="tiny" style="color:#b91c1c;font-weight:700">Out of stock</span>
                        <?php endif; ?>
                        <?php if ($ctaHref !== null): ?>
                            <a class="dvc-btn" href="<?= e($ctaHref) ?>" <?= str_starts_with($ctaHref, 'http') ? 'target="_blank" rel="noopener"' : '' ?>
                               data-track="product_click" data-track-label="<?= e((string) $product['name']) ?>">
                                <?= e($ctaLabel) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
