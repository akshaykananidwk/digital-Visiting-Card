<?php /** @var App\Services\CardPresenter $card @var string $title @var string $qrDataUri */ ?>
<section class="dvc-section">
    <div class="dvc-panel dvc-qr">
        <h2 class="dvc-section-title"><?= icon('qr', 15) ?> <?= e($title) ?></h2>
        <?php if (!empty($qrDataUri)): ?>
            <img src="<?= e($qrDataUri) ?>" alt="QR code for <?= e($card->displayName()) ?>" width="168" height="168" loading="lazy">
        <?php endif; ?>
        <p style="font-size:.82rem;color:var(--c-muted);margin:12px 0 0">Scan to open this card on any phone.</p>
        <a class="dvc-btn secondary" style="margin-top:12px" href="<?= e(url('card/' . $card->card()['slug'] . '/qr?format=png&download=1')) ?>">
            <?= icon('download', 16) ?> Download QR
        </a>
    </div>
</section>
