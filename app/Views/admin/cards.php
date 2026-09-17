<?php
/** @var array{data:array<int,array<string,mixed>>,total:int,page:int,pages:int,from:int,to:int} $result
 *  @var array<string,mixed> $filters @var array<string,int> $stats */
$__view->extend('layouts.admin');
$query = array_filter(['q' => $filters['search'], 'status' => $filters['status']]);
?>
<?php $__view->start('content'); ?>
<div class="grid grid-4 mb-3">
    <?php foreach ([['Total', $stats['total']], ['Published', $stats['published']], ['Draft', $stats['draft']], ['Expired', $stats['expired']]] as [$label, $value]): ?>
        <div class="stat"><div class="stat-label"><?= e($label) ?></div><div class="stat-value"><?= number_format((int) $value) ?></div></div>
    <?php endforeach; ?>
</div>

<form method="get" action="<?= e(url_path('admin/cards')) ?>" class="card mb-3">
    <div class="card-body filter-bar">
        <div class="input-group flex-1" style="min-width:220px">
            <span class="addon"><?= icon('search', 16) ?></span>
            <input class="input" type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Title, link, business or phone…" data-live-search>
        </div>
        <select name="status" data-auto-submit aria-label="Status">
            <option value="">All statuses</option>
            <?php foreach (['published' => 'Published', 'draft' => 'Draft', 'suspended' => 'Suspended', 'expired' => 'Expired'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Card</th><th>Owner</th><th>Status</th><th>Views</th><th>Leads</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($result['data'] as $card): ?>
                    <tr>
                        <td>
                            <div class="bold"><?= e((string) $card['title']) ?></div>
                            <a class="tiny" href="<?= e(url('card/' . $card['slug'])) ?>" target="_blank" rel="noopener">/<?= e((string) $card['slug']) ?></a>
                        </td>
                        <td class="small">
                            <?php if (!empty($card['owner'])): ?>
                                <a href="<?= e(url('admin/users/' . (int) $card['user_id'])) ?>"><?= e((string) $card['owner']['name']) ?></a>
                                <div class="tiny muted"><?= e((string) $card['owner']['email']) ?></div>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <?php $badge = match ((string) $card['status']) { 'published' => 'badge-success', 'suspended' => 'badge-danger', 'expired' => 'badge-warning', default => '' }; ?>
                            <span class="badge <?= $badge ?>"><?= e((string) $card['status']) ?></span>
                        </td>
                        <td class="small"><?= number_format((int) $card['views_count']) ?></td>
                        <td class="small"><?= number_format((int) $card['leads_count']) ?></td>
                        <td class="nowrap">
                            <form method="post" action="<?= e(url_path('admin/cards/' . (int) $card['id'] . '/status')) ?>" style="display:inline-flex;gap:6px">
                                <?= csrf_field() ?>
                                <select name="status" style="min-height:34px;font-size:.78rem;width:auto">
                                    <?php foreach (['published', 'draft', 'suspended', 'expired'] as $value): ?>
                                        <option value="<?= e($value) ?>" <?= (string) $card['status'] === $value ? 'selected' : '' ?>><?= e($value) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-sm btn-secondary" type="submit">Set</button>
                            </form>
                            <form method="post" action="<?= e(url_path('admin/cards/' . (int) $card['id'] . '/delete')) ?>" style="display:inline" data-confirm="Delete this card?">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-ghost" type="submit"><?= icon('trash', 14) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($result['data'] === []): ?>
                    <tr><td colspan="6" class="table-empty">No cards matched that search.</td></tr>
                <?php endif; ?>
            </tbody>
        </table></div>
    </div>
</div>
<?= $__view->include('partials.pagination', ['paginator' => $result, 'baseUrl' => url('admin/cards'), 'query' => $query]) ?>
<?php $__view->stop(); ?>
