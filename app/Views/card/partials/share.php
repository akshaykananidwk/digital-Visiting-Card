<?php
/**
 * Share bottom-sheet used when the Web Share API is unavailable.
 *
 * @var App\Services\CardPresenter $card
 */
$shareUrl = $card->url();
$encoded = rawurlencode($shareUrl);
$text = rawurlencode($card->displayName() . ' — digital visiting card');
?>
<div class="dvc-share-backdrop" data-share-backdrop data-share-close></div>
<div class="dvc-share-sheet" data-share-sheet role="dialog" aria-label="Share this card">
    <div style="display:flex;align-items:center;justify-content:space-between">
        <strong>Share this card</strong>
        <button type="button" data-share-close style="background:none;border:0;color:inherit;padding:6px"><?= icon('x', 20) ?></button>
    </div>

    <div class="dvc-share-grid">
        <a href="https://wa.me/?text=<?= $text ?>%20<?= $encoded ?>" target="_blank" rel="noopener" data-track="share" data-track-label="whatsapp">
            <?= icon('whatsapp', 22) ?><span>WhatsApp</span>
        </a>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $encoded ?>" target="_blank" rel="noopener" data-track="share" data-track-label="facebook">
            <?= icon('facebook', 22) ?><span>Facebook</span>
        </a>
        <a href="https://t.me/share/url?url=<?= $encoded ?>&text=<?= $text ?>" target="_blank" rel="noopener" data-track="share" data-track-label="telegram">
            <?= icon('telegram', 22) ?><span>Telegram</span>
        </a>
        <a href="https://twitter.com/intent/tweet?url=<?= $encoded ?>&text=<?= $text ?>" target="_blank" rel="noopener" data-track="share" data-track-label="x">
            <?= icon('x-social', 22) ?><span>X</span>
        </a>
        <a href="mailto:?subject=<?= $text ?>&body=<?= $encoded ?>" data-track="share" data-track-label="email">
            <?= icon('mail', 22) ?><span>Email</span>
        </a>
        <button type="button" data-copy-link="<?= e($shareUrl) ?>" data-label="Copy link">
            <?= icon('copy', 22) ?><span>Copy link</span>
        </button>
    </div>

    <p class="tiny" style="margin:14px 0 0;opacity:.7;word-break:break-all"><?= e($shareUrl) ?></p>
</div>
