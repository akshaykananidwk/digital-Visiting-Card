<?php /** @var string $heading @var string $body */ $__view->extend('layouts.public'); ?>
<?php $__view->start('content'); ?>
<div class="container section container-sm">
    <h1><?= e($heading) ?></h1>
    <div class="card"><div class="card-body">
        <?php if (trim($body) !== ''): ?>
            <div style="white-space:pre-line"><?= e($body) ?></div>
        <?php else: ?>
            <p class="muted">
                The platform administrator has not published a <?= e(strtolower($heading)) ?> yet.
                Please <a href="<?= e(url('contact')) ?>">contact us</a> with any questions about how this service operates,
                what data is stored and how it is used.
            </p>
            <p class="small muted">Administrators can publish this page from Admin → Settings → Legal.</p>
        <?php endif; ?>
    </div></div>
</div>
<?php $__view->stop(); ?>
