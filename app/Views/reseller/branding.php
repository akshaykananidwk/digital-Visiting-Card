<?php /** @var array<string,mixed> $reseller @var string $verifyHost @var string $targetHost */
$__view->extend('layouts.panel'); ?>
<?php $__view->start('content'); ?>
<div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,380px);align-items:start">
    <form method="post" action="<?= e(url('reseller/branding')) ?>" enctype="multipart/form-data" class="card">
        <?= csrf_field() ?>
        <div class="card-header"><h2>Your brand</h2></div>
        <div class="card-body">
            <p class="small muted">These details replace the platform's branding for visitors who reach the site through your own domain.</p>

            <div class="field">
                <label class="required" for="brand_name">Brand name</label>
                <input class="input" type="text" id="brand_name" name="brand_name" value="<?= e((string) ($reseller['brand_name'] ?: $reseller['company_name'])) ?>" required>
            </div>
            <div class="field">
                <label for="tagline">Tagline</label>
                <input class="input" type="text" id="tagline" name="tagline" value="<?= e((string) ($reseller['tagline'] ?? '')) ?>" maxlength="190">
            </div>

            <div class="grid grid-2" style="gap:0 16px">
                <div class="field">
                    <label for="brand_logo">Logo</label>
                    <?php if (!empty($reseller['brand_logo'])): ?>
                        <img class="upload-preview mb-1" src="<?= e((string) upload_url((string) $reseller['brand_logo'])) ?>" alt="">
                    <?php endif; ?>
                    <input class="input" type="file" id="brand_logo" name="brand_logo" accept="image/png,image/jpeg,image/webp">
                </div>
                <div class="field">
                    <label for="brand_favicon">Favicon</label>
                    <?php if (!empty($reseller['brand_favicon'])): ?>
                        <img class="upload-preview mb-1" src="<?= e((string) upload_url((string) $reseller['brand_favicon'])) ?>" alt="">
                    <?php endif; ?>
                    <input class="input" type="file" id="brand_favicon" name="brand_favicon" accept="image/png,image/webp">
                </div>
            </div>

            <div class="grid grid-3" style="gap:0 12px">
                <div class="field"><label for="support_email">Support email</label><input class="input" type="email" id="support_email" name="support_email" value="<?= e((string) ($reseller['support_email'] ?? '')) ?>"></div>
                <div class="field"><label for="support_phone">Support phone</label><input class="input" type="tel" id="support_phone" name="support_phone" value="<?= e((string) ($reseller['support_phone'] ?? '')) ?>"></div>
                <div class="field"><label for="support_whatsapp">Support WhatsApp</label><input class="input" type="tel" id="support_whatsapp" name="support_whatsapp" value="<?= e((string) ($reseller['support_whatsapp'] ?? '')) ?>"></div>
            </div>
        </div>
        <div class="card-footer text-right"><button class="btn" type="submit"><?= icon('save', 16) ?> Save branding</button></div>
    </form>

    <div class="stack">
        <form method="post" action="<?= e(url('reseller/branding/domain')) ?>" class="card">
            <?= csrf_field() ?>
            <div class="card-header">
                <h3 style="font-size:.98rem">Custom domain</h3>
                <?php
                $status = (string) ($reseller['domain_status'] ?? 'none');
                $badge = match ($status) { 'verified' => 'badge-success', 'pending' => 'badge-warning', 'failed' => 'badge-danger', default => '' };
                ?>
                <span class="badge <?= $badge ?>"><?= e($status) ?></span>
            </div>
            <div class="card-body">
                <div class="field">
                    <label for="domain">Your domain</label>
                    <input class="input" type="text" id="domain" name="domain" value="<?= e((string) ($reseller['domain'] ?? '')) ?>" placeholder="cards.yourbusiness.com">
                    <div class="hint">Leave empty to remove the custom domain.</div>
                </div>
                <button class="btn btn-sm btn-block" type="submit">Save domain</button>
            </div>
        </form>

        <?php if (!empty($reseller['domain']) && !empty($reseller['domain_token'])): ?>
            <div class="card">
                <div class="card-header"><h3 style="font-size:.98rem">DNS setup</h3></div>
                <div class="card-body">
                    <p class="small muted">Add these two records at your domain registrar, then verify.</p>

                    <div class="label">1. Point the domain at this platform</div>
                    <pre class="small">Type:  CNAME
Host:  <?= e(explode('.', (string) $reseller['domain'])[0]) ?>

Value: <?= e($targetHost) ?></pre>

                    <div class="label mt-2">2. Prove you own it</div>
                    <pre class="small">Type:  TXT
Host:  _dvc-verify
Value: <?= e((string) $reseller['domain_token']) ?></pre>

                    <form method="post" action="<?= e(url('reseller/branding/domain/verify')) ?>" class="mt-2">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-block" type="submit"><?= icon('shield', 14) ?> Verify domain</button>
                    </form>

                    <p class="tiny muted mt-2">
                        After verification, install an SSL certificate for the domain on your server
                        (most control panels offer free Let's Encrypt certificates).
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <div class="card"><div class="card-body small">
            <div class="row-between" style="padding:4px 0"><span class="muted">Reseller code</span><strong><?= e((string) $reseller['code']) ?></strong></div>
            <div class="row-between" style="padding:4px 0"><span class="muted">Commission</span><strong><?= e((string) $reseller['commission_rate']) ?>%</strong></div>
            <div class="row-between" style="padding:4px 0"><span class="muted">Customers</span><strong><?= number_format((int) $reseller['total_customers']) ?></strong></div>
        </div></div>
    </div>
</div>
<?php $__view->stop(); ?>
