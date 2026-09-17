<?php
/** @var array<string,mixed> $card @var array<string,mixed> $record @var string $preview @var bool $canDownload @var string $publicUrl */
$__view->extend('layouts.panel');
$cardId = (int) $card['id'];
?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm btn-secondary" href="<?= e(url('cards/' . $cardId . '/editor')) ?>"><?= icon('arrow-left', 15) ?> Editor</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="grid grid-2" style="align-items:start">
    <div class="card">
        <div class="card-header"><h2>Your QR code</h2></div>
        <div class="card-body text-center">
            <?php if ($preview !== ''): ?>
                <img src="<?= e($preview) ?>" alt="QR code for <?= e((string) $card['title']) ?>" width="240" height="240"
                     style="margin-inline:auto;border-radius:14px;border:1px solid var(--border);padding:10px;background:#fff">
            <?php endif; ?>
            <p class="small muted mt-2" style="word-break:break-all"><?= e($publicUrl) ?></p>
            <p class="tiny muted">
                Scans are counted separately so you can tell printed-QR traffic from link traffic.
                <?php if ((int) $record['scan_count'] > 0): ?>
                    <br><strong><?= number_format((int) $record['scan_count']) ?></strong> scan(s) so far.
                <?php endif; ?>
            </p>

            <?php if ($canDownload): ?>
                <div class="row" style="justify-content:center;gap:8px;margin-top:14px">
                    <a class="btn" href="<?= e(url('cards/' . $cardId . '/qr/download?format=png&size=14')) ?>"><?= icon('download', 16) ?> PNG</a>
                    <a class="btn btn-secondary" href="<?= e(url('cards/' . $cardId . '/qr/download?format=svg')) ?>"><?= icon('download', 16) ?> SVG (print)</a>
                    <button class="btn btn-secondary" type="button" onclick="window.print()"><?= icon('file-text', 16) ?> Print</button>
                </div>
                <p class="tiny muted mt-2">SVG stays sharp at any size — use it for boards, flex banners and packaging.</p>
            <?php else: ?>
                <div class="alert alert-info mt-3" style="text-align:left">
                    <?= icon('award', 18) ?>
                    <div>QR download is available on paid plans. <a href="<?= e(url('billing')) ?>">See plans</a></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="stack">
        <form method="post" action="<?= e(url_path('cards/' . $cardId . '/qr')) ?>" class="card">
            <?= csrf_field() ?>
            <div class="card-header"><h3 style="font-size:.98rem">Style</h3></div>
            <div class="card-body">
                <div class="grid grid-2" style="gap:0 12px">
                    <div class="field">
                        <label for="foreground">Foreground</label>
                        <input class="input" type="color" id="foreground" name="foreground" value="<?= e((string) $record['foreground']) ?>" style="height:44px;padding:4px">
                    </div>
                    <div class="field">
                        <label for="background">Background</label>
                        <input class="input" type="color" id="background" name="background" value="<?= e((string) $record['background']) ?>" style="height:44px;padding:4px">
                    </div>
                </div>
                <div class="field">
                    <label for="ecc_level">Error correction</label>
                    <select id="ecc_level" name="ecc_level">
                        <?php foreach (['L' => 'Low (smallest code)', 'M' => 'Medium (recommended)', 'Q' => 'Quartile', 'H' => 'High (most robust)'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= (string) $record['ecc_level'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">Higher correction keeps the code readable even when part of it is damaged or covered by a logo.</div>
                </div>
            </div>
            <div class="card-footer text-right"><button class="btn" type="submit">Update QR style</button></div>
        </form>

        <div class="card">
            <div class="card-header"><h3 style="font-size:.98rem">Share your card</h3></div>
            <div class="card-body">
                <div class="copy-field mb-2">
                    <input class="input" type="text" id="share-url" value="<?= e($publicUrl) ?>" readonly>
                    <button class="btn btn-secondary" type="button" data-copy="#share-url" data-copy-message="Link copied"><?= icon('copy', 15) ?></button>
                </div>
                <div class="row" style="gap:8px;flex-wrap:wrap">
                    <a class="btn btn-sm btn-secondary" target="_blank" rel="noopener"
                       href="https://wa.me/?text=<?= rawurlencode($publicUrl) ?>"><?= icon('whatsapp', 15) ?> WhatsApp</a>
                    <a class="btn btn-sm btn-secondary" target="_blank" rel="noopener"
                       href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($publicUrl) ?>"><?= icon('facebook', 15) ?> Facebook</a>
                    <a class="btn btn-sm btn-secondary" target="_blank" rel="noopener"
                       href="https://t.me/share/url?url=<?= rawurlencode($publicUrl) ?>"><?= icon('telegram', 15) ?> Telegram</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__view->stop(); ?>
