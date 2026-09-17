<?php $__view->extend('layouts.install'); ?>
<?php $__view->start('content'); ?>
    <p class="muted">Create the super administrator account. This account has full access to every part of the platform.</p>

    <form method="post" action="<?= e(url_path('install/admin')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label class="required" for="name">Full name</label>
            <input class="input" type="text" id="name" name="name" value="<?= e(old('name')) ?>" required autocomplete="name">
        </div>
        <div class="grid grid-2" style="gap:0 16px">
            <div class="field">
                <label class="required" for="email">Email address</label>
                <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email">
            </div>
            <div class="field">
                <label for="phone">Mobile number</label>
                <input class="input" type="tel" id="phone" name="phone" value="<?= e(old('phone')) ?>" autocomplete="tel">
            </div>
        </div>
        <div class="grid grid-2" style="gap:0 16px">
            <div class="field">
                <label class="required" for="password">Password</label>
                <input class="input" type="password" id="password" name="password" required autocomplete="new-password" minlength="8">
                <div class="hint">At least 8 characters with a letter and a number.</div>
            </div>
            <div class="field">
                <label class="required" for="password_confirmation">Confirm password</label>
                <input class="input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
            </div>
        </div>
        <button class="btn btn-lg btn-block" type="submit">Create administrator <?= icon('arrow-right', 16) ?></button>
    </form>
<?php $__view->stop(); ?>
