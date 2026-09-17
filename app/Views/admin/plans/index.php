<?php /** @var array<int,array<string,mixed>> $plans */ $__view->extend('layouts.admin'); ?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm" href="<?= e(url('admin/plans/create')) ?>"><?= icon('plus', 15) ?> New plan</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Plan</th><th>Price</th><th>Duration</th><th>Cards</th><th>Subscribers</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($plans as $plan): ?>
                    <tr>
                        <td>
                            <div class="bold"><?= e((string) $plan['name']) ?><?= (int) $plan['is_featured'] === 1 ? ' <span class="badge badge-brand">Featured</span>' : '' ?></div>
                            <div class="tiny muted"><?= e((string) $plan['slug']) ?></div>
                        </td>
                        <td class="bold"><?= (float) $plan['price'] <= 0 ? 'Free' : e(money((float) $plan['price'])) ?></td>
                        <td class="small"><?= (int) $plan['duration_days'] ?> days</td>
                        <td class="small"><?= (int) $plan['card_limit'] < 0 ? '∞' : (int) $plan['card_limit'] ?></td>
                        <td class="small bold"><?= number_format((int) $plan['active_subscribers']) ?></td>
                        <td><span class="badge <?= (int) $plan['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>"><?= (int) $plan['is_active'] === 1 ? 'active' : 'hidden' ?></span></td>
                        <td class="text-right nowrap">
                            <a class="btn btn-sm btn-secondary" href="<?= e(url('admin/plans/' . (int) $plan['id'] . '/edit')) ?>"><?= icon('edit', 13) ?> Edit</a>
                            <form method="post" action="<?= e(url('admin/plans/' . (int) $plan['id'] . '/delete')) ?>" style="display:inline" data-confirm="Delete this plan?">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-ghost" type="submit"><?= icon('trash', 13) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</div>
<p class="small muted mt-2"><?= icon('info', 13) ?> Changing a plan's limits applies immediately to everyone already subscribed to it.</p>
<?php $__view->stop(); ?>
