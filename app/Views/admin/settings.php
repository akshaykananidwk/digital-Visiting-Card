<?php
/** @var string $tab @var array<int,string> $groups @var array<string,mixed> $settings
 *  @var App\Services\RazorpayService $gateway */
$__view->extend('layouts.admin');
$get = static fn (string $key, mixed $default = '') => $settings[$key] ?? $default;
$labels = ['general' => 'General', 'cards' => 'Cards', 'payment' => 'Payments & GST', 'mail' => 'Email', 'seo' => 'SEO', 'uploads' => 'Uploads', 'legal' => 'Legal pages'];
?>
<?php $__view->start('content'); ?>
<div class="tabs">
    <?php foreach ($groups as $group): ?>
        <a href="<?= e(url('admin/settings?tab=' . $group)) ?>" class="<?= $tab === $group ? 'active' : '' ?>"><?= e($labels[$group] ?? ucfirst($group)) ?></a>
    <?php endforeach; ?>
</div>

<form method="post" action="<?= e(url('admin/settings/' . $tab)) ?>" enctype="multipart/form-data" class="card" style="max-width:840px">
    <?= csrf_field() ?>
    <div class="card-body">
        <?php if ($tab === 'general'): ?>
            <div class="grid grid-2" style="gap:0 16px">
                <div class="field"><label for="site_name">Site name</label><input class="input" type="text" id="site_name" name="site_name" value="<?= e((string) $get('site_name')) ?>"></div>
                <div class="field"><label for="site_tagline">Tagline</label><input class="input" type="text" id="site_tagline" name="site_tagline" value="<?= e((string) $get('site_tagline')) ?>"></div>
            </div>
            <div class="field"><label for="site_description">Description</label><textarea class="textarea" id="site_description" name="site_description" rows="2"><?= e((string) $get('site_description')) ?></textarea></div>
            <div class="grid grid-3" style="gap:0 12px">
                <div class="field"><label for="support_email">Support email</label><input class="input" type="email" id="support_email" name="support_email" value="<?= e((string) $get('support_email')) ?>"></div>
                <div class="field"><label for="support_phone">Support phone</label><input class="input" type="tel" id="support_phone" name="support_phone" value="<?= e((string) $get('support_phone')) ?>"></div>
                <div class="field"><label for="support_whatsapp">Support WhatsApp</label><input class="input" type="tel" id="support_whatsapp" name="support_whatsapp" value="<?= e((string) $get('support_whatsapp')) ?>"></div>
            </div>
            <div class="grid grid-3" style="gap:0 12px">
                <div class="field"><label for="currency_code">Currency code</label><input class="input" type="text" id="currency_code" name="currency_code" value="<?= e((string) $get('currency_code', 'INR')) ?>" maxlength="3"></div>
                <div class="field"><label for="currency_symbol">Currency symbol</label><input class="input" type="text" id="currency_symbol" name="currency_symbol" value="<?= e((string) $get('currency_symbol', '₹')) ?>" maxlength="5"></div>
                <div class="field"><label for="default_country">Default country</label><input class="input" type="text" id="default_country" name="default_country" value="<?= e((string) $get('default_country', 'India')) ?>"></div>
            </div>
            <div class="grid grid-2" style="gap:0 16px">
                <div class="field">
                    <label for="site_logo">Logo</label>
                    <?php if ($get('site_logo')): ?><img class="upload-preview mb-1" src="<?= e((string) upload_url((string) $get('site_logo'))) ?>" alt=""><?php endif; ?>
                    <input class="input" type="file" id="site_logo" name="site_logo" accept="image/png,image/jpeg,image/webp">
                </div>
                <div class="field">
                    <label for="site_favicon">Favicon</label>
                    <?php if ($get('site_favicon')): ?><img class="upload-preview mb-1" src="<?= e((string) upload_url((string) $get('site_favicon'))) ?>" alt=""><?php endif; ?>
                    <input class="input" type="file" id="site_favicon" name="site_favicon" accept="image/png,image/webp">
                </div>
            </div>
            <label class="switch mb-2"><input type="checkbox" name="allow_registration" value="1" <?= $get('allow_registration', true) ? 'checked' : '' ?>><span class="track"></span><span class="small bold">Allow public registration</span></label>

        <?php elseif ($tab === 'cards'): ?>
            <div class="field">
                <label for="card_url_mode">Card URL style</label>
                <select id="card_url_mode" name="card_url_mode">
                    <option value="both" <?= (string) $get('card_url_mode') === 'both' ? 'selected' : '' ?>>Both /card/name and /name</option>
                    <option value="card_only" <?= (string) $get('card_url_mode') === 'card_only' ? 'selected' : '' ?>>Only /card/name</option>
                </select>
            </div>
            <div class="grid grid-2" style="gap:0 16px">
                <div class="field">
                    <label for="expired_card_behaviour">When a subscription expires</label>
                    <select id="expired_card_behaviour" name="expired_card_behaviour">
                        <option value="expired_page" <?= (string) $get('expired_card_behaviour') === 'expired_page' ? 'selected' : '' ?>>Show an "expired" page with a renewal prompt</option>
                        <option value="disable" <?= (string) $get('expired_card_behaviour') === 'disable' ? 'selected' : '' ?>>Show a 404 (card disappears)</option>
                        <option value="keep_live" <?= (string) $get('expired_card_behaviour') === 'keep_live' ? 'selected' : '' ?>>Keep the card live</option>
                    </select>
                </div>
                <div class="field">
                    <label for="subscription_grace_days">Grace period (days)</label>
                    <input class="input" type="number" id="subscription_grace_days" name="subscription_grace_days" min="0" max="90" value="<?= (int) $get('subscription_grace_days', 0) ?>">
                    <div class="hint">Extra days after expiry before the card is taken offline.</div>
                </div>
            </div>
            <div class="field">
                <label for="default_whatsapp_message">Default WhatsApp message</label>
                <input class="input" type="text" id="default_whatsapp_message" name="default_whatsapp_message" value="<?= e((string) $get('default_whatsapp_message')) ?>" maxlength="255">
            </div>
            <label class="switch mb-2"><input type="checkbox" name="show_platform_branding" value="1" <?= $get('show_platform_branding', true) ? 'checked' : '' ?>><span class="track"></span><span class="small bold">Show "Powered by" on cards (unless the plan removes it)</span></label>
            <label class="switch"><input type="checkbox" name="reduced_motion_default" value="1" <?= $get('reduced_motion_default') ? 'checked' : '' ?>><span class="track"></span><span class="small bold">Start every card with animations disabled</span></label>

        <?php elseif ($tab === 'payment'): ?>
            <div class="alert alert-info">
                <?= icon('info', 18) ?>
                <div>Webhook URL: <code><?= e(url('webhooks/razorpay')) ?></code><br>
                    Add it in the Razorpay dashboard for <code>payment.captured</code>, <code>payment.failed</code> and <code>refund.processed</code>.</div>
            </div>
            <label class="switch mb-3"><input type="checkbox" name="razorpay_enabled" value="1" <?= $get('razorpay_enabled') ? 'checked' : '' ?>><span class="track"></span><span class="small bold">Enable Razorpay payments</span></label>
            <div class="grid grid-2" style="gap:0 16px">
                <div class="field"><label for="razorpay_key_id">Key ID</label><input class="input" type="text" id="razorpay_key_id" name="razorpay_key_id" value="<?= e((string) $get('razorpay_key_id')) ?>" autocomplete="off"></div>
                <div class="field">
                    <label for="razorpay_key_secret">Key secret</label>
                    <input class="input" type="password" id="razorpay_key_secret" name="razorpay_key_secret" placeholder="<?= $get('razorpay_key_secret') ? 'Stored — leave blank to keep' : 'Not set' ?>" autocomplete="off">
                </div>
            </div>
            <div class="field">
                <label for="razorpay_webhook_secret">Webhook secret</label>
                <input class="input" type="password" id="razorpay_webhook_secret" name="razorpay_webhook_secret" placeholder="<?= $get('razorpay_webhook_secret') ? 'Stored — leave blank to keep' : 'Not set' ?>" autocomplete="off">
            </div>
            <div class="row mb-3" style="gap:10px;align-items:center">
                <button class="btn btn-secondary btn-sm" type="button" id="test-razorpay" data-no-lock>Test connection</button>
                <span class="small" id="razorpay-result"><?= $gateway->isConfigured() ? ($gateway->isTestMode() ? 'Currently in TEST mode' : 'Currently in LIVE mode') : 'Not configured' ?></span>
            </div>

            <fieldset>
                <legend>GST / invoicing</legend>
                <label class="switch mb-2"><input type="checkbox" name="gst_enabled" value="1" <?= $get('gst_enabled') ? 'checked' : '' ?>><span class="track"></span><span class="small bold">Charge GST</span></label>
                <label class="switch mb-2"><input type="checkbox" name="gst_inclusive" value="1" <?= $get('gst_inclusive') ? 'checked' : '' ?>><span class="track"></span><span class="small bold">Plan prices already include GST</span></label>
                <div class="grid grid-4" style="gap:0 12px">
                    <div class="field"><label class="tiny" for="gst_rate">Rate (%)</label><input class="input" type="number" id="gst_rate" name="gst_rate" step="0.01" min="0" max="28" value="<?= e((string) $get('gst_rate', 18)) ?>"></div>
                    <div class="field"><label class="tiny" for="gst_number">GSTIN</label><input class="input" type="text" id="gst_number" name="gst_number" value="<?= e((string) $get('gst_number')) ?>" maxlength="20"></div>
                    <div class="field"><label class="tiny" for="gst_state">State</label><input class="input" type="text" id="gst_state" name="gst_state" value="<?= e((string) $get('gst_state')) ?>"></div>
                    <div class="field"><label class="tiny" for="gst_hsn">HSN/SAC</label><input class="input" type="text" id="gst_hsn" name="gst_hsn" value="<?= e((string) $get('gst_hsn', '998314')) ?>"></div>
                </div>
                <div class="grid grid-2" style="gap:0 16px">
                    <div class="field"><label for="invoice_prefix">Invoice prefix</label><input class="input" type="text" id="invoice_prefix" name="invoice_prefix" value="<?= e((string) $get('invoice_prefix', 'INV')) ?>" maxlength="10"></div>
                    <div class="field"><label for="company_legal_name">Legal company name</label><input class="input" type="text" id="company_legal_name" name="company_legal_name" value="<?= e((string) $get('company_legal_name')) ?>"></div>
                </div>
                <div class="field"><label for="company_address">Company address</label><textarea class="textarea" id="company_address" name="company_address" rows="2"><?= e((string) $get('company_address')) ?></textarea></div>
            </fieldset>

        <?php elseif ($tab === 'mail'): ?>
            <div class="grid grid-2" style="gap:0 16px">
                <div class="field"><label for="smtp_host">SMTP host</label><input class="input" type="text" id="smtp_host" name="smtp_host" value="<?= e((string) $get('smtp_host')) ?>" placeholder="smtp.hostinger.com"></div>
                <div class="field"><label for="smtp_port">Port</label><input class="input" type="number" id="smtp_port" name="smtp_port" value="<?= (int) $get('smtp_port', 587) ?>"></div>
            </div>
            <div class="grid grid-2" style="gap:0 16px">
                <div class="field"><label for="smtp_username">Username</label><input class="input" type="text" id="smtp_username" name="smtp_username" value="<?= e((string) $get('smtp_username')) ?>" autocomplete="off"></div>
                <div class="field">
                    <label for="smtp_password">Password</label>
                    <input class="input" type="password" id="smtp_password" name="smtp_password" placeholder="<?= $get('smtp_password') ? 'Stored — leave blank to keep' : 'Not set' ?>" autocomplete="off">
                </div>
            </div>
            <div class="grid grid-3" style="gap:0 12px">
                <div class="field">
                    <label for="smtp_encryption">Encryption</label>
                    <select id="smtp_encryption" name="smtp_encryption">
                        <?php foreach (['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None (25)'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= (string) $get('smtp_encryption', 'tls') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label for="mail_from_address">From address</label><input class="input" type="email" id="mail_from_address" name="mail_from_address" value="<?= e((string) $get('mail_from_address')) ?>"></div>
                <div class="field"><label for="mail_from_name">From name</label><input class="input" type="text" id="mail_from_name" name="mail_from_name" value="<?= e((string) $get('mail_from_name')) ?>"></div>
            </div>
            <div class="row mb-3" style="gap:10px;align-items:center">
                <button class="btn btn-secondary btn-sm" type="button" id="test-smtp" data-no-lock>Send a test email</button>
                <span class="small" id="smtp-result"></span>
            </div>
            <label class="switch mb-2"><input type="checkbox" name="notify_admin_on_signup" value="1" <?= $get('notify_admin_on_signup', true) ? 'checked' : '' ?>><span class="track"></span><span class="small bold">Email the admin on every new signup</span></label>
            <label class="switch"><input type="checkbox" name="notify_user_on_lead" value="1" <?= $get('notify_user_on_lead', true) ? 'checked' : '' ?>><span class="track"></span><span class="small bold">Email the card owner on every new lead</span></label>

        <?php elseif ($tab === 'seo'): ?>
            <div class="field"><label for="meta_title">Home page title</label><input class="input" type="text" id="meta_title" name="meta_title" value="<?= e((string) $get('meta_title')) ?>" maxlength="190"></div>
            <div class="field"><label for="meta_description">Meta description</label><textarea class="textarea" id="meta_description" name="meta_description" rows="2" maxlength="320"><?= e((string) $get('meta_description')) ?></textarea></div>
            <div class="field"><label for="meta_keywords">Keywords</label><input class="input" type="text" id="meta_keywords" name="meta_keywords" value="<?= e((string) $get('meta_keywords')) ?>"></div>
            <div class="field"><label for="google_verification">Google site verification</label><input class="input" type="text" id="google_verification" name="google_verification" value="<?= e((string) $get('google_verification')) ?>"></div>
            <div class="field">
                <label for="og_image">Social share image</label>
                <?php if ($get('og_image')): ?><img class="upload-preview mb-1" src="<?= e((string) upload_url((string) $get('og_image'))) ?>" alt=""><?php endif; ?>
                <input class="input" type="file" id="og_image" name="og_image" accept="image/png,image/jpeg,image/webp">
            </div>
            <label class="switch"><input type="checkbox" name="enable_sitemap" value="1" <?= $get('enable_sitemap', true) ? 'checked' : '' ?>><span class="track"></span><span class="small bold">Publish sitemap.xml</span></label>

        <?php elseif ($tab === 'uploads'): ?>
            <div class="grid grid-3" style="gap:0 12px">
                <div class="field"><label for="max_upload_mb">Max upload size (MB)</label><input class="input" type="number" id="max_upload_mb" name="max_upload_mb" min="1" max="64" value="<?= (int) $get('max_upload_mb', 5) ?>"></div>
                <div class="field"><label for="image_quality">JPEG/WebP quality</label><input class="input" type="number" id="image_quality" name="image_quality" min="40" max="100" value="<?= (int) $get('image_quality', 82) ?>"></div>
                <div class="field"><label for="max_image_width">Max image width (px)</label><input class="input" type="number" id="max_image_width" name="max_image_width" min="400" max="4000" value="<?= (int) $get('max_image_width', 1600) ?>"></div>
            </div>
            <label class="switch"><input type="checkbox" name="generate_webp" value="1" <?= $get('generate_webp', true) ? 'checked' : '' ?>><span class="track"></span><span class="small bold">Also create WebP copies of uploaded images</span></label>
            <p class="tiny muted mt-2">Uploads are always re-encoded through GD, which strips metadata and any embedded payload. PHP execution is blocked inside the uploads folder.</p>

        <?php else: ?>
            <div class="field">
                <label for="terms_content">Terms of service</label>
                <textarea class="textarea" id="terms_content" name="terms_content" rows="12"><?= e((string) $get('terms_content')) ?></textarea>
            </div>
            <div class="field">
                <label for="privacy_content">Privacy policy</label>
                <textarea class="textarea" id="privacy_content" name="privacy_content" rows="12"><?= e((string) $get('privacy_content')) ?></textarea>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-footer text-right"><button class="btn" type="submit"><?= icon('save', 16) ?> Save <?= e($labels[$tab] ?? $tab) ?> settings</button></div>
</form>
<?php $__view->stop(); ?>

<?php $__view->start('scripts'); ?>
<script>
(function () {
  const rz = document.getElementById('test-razorpay');
  if (rz) {
    rz.addEventListener('click', async function () {
      const out = document.getElementById('razorpay-result');
      rz.disabled = true; out.textContent = 'Testing…';
      try {
        const r = await DVC.request('admin/settings/test/razorpay', { method: 'POST', body: new URLSearchParams({ _token: DVC.token }) });
        out.textContent = r.message;
        out.style.color = r.success ? 'var(--success)' : 'var(--danger)';
      } catch (e) { out.textContent = e.message; out.style.color = 'var(--danger)'; }
      rz.disabled = false;
    });
  }

  const smtp = document.getElementById('test-smtp');
  if (smtp) {
    smtp.addEventListener('click', async function () {
      const out = document.getElementById('smtp-result');
      const to = prompt('Send the test email to:', '');
      if (to === null) return;
      smtp.disabled = true; out.textContent = 'Sending…';
      try {
        const r = await DVC.request('admin/settings/test/smtp', { method: 'POST', body: new URLSearchParams({ _token: DVC.token, email: to }) });
        out.textContent = r.message;
        out.style.color = r.success ? 'var(--success)' : 'var(--danger)';
      } catch (e) { out.textContent = e.message; out.style.color = 'var(--danger)'; }
      smtp.disabled = false;
    });
  }
})();
</script>
<?php $__view->stop(); ?>
