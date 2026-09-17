<?php
/** @var array<string,mixed> $user @var array<int,array<string,mixed>> $cards
 *  @var array<int,array<string,mixed>> $orders @var array<string,mixed>|null $subscription
 *  @var array<string,mixed> $plan @var array<int,array<string,mixed>> $plans
 *  @var array<int,array<string,mixed>> $resellers @var bool $canManage */
$__view->extend('layouts.admin');
$userId = (int) $user['id'];
?>
<?php $__view->start('topbar'); ?>
<?php if ($canManage && auth()->can('admin.impersonate') && (string) $user['role'] === 'customer'): ?>
    <form method="post" action="<?= e(url('admin/users/' . $userId . '/impersonate')) ?>" data-confirm="Sign in as this customer? This action is recorded in the audit trail.">
        <?= csrf_field() ?>
        <button class="btn btn-sm btn-secondary" type="submit"><?= icon('key', 15) ?> Login as user</button>
    </form>
<?php endif; ?>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<?php if (!$canManage): ?>
    <div class="alert alert-warning"><?= icon('lock', 18) ?><div>This account has the same or a higher role than yours, so it is read-only for you.</div></div>
<?php endif; ?>

<div class="grid" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr);align-items:start">
    <div class="stack">
        <form method="post" action="<?= e(url('admin/users/' . $userId)) ?>" class="card">
            <?= csrf_field() ?>
            <div class="card-header"><h2>Account</h2>
                <span class="badge <?= (string) $user['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= e((string) $user['status']) ?></span>
            </div>
            <div class="card-body">
                <div class="grid grid-2" style="gap:0 16px">
                    <div class="field"><label for="name">Name</label><input class="input" type="text" id="name" name="name" value="<?= e((string) $user['name']) ?>" <?= $canManage ? '' : 'disabled' ?>></div>
                    <div class="field"><label for="email">Email</label><input class="input" type="email" id="email" name="email" value="<?= e((string) $user['email']) ?>" <?= $canManage ? '' : 'disabled' ?>></div>
                </div>
                <div class="grid grid-2" style="gap:0 16px">
                    <div class="field"><label for="phone">Phone</label><input class="input" type="tel" id="phone" name="phone" value="<?= e((string) ($user['phone'] ?? '')) ?>" <?= $canManage ? '' : 'disabled' ?>></div>
                    <div class="field"><label for="company">Company</label><input class="input" type="text" id="company" name="company" value="<?= e((string) ($user['company'] ?? '')) ?>" <?= $canManage ? '' : 'disabled' ?>></div>
                </div>
                <div class="grid grid-2" style="gap:0 16px">
                    <div class="field"><label for="gstin">GSTIN</label><input class="input" type="text" id="gstin" name="gstin" value="<?= e((string) ($user['gstin'] ?? '')) ?>" <?= $canManage ? '' : 'disabled' ?>></div>
                    <div class="field">
                        <label for="role">Role</label>
                        <select id="role" name="role" <?= (auth()->isSuperAdmin() && $canManage) ? '' : 'disabled' ?>>
                            <?php foreach (['customer' => 'Customer', 'reseller' => 'Reseller', 'admin' => 'Administrator', 'super_admin' => 'Super administrator'] as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= (string) $user['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label for="reseller_id">Reseller</label>
                    <select id="reseller_id" name="reseller_id" <?= $canManage ? '' : 'disabled' ?>>
                        <option value="">Direct customer</option>
                        <?php foreach ($resellers as $reseller): ?>
                            <option value="<?= (int) $reseller['id'] ?>" <?= (int) ($user['reseller_id'] ?? 0) === (int) $reseller['id'] ? 'selected' : '' ?>><?= e((string) $reseller['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php if ($canManage): ?>
                <div class="card-footer text-right"><button class="btn" type="submit">Save changes</button></div>
            <?php endif; ?>
        </form>

        <div class="card">
            <div class="card-header"><h2>Cards (<?= count($cards) ?>)</h2></div>
            <div class="card-body" style="padding:0">
                <?php if ($cards === []): ?>
                    <p class="small muted" style="padding:18px;margin:0">This user has no cards.</p>
                <?php else: ?>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>Card</th><th>Link</th><th>Status</th><th>Views</th></tr></thead>
                        <tbody>
                            <?php foreach ($cards as $card): ?>
                                <tr>
                                    <td class="small bold"><?= e((string) $card['title']) ?></td>
                                    <td class="small"><a href="<?= e(url('card/' . $card['slug'])) ?>" target="_blank" rel="noopener">/<?= e((string) $card['slug']) ?></a></td>
                                    <td><span class="badge <?= (string) $card['status'] === 'published' ? 'badge-success' : '' ?>"><?= e((string) $card['status']) ?></span></td>
                                    <td class="small"><?= number_format((int) $card['views_count']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Orders</h2></div>
            <div class="card-body" style="padding:0">
                <?php if ($orders === []): ?>
                    <p class="small muted" style="padding:18px;margin:0">No orders.</p>
                <?php else: ?>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>Order</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td class="small"><a href="<?= e(url('admin/orders/' . (int) $order['id'])) ?>"><?= e((string) $order['order_number']) ?></a></td>
                                    <td class="small bold"><?= e(money((float) $order['total'])) ?></td>
                                    <td><span class="badge <?= (string) $order['status'] === 'paid' ? 'badge-success' : '' ?>"><?= e((string) $order['status']) ?></span></td>
                                    <td class="small nowrap"><?= e(date('d M Y', strtotime((string) $order['created_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <div class="card-header"><h3 style="font-size:.98rem">Subscription</h3></div>
            <div class="card-body">
                <div class="bold" style="font-size:1.1rem"><?= e((string) $plan['name']) ?></div>
                <?php if ($subscription !== null && !empty($subscription['ends_at'])): ?>
                    <div class="small muted mb-2">Until <?= e(date('d M Y', strtotime((string) $subscription['ends_at']))) ?></div>
                <?php else: ?>
                    <div class="small muted mb-2">No expiry</div>
                <?php endif; ?>

                <?php if ($canManage): ?>
                    <form method="post" action="<?= e(url('admin/users/' . $userId . '/plan')) ?>" class="mb-2">
                        <?= csrf_field() ?>
                        <div class="field">
                            <label class="tiny" for="plan_id">Assign a plan</label>
                            <select id="plan_id" name="plan_id" required>
                                <?php foreach ($plans as $item): ?>
                                    <option value="<?= (int) $item['id'] ?>" <?= (int) ($plan['id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>>
                                        <?= e((string) $item['name']) ?> (<?= (int) $item['duration_days'] ?>d)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-sm btn-block" type="submit">Assign plan</button>
                    </form>

                    <form method="post" action="<?= e(url('admin/users/' . $userId . '/extend')) ?>">
                        <?= csrf_field() ?>
                        <div class="row" style="gap:6px">
                            <input class="input" type="number" name="days" value="30" min="1" max="3650" style="max-width:100px">
                            <button class="btn btn-sm btn-secondary flex-1" type="submit">Extend by days</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canManage): ?>
            <form method="post" action="<?= e(url('admin/users/' . $userId . '/status')) ?>" class="card">
                <?= csrf_field() ?>
                <div class="card-header"><h3 style="font-size:.98rem">Status</h3></div>
                <div class="card-body">
                    <div class="field">
                        <select name="status">
                            <?php foreach (['active' => 'Active', 'suspended' => 'Suspended', 'pending' => 'Pending'] as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= (string) $user['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="hint">Suspending an account immediately takes its published cards offline.</div>
                    </div>
                    <button class="btn btn-sm btn-block" type="submit">Update status</button>
                </div>
            </form>

            <form method="post" action="<?= e(url('admin/users/' . $userId . '/password')) ?>" class="card" data-confirm="Reset this user's password?">
                <?= csrf_field() ?>
                <div class="card-header"><h3 style="font-size:.98rem">Reset password</h3></div>
                <div class="card-body">
                    <div class="field">
                        <input class="input" type="text" name="password" placeholder="New password" required minlength="8">
                        <div class="hint">Share it securely — the user is signed out of every device.</div>
                    </div>
                    <button class="btn btn-sm btn-secondary btn-block" type="submit">Reset password</button>
                </div>
            </form>

            <?php if (auth()->isSuperAdmin()): ?>
                <form method="post" action="<?= e(url('admin/users/' . $userId . '/delete')) ?>" class="card" style="border-color:var(--danger)"
                      data-confirm="Permanently delete this account and all of its data?">
                    <?= csrf_field() ?>
                    <div class="card-header"><h3 style="font-size:.98rem;color:var(--danger)">Delete account</h3></div>
                    <div class="card-body">
                        <p class="tiny muted">Type the email address to confirm.</p>
                        <input class="input mb-2" type="text" name="confirm_email" placeholder="<?= e((string) $user['email']) ?>" required>
                        <button class="btn btn-danger btn-sm btn-block" type="submit">Delete permanently</button>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <div class="card"><div class="card-body small">
            <div class="row-between" style="padding:4px 0"><span class="muted">Joined</span><strong><?= e(date('d M Y', strtotime((string) $user['created_at']))) ?></strong></div>
            <div class="row-between" style="padding:4px 0"><span class="muted">Last login</span><strong><?= !empty($user['last_login_at']) ? e(date('d M, H:i', strtotime((string) $user['last_login_at']))) : '—' ?></strong></div>
            <div class="row-between" style="padding:4px 0"><span class="muted">Last IP</span><strong class="tiny"><?= e((string) ($user['last_login_ip'] ?? '—')) ?></strong></div>
            <div class="row-between" style="padding:4px 0"><span class="muted">Email verified</span><strong><?= !empty($user['email_verified_at']) ? 'Yes' : 'No' ?></strong></div>
        </div></div>
    </div>
</div>
<?php $__view->stop(); ?>
