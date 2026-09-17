<?php
/**
 * Fixed bottom action bar — always within thumb reach on mobile.
 *
 * @var App\Services\CardPresenter $card
 */
$tel = $card->telLink();
$whatsapp = $card->whatsappLink();
$directions = $card->directionsLink();
if ($tel === null && $whatsapp === null) {
    return;
}
?>
<div class="dvc-sticky">
    <?php if ($tel !== null): ?>
        <a class="dvc-btn" href="<?= e($tel) ?>" data-track="call" data-track-label="sticky"><?= icon('phone', 17) ?> Call</a>
    <?php endif; ?>
    <?php if ($whatsapp !== null): ?>
        <a class="dvc-btn whatsapp" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener" data-track="whatsapp" data-track-label="sticky"><?= icon('whatsapp', 17) ?> WhatsApp</a>
    <?php endif; ?>
    <?php if ($directions !== null): ?>
        <a class="dvc-btn secondary" href="<?= e($directions) ?>" target="_blank" rel="noopener" data-track="directions" data-track-label="sticky"><?= icon('navigation', 17) ?> Map</a>
    <?php endif; ?>
    <a class="dvc-btn secondary" href="<?= e(url('card/' . $card->card()['slug'] . '/vcard')) ?>" data-track="save_contact" data-track-label="sticky"><?= icon('user-plus', 17) ?> Save</a>
</div>
