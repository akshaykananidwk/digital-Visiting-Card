<?php $__view->extend('layouts.public'); ?>
<?php $__view->start('content'); ?>
<div class="container section container-sm">
    <div class="text-center mb-4">
        <span class="eyebrow">Contact</span>
        <h1>We would love to hear from you</h1>
    </div>

    <div class="grid grid-2" style="align-items:start">
        <div class="card"><div class="card-body">
            <form method="post" action="<?= e(url_path('contact')) ?>">
                <?= csrf_field() ?>
                <div style="position:absolute;left:-9999px" aria-hidden="true">
                    <input type="text" name="website_url" tabindex="-1" autocomplete="off">
                </div>
                <div class="field">
                    <label class="required" for="name">Your name</label>
                    <input class="input" type="text" id="name" name="name" value="<?= e(old('name')) ?>" required>
                </div>
                <div class="field">
                    <label class="required" for="email">Email address</label>
                    <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required>
                </div>
                <div class="field">
                    <label for="phone">Phone number</label>
                    <input class="input" type="tel" id="phone" name="phone" value="<?= e(old('phone')) ?>">
                </div>
                <div class="field">
                    <label for="subject">Subject</label>
                    <input class="input" type="text" id="subject" name="subject" value="<?= e(old('subject', $request->string('subject'))) ?>">
                </div>
                <div class="field">
                    <label class="required" for="message">Message</label>
                    <textarea class="textarea" id="message" name="message" required minlength="10"><?= e(old('message')) ?></textarea>
                </div>
                <button class="btn btn-block" type="submit">Send message</button>
            </form>
        </div></div>

        <div class="stack">
            <?php if (!empty($branding['support_phone'])): ?>
                <div class="card"><div class="card-body row" style="gap:14px;align-items:center">
                    <span class="feature-icon" style="margin:0"><?= icon('phone', 20) ?></span>
                    <div><div class="small muted">Call us</div><a class="bold" href="tel:<?= e($branding['support_phone']) ?>"><?= e($branding['support_phone']) ?></a></div>
                </div></div>
            <?php endif; ?>
            <?php if (!empty($branding['whatsapp'])): ?>
                <div class="card"><div class="card-body row" style="gap:14px;align-items:center">
                    <span class="feature-icon" style="margin:0"><?= icon('whatsapp', 20) ?></span>
                    <div><div class="small muted">WhatsApp</div><a class="bold" href="https://wa.me/<?= e(preg_replace('/\D/', '', $branding['whatsapp'])) ?>" target="_blank" rel="noopener"><?= e($branding['whatsapp']) ?></a></div>
                </div></div>
            <?php endif; ?>
            <?php if (!empty($branding['support_email'])): ?>
                <div class="card"><div class="card-body row" style="gap:14px;align-items:center">
                    <span class="feature-icon" style="margin:0"><?= icon('mail', 20) ?></span>
                    <div><div class="small muted">Email</div><a class="bold" href="mailto:<?= e($branding['support_email']) ?>"><?= e($branding['support_email']) ?></a></div>
                </div></div>
            <?php endif; ?>
            <div class="card"><div class="card-body">
                <h3 style="font-size:1rem">Looking for help?</h3>
                <p class="small muted">Most questions are answered in our <a href="<?= e(url('faq')) ?>">FAQ</a>.</p>
            </div></div>
        </div>
    </div>
</div>
<?php $__view->stop(); ?>
