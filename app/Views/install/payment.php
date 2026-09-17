<?php /** @var array<string,mixed> $settings */ $__view->extend('layouts.install'); ?>
<?php $__view->start('content'); ?>
    <p class="muted">Connect Razorpay so customers can buy plans. You can skip this now and configure it later from Admin → Settings.</p>

    <form method="post" action="<?= e(url('install/payment')) ?>">
        <?= csrf_field() ?>

        <label class="switch mb-3">
            <input type="checkbox" name="razorpay_enabled" value="1" <?= !empty($settings['razorpay_enabled']) ? 'checked' : '' ?>>
            <span class="track"></span>
            <span class="small bold">Enable Razorpay payments</span>
        </label>

        <div class="grid grid-2" style="gap:0 16px">
            <div class="field">
                <label for="razorpay_key_id">Key ID</label>
                <input class="input" type="text" id="razorpay_key_id" name="razorpay_key_id" value="<?= e($settings['razorpay_key_id'] ?? '') ?>" placeholder="rzp_live_xxxxxxxx" autocomplete="off">
            </div>
            <div class="field">
                <label for="razorpay_key_secret">Key secret</label>
                <input class="input" type="password" id="razorpay_key_secret" name="razorpay_key_secret" placeholder="Stored encrypted" autocomplete="off">
            </div>
        </div>
        <div class="field">
            <label for="razorpay_webhook_secret">Webhook secret</label>
            <input class="input" type="password" id="razorpay_webhook_secret" name="razorpay_webhook_secret" placeholder="Optional but recommended" autocomplete="off">
            <div class="hint">Webhook URL: <code><?= e(url('webhooks/razorpay')) ?></code></div>
        </div>

        <fieldset>
            <legend>GST / invoicing (India)</legend>
            <label class="switch mb-2">
                <input type="checkbox" name="gst_enabled" value="1" <?= !empty($settings['gst_enabled']) ? 'checked' : '' ?>>
                <span class="track"></span>
                <span class="small bold">Charge GST on plan purchases</span>
            </label>
            <div class="grid grid-3" style="gap:0 14px">
                <div class="field">
                    <label for="gst_rate">GST rate (%)</label>
                    <input class="input" type="number" id="gst_rate" name="gst_rate" step="0.01" min="0" max="28" value="<?= e($settings['gst_rate'] ?? 18) ?>">
                </div>
                <div class="field">
                    <label for="gst_number">GSTIN</label>
                    <input class="input" type="text" id="gst_number" name="gst_number" value="<?= e($settings['gst_number'] ?? '') ?>" maxlength="20">
                </div>
                <div class="field">
                    <label for="gst_state">State of supply</label>
                    <input class="input" type="text" id="gst_state" name="gst_state" value="<?= e($settings['gst_state'] ?? '') ?>" placeholder="Gujarat">
                </div>
            </div>
        </fieldset>

        <button class="btn btn-lg btn-block" type="submit">Save &amp; continue <?= icon('arrow-right', 16) ?></button>
    </form>
<?php $__view->stop(); ?>
