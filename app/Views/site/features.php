<?php $__view->extend('layouts.public'); ?>
<?php $__view->start('content'); ?>
<div class="container section">
    <div class="text-center mb-4">
        <span class="eyebrow">Features</span>
        <h1>Everything a modern business card should do</h1>
        <p class="lead" style="margin-inline:auto">Built mobile-first, because that is where your card will be opened.</p>
    </div>

    <div class="grid grid-3">
        <?php
        $features = [
            ['phone', 'One-tap calling', 'A large Call button that dials straight from the card — no copying numbers.'],
            ['whatsapp', 'WhatsApp with a pre-filled message', 'Visitors land in your chat with a message you wrote, ready to send.'],
            ['user-plus', 'Save contact as vCard', 'Your name, number, email, company and address go straight into their phone book.'],
            ['navigation', 'Google Maps directions', 'Directions from wherever the visitor is standing.'],
            ['package', 'Products with prices', 'Show your catalogue with images, prices, discounts and WhatsApp enquiry buttons.'],
            ['zap', 'Services you offer', 'Describe each service with an icon, description, price and call to action.'],
            ['image', 'Gallery and videos', 'Photos and YouTube videos in a fast, lazy-loaded grid.'],
            ['clock', 'Business hours', 'Automatic open/closed badge based on the visitor\'s local time.'],
            ['qr', 'QR code', 'Download as PNG or SVG for boards, bills, packaging and shop shutters.'],
            ['inbox', 'Enquiry form', 'Every enquiry lands in your dashboard with spam protection built in.'],
            ['bar-chart', 'Analytics', 'Views, unique visitors, calls, WhatsApp clicks, saves and shares — per card, per day.'],
            ['rupee', 'UPI payments', 'Show your UPI ID with a one-tap pay button for any UPI app.'],
            ['layers', '1,000+ designs', 'Switch design any time — your content is never tied to a template.'],
            ['shield', 'Secure by design', 'Prepared statements, CSRF protection, hardened uploads and role-based access.'],
            ['link', 'Your own link', 'domain.com/your-name — memorable, shareable and permanent.'],
        ];
        foreach ($features as [$iconName, $heading, $text]): ?>
            <div class="card card-hover"><div class="card-body">
                <div class="feature-icon"><?= icon($iconName, 22) ?></div>
                <h3 style="font-size:1.02rem"><?= $heading ?></h3>
                <p class="small muted mb-0"><?= $text ?></p>
            </div></div>
        <?php endforeach; ?>
    </div>

    <div class="text-center mt-4">
        <a class="btn btn-lg" href="<?= e(url('register')) ?>">Create your card free</a>
    </div>
</div>
<?php $__view->stop(); ?>
