<?php $__view->extend('layouts.public'); ?>
<?php $__view->start('content'); ?>
<div class="container section container-sm">
    <div class="text-center mb-4">
        <span class="eyebrow">How it works</span>
        <h1>From sign-up to shared link in minutes</h1>
    </div>

    <div class="stack" style="--stack-gap:18px">
        <?php
        $steps = [
            ['Create your account', 'Name, email and mobile number — that is all we need to start.'],
            ['Choose your business category', 'We use it to suggest the designs that suit your industry.'],
            ['Pick a design', 'Preview each design inside a phone frame. You can change it later at any time.'],
            ['Add your details', 'Profile photo, logo, contact numbers, address, services and products.'],
            ['Preview live', 'The preview updates as you type so there are no surprises.'],
            ['Publish', 'Your card goes live at your own link with a QR code generated automatically.'],
            ['Share everywhere', 'WhatsApp, Instagram bio, SMS, email signature, shop board or printed QR.'],
        ];
        foreach ($steps as $index => [$heading, $text]): ?>
            <div class="card"><div class="card-body row" style="gap:16px;flex-wrap:nowrap;align-items:flex-start">
                <span class="feature-icon" style="margin:0;flex:none;font-weight:800"><?= $index + 1 ?></span>
                <div>
                    <h3 style="font-size:1.05rem;margin-bottom:4px"><?= e($heading) ?></h3>
                    <p class="small muted mb-0"><?= e($text) ?></p>
                </div>
            </div></div>
        <?php endforeach; ?>
    </div>

    <div class="text-center mt-4"><a class="btn btn-lg" href="<?= e(url('register')) ?>">Start now — it is free</a></div>
</div>
<?php $__view->stop(); ?>
