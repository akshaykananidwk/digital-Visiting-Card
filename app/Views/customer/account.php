<?php /** @var array<string,mixed> $user @var array<string,mixed> $summary */ $__view->extend('layouts.panel'); ?>
<?php $__view->start('content'); ?>
<div class="grid" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr);align-items:start">
    <div class="stack">
        <form method="post" action="<?= e(url_path('account/profile')) ?>" enctype="multipart/form-data" class="card">
            <?= csrf_field() ?>
            <div class="card-header"><h2>Profile</h2></div>
            <div class="card-body">
                <div class="row mb-3" style="gap:16px;align-items:center">
                    <?php if (!empty($user['avatar'])): ?>
                        <img class="avatar avatar-lg" src="<?= e((string) upload_url((string) $user['avatar'])) ?>" alt="">
                    <?php else: ?>
                        <span class="avatar avatar-lg avatar-fallback" style="font-size:1.5rem"><?= e(mb_strtoupper(mb_substr((string) $user['name'], 0, 1))) ?></span>
                    <?php endif; ?>
                    <div class="flex-1">
                        <label class="label" for="avatar">Profile picture</label>
                        <input class="input" type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp">
                    </div>
                </div>

                <div class="grid grid-2" style="gap:0 16px">
                    <div class="field">
                        <label class="required" for="name">Full name</label>
                        <input class="input" type="text" id="name" name="name" value="<?= e(old('name', (string) $user['name'])) ?>" required>
                    </div>
                    <div class="field">
                        <label class="required" for="phone">Phone</label>
                        <input class="input" type="tel" id="phone" name="phone" value="<?= e(old('phone', (string) ($user['phone'] ?? ''))) ?>" required>
                    </div>
                </div>

                <div class="field">
                    <label for="email_display">Email address</label>
                    <input class="input" type="email" id="email_display" value="<?= e((string) $user['email']) ?>" disabled>
                    <div class="hint">Contact support if you need to change your sign-in email address.</div>
                </div>

                <div class="grid grid-2" style="gap:0 16px">
                    <div class="field">
                        <label for="company">Company name</label>
                        <input class="input" type="text" id="company" name="company" value="<?= e(old('company', (string) ($user['company'] ?? ''))) ?>">
                    </div>
                    <div class="field">
                        <label for="gstin">GSTIN</label>
                        <input class="input" type="text" id="gstin" name="gstin" value="<?= e(old('gstin', (string) ($user['gstin'] ?? ''))) ?>" maxlength="20">
                        <div class="hint">Used on your invoices.</div>
                    </div>
                </div>

                <div class="field">
                    <label for="address">Billing address</label>
                    <input class="input" type="text" id="address" name="address" value="<?= e(old('address', (string) ($user['address'] ?? ''))) ?>">
                </div>
                <div class="grid grid-3" style="gap:0 12px">
                    <div class="field"><label for="city">City</label><input class="input" type="text" id="city" name="city" value="<?= e(old('city', (string) ($user['city'] ?? ''))) ?>"></div>
                    <div class="field"><label for="state">State</label><input class="input" type="text" id="state" name="state" value="<?= e(old('state', (string) ($user['state'] ?? ''))) ?>"></div>
                    <div class="field"><label for="pincode">PIN code</label><input class="input" type="text" id="pincode" name="pincode" value="<?= e(old('pincode', (string) ($user['pincode'] ?? ''))) ?>"></div>
                </div>
            </div>
            <div class="card-footer text-right"><button class="btn" type="submit"><?= icon('save', 16) ?> Save profile</button></div>
        </form>

        <form method="post" action="<?= e(url_path('account/password')) ?>" class="card">
            <?= csrf_field() ?>
            <div class="card-header"><h2>Password</h2></div>
            <div class="card-body">
                <div class="field">
                    <label class="required" for="current_password">Current password</label>
                    <input class="input" type="password" id="current_password" name="current_password" required autocomplete="current-password">
                </div>
                <div class="grid grid-2" style="gap:0 16px">
                    <div class="field">
                        <label class="required" for="password">New password</label>
                        <input class="input" type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="field">
                        <label class="required" for="password_confirmation">Confirm new password</label>
                        <input class="input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                    </div>
                </div>
                <p class="tiny muted">Changing your password signs you out of every device.</p>
            </div>
            <div class="card-footer text-right"><button class="btn" type="submit">Change password</button></div>
        </form>

        <div class="card" style="border-color:var(--danger)">
            <div class="card-header"><h2 style="color:var(--danger)">Delete account</h2></div>
            <div class="card-body">
                <p class="small muted">This permanently deletes your account, every card you created, your leads and your analytics. It cannot be undone.</p>
                <form method="post" action="<?= e(url_path('account/delete')) ?>" data-confirm="Delete your account and all of your cards permanently?">
                    <?= csrf_field() ?>
                    <div class="grid grid-2" style="gap:0 12px">
                        <div class="field">
                            <label for="confirm">Type DELETE to confirm</label>
                            <input class="input" type="text" id="confirm" name="confirm" placeholder="DELETE" required>
                        </div>
                        <div class="field">
                            <label for="delete_password">Your password</label>
                            <input class="input" type="password" id="delete_password" name="password" required autocomplete="current-password">
                        </div>
                    </div>
                    <button class="btn btn-danger" type="submit"><?= icon('trash', 15) ?> Delete my account</button>
                </form>
            </div>
        </div>
    </div>

    <div class="stack">
        <div class="card"><div class="card-body">
            <div class="label">Plan</div>
            <div class="bold" style="font-size:1.2rem"><?= e((string) $summary['plan']['name']) ?></div>
            <?php if ($summary['days_left'] !== null): ?>
                <div class="small muted"><?= (int) $summary['days_left'] ?> day(s) remaining</div>
            <?php endif; ?>
            <a class="btn btn-secondary btn-sm btn-block mt-2" href="<?= e(url('billing')) ?>">Manage plan</a>
        </div></div>

        <div class="card"><div class="card-body small">
            <div class="row-between" style="padding:5px 0"><span class="muted">Member since</span><strong><?= e(date('M Y', strtotime((string) $user['created_at']))) ?></strong></div>
            <div class="row-between" style="padding:5px 0"><span class="muted">Email verified</span>
                <strong><?= !empty($user['email_verified_at']) ? 'Yes' : 'No' ?></strong>
            </div>
            <?php if (empty($user['email_verified_at'])): ?>
                <form method="post" action="<?= e(url_path('verify-email/resend')) ?>" class="mt-2">
                    <?= csrf_field() ?>
                    <button class="btn btn-secondary btn-sm btn-block" type="submit">Send verification email</button>
                </form>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<?php $__view->stop(); ?>
