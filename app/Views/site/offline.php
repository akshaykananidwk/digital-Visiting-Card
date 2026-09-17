<?php $__view->extend('layouts.public'); ?>
<?php $__view->start('content'); ?>
<div class="container section text-center">
    <div class="feature-icon" style="margin:0 auto 18px;width:72px;height:72px"><?= icon('globe', 32) ?></div>
    <h1>You are offline</h1>
    <p class="muted">Check your internet connection and try again. Pages you have already opened will still work.</p>
    <button class="btn" type="button" onclick="location.reload()"><?= icon('refresh', 16) ?> Retry</button>
</div>
<?php $__view->stop(); ?>
