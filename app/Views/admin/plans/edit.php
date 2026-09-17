<?php /** @var array<string,mixed>|null $plan */ $__view->extend('layouts.admin');
$action = $plan === null ? url_path('admin/plans') : url_path('admin/plans/' . (int) $plan['id']);
$features = is_array($plan['features'] ?? null) ? implode("\n", $plan['features']) : '';
$value = static fn (string $key, mixed $default = '') => e((string) ($plan[$key] ?? $default));
?>
<?php $__view->start('content'); ?>
<form method="post" action="<?= e($action) ?>" class="card" style="max-width:840px">
    <?= csrf_field() ?>
    <div class="card-header"><h2><?= $plan === null ? 'New plan' : 'Edit plan' ?></h2></div>
    <div class="card-body">
        <div class="grid grid-2" style="gap:0 16px">
            <div class="field"><label class="required" for="name">Name</label><input class="input" type="text" id="name" name="name" value="<?= $value('name') ?>" required></div>
            <div class="field"><label class="required" for="slug">Slug</label><input class="input" type="text" id="slug" name="slug" value="<?= $value('slug') ?>" required pattern="[a-z0-9\-]+"></div>
        </div>
        <div class="field"><label for="description">Short description</label><input class="input" type="text" id="description" name="description" value="<?= $value('description') ?>" maxlength="255"></div>

        <fieldset>
            <legend>Pricing</legend>
            <div class="grid grid-4" style="gap:0 12px">
                <div class="field"><label class="required tiny" for="price">Price</label><input class="input" type="number" id="price" name="price" step="0.01" min="0" value="<?= $value('price', 0) ?>" required></div>
                <div class="field"><label class="tiny" for="reseller_price">Reseller price</label><input class="input" type="number" id="reseller_price" name="reseller_price" step="0.01" min="0" value="<?= $value('reseller_price', '') ?>"></div>
                <div class="field"><label class="tiny" for="mrp">MRP (strike-through)</label><input class="input" type="number" id="mrp" name="mrp" step="0.01" min="0" value="<?= $value('mrp', '') ?>"></div>
                <div class="field"><label class="required tiny" for="duration_days">Duration (days)</label><input class="input" type="number" id="duration_days" name="duration_days" min="1" max="36500" value="<?= $value('duration_days', 365) ?>" required></div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Limits <span class="tiny muted">(−1 = unlimited)</span></legend>
            <div class="grid grid-4" style="gap:0 12px">
                <?php foreach ([
                    'card_limit' => 'Cards', 'product_limit' => 'Products', 'service_limit' => 'Services',
                    'gallery_limit' => 'Gallery images', 'video_limit' => 'Videos', 'lead_limit' => 'Leads',
                    'storage_limit_mb' => 'Storage (MB)', 'trial_days' => 'Trial days',
                ] as $key => $label): ?>
                    <div class="field">
                        <label class="tiny" for="<?= e($key) ?>"><?= e($label) ?></label>
                        <input class="input" type="number" id="<?= e($key) ?>" name="<?= e($key) ?>" min="-1" value="<?= $value($key, 0) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Features</legend>
            <div class="row wrap" style="gap:16px">
                <?php foreach ([
                    'premium_templates' => 'Premium designs', 'custom_domain' => 'Custom domain',
                    'remove_branding' => 'Remove branding', 'analytics' => 'Analytics',
                    'qr_download' => 'QR download', 'vcard' => 'Save contact', 'enquiry_form' => 'Enquiry form',
                    'seo_controls' => 'SEO controls', 'api_access' => 'API access', 'priority_support' => 'Priority support',
                ] as $flag => $label): ?>
                    <label class="switch" style="min-width:190px">
                        <input type="checkbox" name="<?= e($flag) ?>" value="1" <?= (int) ($plan[$flag] ?? 0) === 1 ? 'checked' : '' ?>>
                        <span class="track"></span><span class="small"><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="field mt-2">
                <label for="features">Marketing bullet points (one per line)</label>
                <textarea class="textarea" id="features" name="features" rows="5"><?= e($features) ?></textarea>
            </div>
        </fieldset>

        <fieldset>
            <legend>Visibility</legend>
            <div class="row wrap" style="gap:16px">
                <?php foreach (['is_active' => 'Active', 'is_featured' => 'Featured', 'is_free' => 'Default free plan'] as $flag => $label): ?>
                    <label class="switch" style="min-width:190px">
                        <input type="checkbox" name="<?= e($flag) ?>" value="1" <?= (int) ($plan[$flag] ?? ($flag === 'is_active' ? 1 : 0)) === 1 ? 'checked' : '' ?>>
                        <span class="track"></span><span class="small"><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
                <div class="field" style="max-width:140px">
                    <label class="tiny" for="sort_order">Sort order</label>
                    <input class="input" type="number" id="sort_order" name="sort_order" min="0" max="1000" value="<?= $value('sort_order', 0) ?>">
                </div>
            </div>
        </fieldset>
    </div>
    <div class="card-footer row-between">
        <a class="btn btn-ghost" href="<?= e(url('admin/plans')) ?>">Cancel</a>
        <button class="btn" type="submit">Save plan</button>
    </div>
</form>
<?php $__view->stop(); ?>
