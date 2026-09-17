<?php /** @var array<int,array<string,mixed>> $plans @var float $balance */ $__view->extend('layouts.panel'); ?>
<?php $__view->start('content'); ?>
<div class="container-sm" style="margin:0">
    <div class="alert alert-info">
        <?= icon('wallet', 18) ?>
        <div>Wallet balance: <strong><?= e(money($balance)) ?></strong>. Activating a paid plan deducts your reseller price from this balance.</div>
    </div>

    <form method="post" action="<?= e(url('reseller/customers')) ?>" class="card">
        <?= csrf_field() ?>
        <div class="card-header"><h2>New customer</h2></div>
        <div class="card-body">
            <div class="grid grid-2" style="gap:0 16px">
                <div class="field"><label class="required" for="name">Full name</label><input class="input" type="text" id="name" name="name" value="<?= e(old('name')) ?>" required></div>
                <div class="field"><label class="required" for="email">Email</label><input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required></div>
            </div>
            <div class="grid grid-2" style="gap:0 16px">
                <div class="field"><label class="required" for="phone">Phone</label><input class="input" type="tel" id="phone" name="phone" value="<?= e(old('phone')) ?>" required></div>
                <div class="field">
                    <label class="required" for="password">Password</label>
                    <input class="input" type="text" id="password" name="password" required minlength="8" value="<?= e(substr(bin2hex(random_bytes(6)), 0, 10)) ?>1a">
                    <div class="hint">Share this with your customer securely; they can change it later.</div>
                </div>
            </div>
            <div class="field">
                <label for="plan_id">Activate a plan now</label>
                <select id="plan_id" name="plan_id">
                    <option value="">Free plan only</option>
                    <?php foreach ($plans as $plan): ?>
                        <?php if ((int) $plan['is_free'] === 1) { continue; } ?>
                        <option value="<?= (int) $plan['id'] ?>" <?= (float) $plan['your_price'] > $balance ? 'disabled' : '' ?>>
                            <?= e((string) $plan['name']) ?> — your price <?= e(money((float) $plan['your_price'])) ?>
                            (MRP <?= e(money((float) $plan['price'])) ?>)
                            <?= (float) $plan['your_price'] > $balance ? ' — insufficient balance' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="card-footer row-between">
            <a class="btn btn-ghost" href="<?= e(url('reseller/customers')) ?>">Cancel</a>
            <button class="btn" type="submit">Create customer</button>
        </div>
    </form>
</div>
<?php $__view->stop(); ?>
