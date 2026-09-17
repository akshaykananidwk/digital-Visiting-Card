<?php /** @var array<string,mixed> $settings */ $__view->extend('layouts.install'); ?>
<?php $__view->start('content'); ?>
    <p class="muted">Basic branding and support details. Everything here can be changed later in Admin → Settings.</p>

    <form method="post" action="<?= e(url_path('install/site')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label class="required" for="site_name">Website / brand name</label>
            <input class="input" type="text" id="site_name" name="site_name" value="<?= e(old('site_name', $settings['site_name'] ?? 'Digital Visiting Card')) ?>" required>
        </div>
        <div class="field">
            <label for="site_tagline">Tagline</label>
            <input class="input" type="text" id="site_tagline" name="site_tagline" value="<?= e(old('site_tagline', $settings['site_tagline'] ?? '')) ?>">
        </div>
        <div class="grid grid-2" style="gap:0 16px">
            <div class="field">
                <label for="support_email">Support email</label>
                <input class="input" type="email" id="support_email" name="support_email" value="<?= e(old('support_email', $settings['support_email'] ?? '')) ?>">
            </div>
            <div class="field">
                <label for="support_phone">Support phone</label>
                <input class="input" type="tel" id="support_phone" name="support_phone" value="<?= e(old('support_phone', $settings['support_phone'] ?? '')) ?>">
            </div>
        </div>
        <div class="grid grid-2" style="gap:0 16px">
            <div class="field">
                <label for="support_whatsapp">Support WhatsApp</label>
                <input class="input" type="tel" id="support_whatsapp" name="support_whatsapp" value="<?= e(old('support_whatsapp', $settings['support_whatsapp'] ?? '')) ?>">
            </div>
            <div class="field">
                <label for="currency_symbol">Currency symbol</label>
                <input class="input" type="text" id="currency_symbol" name="currency_symbol" value="<?= e(old('currency_symbol', $settings['currency_symbol'] ?? '₹')) ?>" maxlength="5">
            </div>
        </div>
        <button class="btn btn-lg btn-block" type="submit">Save &amp; continue <?= icon('arrow-right', 16) ?></button>
    </form>
<?php $__view->stop(); ?>
