<?php
/** @var array<string,mixed>|null $template @var array<int,array<string,mixed>> $suggested @var array<int,array<string,mixed>> $categories */
$__view->extend('layouts.panel');
?>
<?php $__view->start('content'); ?>
<div class="container-sm" style="margin:0">
    <form method="post" action="<?= e(url_path('cards')) ?>" class="card">
        <?= csrf_field() ?>
        <div class="card-header"><h2>Card details</h2></div>
        <div class="card-body">
            <?php if ($template !== null): ?>
                <div class="alert alert-info">
                    <?= icon('layers', 18) ?>
                    <div>Using the design <strong><?= e((string) $template['name']) ?></strong>. You can change it later at any time.</div>
                </div>
                <input type="hidden" name="template_id" value="<?= (int) $template['id'] ?>">
            <?php endif; ?>

            <div class="field">
                <label class="required" for="title">Card name (for your reference)</label>
                <input class="input" type="text" id="title" name="title" value="<?= e(old('title')) ?>" required placeholder="e.g. Shop card">
            </div>

            <div class="grid grid-2" style="gap:0 16px">
                <div class="field">
                    <label class="required" for="full_name">Your name</label>
                    <input class="input" type="text" id="full_name" name="full_name" value="<?= e(old('full_name', (string) (user()['name'] ?? ''))) ?>" required>
                </div>
                <div class="field">
                    <label for="designation">Designation</label>
                    <input class="input" type="text" id="designation" name="designation" value="<?= e(old('designation')) ?>" placeholder="Proprietor">
                </div>
            </div>

            <div class="grid grid-2" style="gap:0 16px">
                <div class="field">
                    <label for="business_name">Business name</label>
                    <input class="input" type="text" id="business_name" name="business_name" value="<?= e(old('business_name')) ?>">
                </div>
                <div class="field">
                    <label for="business_category">Business category</label>
                    <input class="input" type="text" id="business_category" name="business_category" value="<?= e(old('business_category')) ?>" list="category-list" placeholder="e.g. CCTV & Security">
                    <datalist id="category-list">
                        <?php foreach ($categories as $group): foreach ($group['children'] as $child): ?>
                            <option value="<?= e((string) $child['name']) ?>"></option>
                        <?php endforeach; endforeach; ?>
                    </datalist>
                </div>
            </div>

            <div class="grid grid-2" style="gap:0 16px">
                <div class="field">
                    <label class="required" for="phone">Phone number</label>
                    <input class="input" type="tel" id="phone" name="phone" value="<?= e(old('phone', (string) (user()['phone'] ?? ''))) ?>" required>
                </div>
                <div class="field">
                    <label for="whatsapp">WhatsApp number</label>
                    <input class="input" type="tel" id="whatsapp" name="whatsapp" value="<?= e(old('whatsapp')) ?>" placeholder="Same as phone if empty">
                </div>
            </div>

            <div class="grid grid-2" style="gap:0 16px">
                <div class="field">
                    <label for="email">Email address</label>
                    <input class="input" type="email" id="email" name="email" value="<?= e(old('email', (string) (user()['email'] ?? ''))) ?>">
                </div>
                <div class="field">
                    <label for="city">City</label>
                    <input class="input" type="text" id="city" name="city" value="<?= e(old('city')) ?>">
                </div>
            </div>

            <div class="field">
                <label for="slug">Your card link</label>
                <div class="input-group">
                    <span class="addon"><?= e(rtrim(url('card'), '/')) ?>/</span>
                    <input class="input" type="text" id="slug" name="slug" value="<?= e(old('slug')) ?>"
                           placeholder="ak-computer" data-slug-check data-slug-status="#slug-status" pattern="[a-z0-9\-]{3,100}">
                </div>
                <div class="hint" id="slug-status">Leave empty and we will create one from your business name.</div>
            </div>
        </div>
        <div class="card-footer row-between">
            <a class="btn btn-ghost" href="<?= e(url('cards')) ?>">Cancel</a>
            <button class="btn" type="submit">Create card <?= icon('arrow-right', 16) ?></button>
        </div>
    </form>

    <?php if ($template === null && $suggested !== []): ?>
        <div class="card mt-3">
            <div class="card-header">
                <h2>Or start from a design</h2>
                <a class="small" href="<?= e(url('templates')) ?>">Browse all</a>
            </div>
            <div class="card-body">
                <div class="template-grid">
                    <?php foreach (array_slice($suggested, 0, 6) as $item): ?>
                        <a class="template-card" href="<?= e(url('cards/create?template=' . (int) $item['id'])) ?>">
                            <div class="template-thumb">
                                <iframe src="<?= e(url_path('templates/preview/' . $item['code'])) ?>" title="<?= e((string) $item['name']) ?>" loading="lazy" tabindex="-1" scrolling="no"></iframe>
                            </div>
                            <div class="template-meta"><div class="name truncate"><?= e((string) $item['name']) ?></div></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php $__view->stop(); ?>
