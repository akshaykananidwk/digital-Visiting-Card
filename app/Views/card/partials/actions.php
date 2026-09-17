<?php
/**
 * Primary thumb-friendly call-to-action grid.
 *
 * @var App\Services\CardPresenter $card
 */
$tel = $card->telLink();
$whatsapp = $card->whatsappLink();
$directions = $card->directionsLink();
$email = $card->emailLink();
$vcardUrl = url('card/' . $card->card()['slug'] . '/vcard');
?>
<div class="dvc-actions">
    <?php if ($tel !== null): ?>
        <a class="dvc-action primary" href="<?= e($tel) ?>" data-track="call" data-track-label="action_grid">
            <?= icon('phone', 23) ?><span>Call now</span>
        </a>
    <?php endif; ?>

    <?php if ($whatsapp !== null): ?>
        <a class="dvc-action whatsapp" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener" data-track="whatsapp" data-track-label="action_grid">
            <?= icon('whatsapp', 23) ?><span>WhatsApp</span>
        </a>
    <?php endif; ?>

    <?php if ((bool) $card->setting('vcard_enabled', true)): ?>
        <a class="dvc-action" href="<?= e($vcardUrl) ?>" data-track="save_contact" data-track-label="action_grid">
            <?= icon('user-plus', 23) ?><span>Save contact</span>
        </a>
    <?php endif; ?>

    <?php if ($directions !== null): ?>
        <a class="dvc-action" href="<?= e($directions) ?>" target="_blank" rel="noopener" data-track="directions" data-track-label="action_grid">
            <?= icon('navigation', 23) ?><span>Directions</span>
        </a>
    <?php endif; ?>

    <?php if ($email !== null): ?>
        <a class="dvc-action" href="<?= e($email) ?>" data-track="email" data-track-label="action_grid">
            <?= icon('mail', 23) ?><span>Email</span>
        </a>
    <?php endif; ?>

    <button class="dvc-action" type="button" data-share
            data-share-url="<?= e($card->url()) ?>"
            data-share-title="<?= e($card->displayName()) ?>"
            data-share-text="<?= e($card->seoDescription()) ?>">
        <?= icon('share', 23) ?><span>Share card</span>
    </button>
</div>
