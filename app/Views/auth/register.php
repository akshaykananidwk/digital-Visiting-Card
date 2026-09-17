<?php $__view->extend('layouts.auth'); ?>
<?php $__view->start('content'); ?>
    <h1 style="font-size:1.45rem;margin-bottom:4px">Create your account</h1>
    <p class="muted small mb-3">Your digital visiting card is ready in about 5 minutes.</p>

    <form method="post" action="<?= e(url_path('register')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label class="required" for="name">Full name</label>
            <input class="input" type="text" id="name" name="name" value="<?= e(old('name')) ?>" required autocomplete="name" autofocus>
        </div>
        <div class="field">
            <label class="required" for="email">Email address</label>
            <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email">
        </div>
        <div class="field">
            <label class="required" for="phone">Mobile number</label>
            <input class="input" type="tel" id="phone" name="phone" value="<?= e(old('phone')) ?>" required autocomplete="tel">
        </div>
        <div class="field">
            <label class="required" for="password">Password</label>
            <input class="input" type="password" id="password" name="password" required autocomplete="new-password" minlength="8">
            <div class="hint">At least 8 characters including a letter and a number.</div>
        </div>
        <div class="field">
            <label class="required" for="password_confirmation">Confirm password</label>
            <input class="input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
        </div>
        <label class="checkbox mb-2">
            <input type="checkbox" name="terms" value="1" required <?= old('terms') ? 'checked' : '' ?>>
            <span class="small">I agree to the <a href="<?= e(url('terms')) ?>" target="_blank">terms of service</a> and <a href="<?= e(url('privacy')) ?>" target="_blank">privacy policy</a>.</span>
        </label>
        <button class="btn btn-lg btn-block" type="submit">Create my account</button>
    </form>

    <p class="text-center small muted mt-3">Already have an account? <a href="<?= e(url('login')) ?>">Sign in</a></p>
<?php $__view->stop(); ?>
