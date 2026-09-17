<?php $__view->extend('layouts.admin'); ?>
<?php $__view->start('content'); ?>
<form method="post" action="<?= e(url_path('admin/resellers')) ?>" class="card" style="max-width:720px">
    <?= csrf_field() ?>
    <div class="card-header"><h2>New reseller</h2></div>
    <div class="card-body">
        <p class="small muted">This creates a user account with the reseller role plus the reseller profile and wallet.</p>

        <div class="grid grid-2" style="gap:0 16px">
            <div class="field"><label class="required" for="name">Contact name</label><input class="input" type="text" id="name" name="name" value="<?= e(old('name')) ?>" required></div>
            <div class="field"><label class="required" for="company_name">Company name</label><input class="input" type="text" id="company_name" name="company_name" value="<?= e(old('company_name')) ?>" required></div>
        </div>
        <div class="grid grid-2" style="gap:0 16px">
            <div class="field"><label class="required" for="email">Email (sign-in)</label><input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required></div>
            <div class="field"><label class="required" for="phone">Phone</label><input class="input" type="tel" id="phone" name="phone" value="<?= e(old('phone')) ?>" required></div>
        </div>
        <div class="field">
            <label class="required" for="password">Password</label>
            <input class="input" type="text" id="password" name="password" required minlength="8" value="<?= e(substr(bin2hex(random_bytes(6)), 0, 10)) ?>1a">
        </div>
        <div class="grid grid-3" style="gap:0 12px">
            <div class="field">
                <label for="commission_rate">Commission (%)</label>
                <input class="input" type="number" id="commission_rate" name="commission_rate" value="20" min="0" max="90" step="0.01">
                <div class="hint">Discount off plan price.</div>
            </div>
            <div class="field">
                <label for="credit_limit">Credit limit</label>
                <input class="input" type="number" id="credit_limit" name="credit_limit" value="0" min="0" step="0.01">
                <div class="hint">How far the wallet may go negative.</div>
            </div>
            <div class="field">
                <label for="opening_balance">Opening wallet balance</label>
                <input class="input" type="number" id="opening_balance" name="opening_balance" value="0" min="0" step="0.01">
            </div>
        </div>
    </div>
    <div class="card-footer row-between">
        <a class="btn btn-ghost" href="<?= e(url('admin/resellers')) ?>">Cancel</a>
        <button class="btn" type="submit">Create reseller</button>
    </div>
</form>
<?php $__view->stop(); ?>
