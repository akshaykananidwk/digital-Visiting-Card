<?php $__view->extend('layouts.auth'); ?>
<?php $__view->start('content'); ?>
    <h1 style="font-size:1.45rem;margin-bottom:4px">Reset your password</h1>
    <p class="muted small mb-3">Enter your email address and we will send you a reset link.</p>

    <form method="post" action="<?= e(url('forgot-password')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label class="required" for="email">Email address</label>
            <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email" autofocus>
        </div>
        <button class="btn btn-lg btn-block" type="submit">Send reset link</button>
    </form>

    <p class="text-center small muted mt-3"><a href="<?= e(url('login')) ?>">&larr; Back to sign in</a></p>
<?php $__view->stop(); ?>
