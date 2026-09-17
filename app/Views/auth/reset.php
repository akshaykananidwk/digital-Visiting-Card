<?php /** @var string $token @var string $email */ $__view->extend('layouts.auth'); ?>
<?php $__view->start('content'); ?>
    <h1 style="font-size:1.45rem;margin-bottom:4px">Choose a new password</h1>
    <p class="muted small mb-3">Resetting the password for <strong><?= e($email) ?></strong>.</p>

    <form method="post" action="<?= e(url('reset-password')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field">
            <label class="required" for="password">New password</label>
            <input class="input" type="password" id="password" name="password" required autocomplete="new-password" minlength="8" autofocus>
            <div class="hint">At least 8 characters including a letter and a number.</div>
        </div>
        <div class="field">
            <label class="required" for="password_confirmation">Confirm new password</label>
            <input class="input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
        </div>
        <button class="btn btn-lg btn-block" type="submit">Update password</button>
    </form>
<?php $__view->stop(); ?>
