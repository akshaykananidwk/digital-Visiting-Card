<?php /** @var array{data:array<int,array<string,mixed>>,total:int,page:int,pages:int,from:int,to:int} $result
 *  @var array<string,mixed> $filters @var array<int,array<string,mixed>> $owners */
$__view->extend('layouts.panel');
$query = array_filter(['q' => $filters['search'], 'status' => $filters['status']]);
?>
<?php $__view->start('content'); ?>
<form method="get" action="<?= e(url_path('reseller/cards')) ?>" class="card mb-3">
    <div class="card-body filter-bar">
        <div class="input-group flex-1" style="min-width:220px">
            <span class="addon"><?= icon('search', 16) ?></span>
            <input class="input" type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Card title, link or business…" data-live-search>
        </div>
        <select name="status" data-auto-submit aria-label="Status">
            <option value="">All statuses</option>
            <?php foreach (['published', 'draft', 'suspended', 'expired'] as $value): ?>
                <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="card"><div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Card</th><th>Customer</th><th>Status</th><th>Views</th><th>Leads</th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $card): ?>
                <tr>
                    <td>
                        <div class="bold small"><?= e((string) $card['title']) ?></div>
                        <a class="tiny" href="<?= e(url('card/' . $card['slug'])) ?>" target="_blank" rel="noopener">/<?= e((string) $card['slug']) ?></a>
                    </td>
                    <td class="small">
                        <?php $owner = $owners[(int) $card['user_id']] ?? null; ?>
                        <?php if ($owner !== null): ?>
                            <a href="<?= e(url('reseller/customers/' . (int) $card['user_id'])) ?>"><?= e((string) $owner['name']) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><span class="badge <?= (string) $card['status'] === 'published' ? 'badge-success' : '' ?>"><?= e((string) $card['status']) ?></span></td>
                    <td class="small"><?= number_format((int) $card['views_count']) ?></td>
                    <td class="small"><?= number_format((int) $card['leads_count']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($result['data'] === []): ?><tr><td colspan="5" class="table-empty">No cards yet.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></div>
<?= $__view->include('partials.pagination', ['paginator' => $result, 'baseUrl' => url('reseller/cards'), 'query' => $query]) ?>
<?php $__view->stop(); ?>
