<?php /** @var App\Services\CardPresenter $card @var string $title */ $upi = $card->upiLink(); ?>
<section class="dvc-section">
    <div class="dvc-panel dvc-payment">
        <h2 class="dvc-section-title"><?= icon('rupee', 15) ?> <?= e($title) ?></h2>
        <p class="dvc-upi"><?= e((string) $card->get('upi_id')) ?></p>
        <?php if ($card->has('payment_note')): ?>
            <p style="font-size:.84rem;color:var(--c-muted);margin:10px 0 0"><?= e((string) $card->get('payment_note')) ?></p>
        <?php endif; ?>
        <div style="display:grid;gap:9px;margin-top:14px">
            <?php if ($upi !== null): ?>
                <a class="dvc-btn" href="<?= e($upi) ?>" data-track="payment" data-track-label="upi_app"><?= icon('rupee', 17) ?> Pay with any UPI app</a>
            <?php endif; ?>
            <button class="dvc-btn secondary" type="button" data-copy-link="<?= e((string) $card->get('upi_id')) ?>" data-label="Copy UPI ID">Copy UPI ID</button>
        </div>
    </div>
</section>
