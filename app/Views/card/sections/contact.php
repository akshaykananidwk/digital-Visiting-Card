<?php
/** @var App\Services\CardPresenter $card @var string $title */
$rows = [];

if ($card->telLink() !== null) {
    $rows[] = ['icon' => 'phone', 'label' => 'Phone', 'value' => (string) $card->get('phone'), 'href' => $card->telLink(), 'track' => 'call'];
}
if ($card->has('phone_alt')) {
    $rows[] = ['icon' => 'phone', 'label' => 'Alternate phone', 'value' => (string) $card->get('phone_alt'), 'href' => $card->telLink((string) $card->get('phone_alt')), 'track' => 'call'];
}
if ($card->whatsappLink() !== null) {
    $rows[] = ['icon' => 'whatsapp', 'label' => 'WhatsApp', 'value' => (string) ($card->get('whatsapp') ?? $card->get('phone')), 'href' => $card->whatsappLink(), 'track' => 'whatsapp', 'blank' => true];
}
if ($card->emailLink() !== null) {
    $rows[] = ['icon' => 'mail', 'label' => 'Email', 'value' => (string) $card->get('email'), 'href' => $card->emailLink(), 'track' => 'email'];
}
if ($card->websiteLink() !== null) {
    $rows[] = ['icon' => 'globe', 'label' => 'Website', 'value' => $card->websiteLabel(), 'href' => $card->websiteLink(), 'track' => 'website', 'blank' => true];
}
if ($card->fullAddress() !== '') {
    $rows[] = ['icon' => 'map-pin', 'label' => 'Address', 'value' => $card->fullAddress(), 'href' => $card->directionsLink(), 'track' => 'directions', 'blank' => true];
}
if ($rows === []) {
    return;
}
?>
<section class="dvc-section">
    <div class="dvc-panel">
        <h2 class="dvc-section-title"><?= icon('phone', 15) ?> <?= e($title) ?></h2>
        <div class="dvc-contact-list">
            <?php foreach ($rows as $row): ?>
                <?php if ($row['href'] !== null): ?>
                    <a class="dvc-contact-row" href="<?= e((string) $row['href']) ?>"
                       <?= !empty($row['blank']) ? 'target="_blank" rel="noopener"' : '' ?>
                       data-track="<?= e($row['track']) ?>" data-track-label="contact_list">
                <?php else: ?>
                    <div class="dvc-contact-row">
                <?php endif; ?>
                    <span class="dvc-contact-icon"><?= icon($row['icon'], 19) ?></span>
                    <span class="flex-1" style="min-width:0">
                        <span class="dvc-contact-label" style="display:block"><?= e($row['label']) ?></span>
                        <span class="dvc-contact-value"><?= e($row['value']) ?></span>
                    </span>
                <?php if ($row['href'] !== null): ?></a><?php else: ?></div><?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
