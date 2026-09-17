<?php /** @var App\Services\CardPresenter $card @var string $title */ ?>
<section class="dvc-section">
    <div class="dvc-panel">
        <h2 class="dvc-section-title"><?= icon('inbox', 15) ?> <?= e($title) ?></h2>
        <p style="margin:0 0 14px;font-size:.88rem;color:var(--c-muted)">Send a message and we will get back to you shortly.</p>

        <form class="dvc-form" method="post" action="<?= e(url_path('card/' . $card->card()['slug'] . '/enquiry')) ?>" data-enquiry-form>
            <?= csrf_field() ?>
            <!-- Honeypot: real visitors never fill this in. -->
            <div style="position:absolute;left:-9999px" aria-hidden="true">
                <label for="website_url">Leave this empty</label>
                <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off">
            </div>
            <input type="hidden" name="form_time" value="<?= time() ?>">

            <label class="sr-only" for="lead_name" style="position:absolute;left:-9999px">Your name</label>
            <input type="text" id="lead_name" name="name" placeholder="Your name *" required maxlength="150" autocomplete="name">

            <label class="sr-only" for="lead_phone" style="position:absolute;left:-9999px">Phone number</label>
            <input type="tel" id="lead_phone" name="phone" placeholder="Phone number *" required maxlength="25" autocomplete="tel">

            <label class="sr-only" for="lead_email" style="position:absolute;left:-9999px">Email address</label>
            <input type="email" id="lead_email" name="email" placeholder="Email address (optional)" maxlength="190" autocomplete="email">

            <label class="sr-only" for="lead_message" style="position:absolute;left:-9999px">Message</label>
            <textarea id="lead_message" name="message" placeholder="How can we help you?" maxlength="2000"></textarea>

            <div class="dvc-form-status" data-enquiry-status role="status"></div>
            <button class="dvc-btn block" type="submit"><?= icon('mail', 17) ?> Send enquiry</button>
        </form>
    </div>
</section>
