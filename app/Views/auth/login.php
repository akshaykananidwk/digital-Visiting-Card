<?php $__view->extend('layouts.auth'); ?>
<?php $__view->start('content'); ?>
    <h1 style="font-size:1.45rem;margin-bottom:4px">Welcome back</h1>
    <p class="muted small mb-3">Sign in to manage your digital cards.</p>

    <form method="post" action="<?= e(url('login')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label class="required" for="email">Email address</label>
            <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email" autofocus>
        </div>
        <div class="field">
            <div class="row-between" style="margin-bottom:6px">
                <label class="required" for="password" style="margin:0">Password</label>
                <a class="tiny" href="<?= e(url('forgot-password')) ?>">Forgot password?</a>
            </div>
            <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-lg btn-block" type="submit">Sign in</button>
    </form>

    <div class="divider-text">New here?</div>
    <a class="btn btn-secondary btn-block" href="<?= e(url('register')) ?>">Create a free account</a>
<?php $__view->stop(); ?>
