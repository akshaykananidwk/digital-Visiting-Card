<?php /** @var App\Core\View $__view */ $__view->extend('layouts.install'); ?>
<?php $__view->start('content'); ?>
    <p class="lead">Welcome! This wizard will configure your Digital Visiting Card platform in a few minutes — no manual SQL import and no config file editing required.</p>

    <div class="grid grid-2 mt-3">
        <div class="card"><div class="card-body">
            <div class="feature-icon"><?= icon('database', 22) ?></div>
            <h3 style="font-size:1rem">Database &amp; tables</h3>
            <p class="small muted mb-0">Enter your MySQL details once — tables, indexes and default data are created for you.</p>
        </div></div>
        <div class="card"><div class="card-body">
            <div class="feature-icon"><?= icon('layers', 22) ?></div>
            <h3 style="font-size:1rem">1,200 designs</h3>
            <p class="small muted mb-0">The design library is generated during installation, ready for your customers to pick from.</p>
        </div></div>
        <div class="card"><div class="card-body">
            <div class="feature-icon"><?= icon('shield', 22) ?></div>
            <h3 style="font-size:1rem">Secure by default</h3>
            <p class="small muted mb-0">Encryption keys, hardened sessions and upload protection are configured automatically.</p>
        </div></div>
        <div class="card"><div class="card-body">
            <div class="feature-icon"><?= icon('credit-card', 22) ?></div>
            <h3 style="font-size:1rem">Razorpay ready</h3>
            <p class="small muted mb-0">Add your keys now or later from Admin → Settings. Payments are always verified server-side.</p>
        </div></div>
    </div>

    <div class="alert alert-info mt-3">
        <?= icon('info', 18) ?>
        <div><strong>Before you start:</strong> create an empty MySQL database (or a database user with CREATE rights) in your hosting control panel. Running PHP <?= e($php ?? PHP_VERSION) ?>.</div>
    </div>
<?php $__view->stop(); ?>

<?php $__view->start('actions'); ?>
    <span class="small muted">Installer version <?= e($version ?? APP_VERSION) ?></span>
    <a class="btn" href="<?= e(url('install/requirements')) ?>">Start installation <?= icon('arrow-right', 16) ?></a>
<?php $__view->stop(); ?>
