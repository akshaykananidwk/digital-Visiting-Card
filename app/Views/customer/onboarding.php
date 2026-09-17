<?php
/** @var array<int,array<string,mixed>> $categories @var array<int,array<string,mixed>> $suggested */
$__view->extend('layouts.panel');
?>
<?php $__view->start('content'); ?>
<div class="container-sm" style="margin:0">
    <div class="text-center mb-4">
        <span class="eyebrow"><?= icon('rocket', 14) ?> Let's get you online</span>
        <h1 style="font-size:1.6rem">Create your first digital card</h1>
        <p class="muted">Fill in the basics — you can add services, products and photos afterwards.</p>
    </div>

    <form method="post" action="<?= e(url_path('onboarding')) ?>" class="card">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="field">
                <label class="required" for="business_category">What kind of business is this?</label>
                <input class="input" type="text" id="business_category" name="business_category" value="<?= e(old('business_category')) ?>" list="cats" required placeholder="e.g. Computer Shop, CCTV, Doctor, Hotel">
                <datalist id="cats">
                    <?php foreach ($categories as $group): foreach ($group['children'] as $child): ?>
                        <option value="<?= e((string) $child['name']) ?>"></option>
                    <?php endforeach; endforeach; ?>
                </datalist>
                <div class="hint">We use this to suggest designs that suit your industry.</div>
            </div>

            <div class="grid grid-2" style="gap:0 16px">
                <div class="field">
                    <label class="required" for="full_name">Your name</label>
                    <input class="input" type="text" id="full_name" name="full_name" value="<?= e(old('full_name', (string) (user()['name'] ?? ''))) ?>" required>
                </div>
                <div class="field">
                    <label for="business_name">Business name</label>
                    <input class="input" type="text" id="business_name" name="business_name" value="<?= e(old('business_name')) ?>">
                </div>
            </div>

            <div class="field">
                <label class="required" for="title">Card name</label>
                <input class="input" type="text" id="title" name="title" value="<?= e(old('title')) ?>" required placeholder="e.g. My business card">
                <div class="hint">For your own reference — it is not shown on the card.</div>
            </div>

            <div class="grid grid-2" style="gap:0 16px">
                <div class="field">
                    <label class="required" for="phone">Phone number</label>
                    <input class="input" type="tel" id="phone" name="phone" value="<?= e(old('phone', (string) (user()['phone'] ?? ''))) ?>" required>
                </div>
                <div class="field">
                    <label for="whatsapp">WhatsApp number</label>
                    <input class="input" type="tel" id="whatsapp" name="whatsapp" value="<?= e(old('whatsapp')) ?>" placeholder="Same as phone if blank">
                </div>
            </div>

            <div class="grid grid-2" style="gap:0 16px">
                <div class="field">
                    <label for="email">Email</label>
                    <input class="input" type="email" id="email" name="email" value="<?= e(old('email', (string) (user()['email'] ?? ''))) ?>">
                </div>
                <div class="field">
                    <label for="city">City</label>
                    <input class="input" type="text" id="city" name="city" value="<?= e(old('city')) ?>">
                </div>
            </div>

            <div class="field">
                <label for="slug">Choose your link</label>
                <div class="input-group">
                    <span class="addon"><?= e(rtrim(url('card'), '/')) ?>/</span>
                    <input class="input" type="text" id="slug" name="slug" value="<?= e(old('slug')) ?>" data-slug-check data-slug-status="#slug-status" placeholder="your-business">
                </div>
                <div class="hint" id="slug-status">Leave blank and we will suggest one for you.</div>
            </div>
        </div>
        <div class="card-footer text-right">
            <button class="btn btn-lg" type="submit">Create my card <?= icon('arrow-right', 16) ?></button>
        </div>
    </form>

    <p class="text-center small muted mt-3"><a href="<?= e(url('dashboard')) ?>">Skip for now</a></p>
</div>
<?php $__view->stop(); ?>
