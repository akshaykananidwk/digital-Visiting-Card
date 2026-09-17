<?php $__view->extend('layouts.install'); ?>
<?php $__view->start('content'); ?>
    <div class="text-center">
        <div class="feature-icon" style="margin:0 auto 16px;width:64px;height:64px;background:var(--success-soft);color:var(--success)">
            <?= icon('check-circle', 30) ?>
        </div>
        <h2>Installation complete</h2>
        <p class="muted">Your Digital Visiting Card platform is ready. Sign in with the administrator account you created.</p>
    </div>

    <div class="alert alert-warning mt-3">
        <?= icon('shield', 18) ?>
        <div>
            <strong>Recommended next steps</strong>
            <ul style="margin:6px 0 0;padding-left:18px">
                <li>Delete the <code>/install</code> folder from your server.</li>
                <li>Set <code>.env</code> permissions to <code>600</code>.</li>
                <li>Install an SSL certificate and confirm <code>APP_URL</code> uses <code>https://</code>.</li>
                <li>Add your Razorpay webhook: <code><?= e(url('webhooks/razorpay')) ?></code></li>
            </ul>
        </div>
    </div>

    <a class="btn btn-lg btn-block mt-3" href="<?= e($login_url) ?>">Go to sign in <?= icon('arrow-right', 16) ?></a>
<?php $__view->stop(); ?>
