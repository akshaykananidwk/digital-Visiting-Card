<?php
/**
 * Primary calls to action.
 *
 * The same links in one of several arrangements, so a design can present
 * contact as a tile grid, a stacked list, a single scrolling row, a narrow
 * side rail or large blocks. The links themselves never change: whichever
 * arrangement a design picks, a visitor gets the same actions.
 *
 * @var App\Services\CardPresenter $card
 * @var string|null $variant  grid | list | row | rail | tiles
 */
$variant = in_array($variant ?? 'grid', ['grid', 'list', 'row', 'rail', 'tiles'], true) ? ($variant ?? 'grid') : 'grid';

$tel = $card->telLink();
$whatsapp = $card->whatsappLink();
$directions = $card->directionsLink();
$email = $card->emailLink();
$vcardUrl = url_path('card/' . $card->card()['slug'] . '/vcard');

/** @var array<int,array{key:string,class:string,href:?string,icon:string,label:string,external:bool}> $items */
$items = [];
if ($tel !== null) {
    $items[] = ['key' => 'call', 'class' => 'primary', 'href' => $tel, 'icon' => 'phone', 'label' => 'Call now', 'external' => false];
}
if ($whatsapp !== null) {
    $items[] = ['key' => 'whatsapp', 'class' => 'whatsapp', 'href' => $whatsapp, 'icon' => 'whatsapp', 'label' => 'WhatsApp', 'external' => true];
}
if ((bool) $card->setting('vcard_enabled', true)) {
    $items[] = ['key' => 'save_contact', 'class' => '', 'href' => $vcardUrl, 'icon' => 'user-plus', 'label' => 'Save contact', 'external' => false];
}
if ($directions !== null) {
    $items[] = ['key' => 'directions', 'class' => '', 'href' => $directions, 'icon' => 'navigation', 'label' => 'Directions', 'external' => true];
}
if ($email !== null) {
    $items[] = ['key' => 'email', 'class' => '', 'href' => $email, 'icon' => 'mail', 'label' => 'Email', 'external' => false];
}

$iconSize = $variant === 'list' || $variant === 'rail' ? 20 : ($variant === 'tiles' ? 28 : 23);
?>
<div class="dvc-actions dvc-actions-<?= e($variant) ?>">
    <?php foreach ($items as $item): ?>
        <a class="dvc-action <?= e($item['class']) ?>" href="<?= e((string) $item['href']) ?>"
           <?= $item['external'] ? 'target="_blank" rel="noopener"' : '' ?>
           data-track="<?= e($item['key']) ?>" data-track-label="action_<?= e($variant) ?>">
            <?= icon($item['icon'], $iconSize) ?><span><?= e($item['label']) ?></span>
        </a>
    <?php endforeach; ?>

    <button class="dvc-action" type="button" data-share
            data-share-url="<?= e($card->url()) ?>"
            data-share-title="<?= e($card->displayName()) ?>"
            data-share-text="<?= e($card->seoDescription()) ?>"
            data-track-label="action_<?= e($variant) ?>">
        <?= icon('share', $iconSize) ?><span>Share card</span>
    </button>
</div>
