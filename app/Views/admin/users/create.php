<?php /** @var array<int,array<string,mixed>> $plans @var array<int,array<string,mixed>> $resellers */ $__view->extend('layouts.admin'); ?>
<?php $__view->start('content'); ?>
<form method="post" action="<?= e(url('admin/users')) ?>" class="card" style="max-width:720px">
    <?= csrf_field() ?>
    <div class="card-header"><h2>New user</h2></div>
    <div class="card-body">
        <div class="grid grid-2" style="gap:0 16px">
            <div class="field"><label class="required" for="name">Full name</label><input class="input" type="text" id="name" name="name" value="<?= e(old('name')) ?>" required></div>
            <div class="field"><label class="required" for="email">Email</label><input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required></div>
        </div>
        <div class="grid grid-2" style="gap:0 16px">
            <div class="field"><label for="phone">Phone</label><input class="input" type="tel" id="phone" name="phone" value="<?= e(old('phone')) ?>"></div>
            <div class="field"><label class="required" for="password">Password</label><input class="input" type="password" id="password" name="password" required minlength="8"></div>
        </div>
        <div class="grid grid-2" style="gap:0 16px">
            <div class="field">
                <label class="required" for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="customer">Customer</option>
                    <option value="reseller">Reseller</option>
                    <?php if (auth()->isSuperAdmin()): ?>
                        <option value="admin">Administrator</option>
                        <option value="super_admin">Super administrator</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="field">
                <label class="required" for="status">Status</label>
                <select id="status" name="status" required>
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
        </div>
        <div class="grid grid-2" style="gap:0 16px">
            <div class="field">
                <label for="plan_id">Assign plan</label>
                <select id="plan_id" name="plan_id">
                    <option value="">No plan</option>
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?= (int) $plan['id'] ?>"><?= e((string) $plan['name']) ?> — <?= e(money((float) $plan['price'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="reseller_id">Belongs to reseller</label>
                <select id="reseller_id" name="reseller_id">
                    <option value="">Direct customer</option>
                    <?php foreach ($resellers as $reseller): ?>
                        <option value="<?= (int) $reseller['id'] ?>"><?= e((string) $reseller['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    <div class="card-footer row-between">
        <a class="btn btn-ghost" href="<?= e(url('admin/users')) ?>">Cancel</a>
        <button class="btn" type="submit">Create user</button>
    </div>
</form>
<?php $__view->stop(); ?>
