<?php /** @var array{data:array<int,array<string,mixed>>,total:int,page:int,pages:int,from:int,to:int} $result
 *  @var array<string,mixed> $filters @var array<int,array<string,mixed>> $cards @var int $total */
$__view->extend('layouts.admin');
$query = array_filter(['q' => $filters['search'], 'status' => $filters['status']]);
?>
<?php $__view->start('content'); ?>
<form method="get" action="<?= e(url_path('admin/leads')) ?>" class="card mb-3">
    <div class="card-body filter-bar">
        <div class="input-group flex-1" style="min-width:220px">
            <span class="addon"><?= icon('search', 16) ?></span>
            <input class="input" type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Name, phone or email…" data-live-search>
        </div>
        <select name="status" data-auto-submit aria-label="Status">
            <option value="">All statuses</option>
            <?php foreach (['new', 'read', 'contacted', 'converted', 'spam', 'archived'] as $value): ?>
                <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="badge"><?= number_format((int) $total) ?> total</span>
    </div>
</form>

<div class="card"><div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Lead</th><th>Contact</th><th>Card</th><th>Status</th><th>Received</th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $lead): ?>
                <tr>
                    <td>
                        <div class="bold small"><?= e((string) $lead['name']) ?></div>
                        <div class="tiny muted truncate" style="max-width:260px"><?= e(mb_substr((string) ($lead['message'] ?? ''), 0, 80)) ?></div>
                    </td>
                    <td class="small">
                        <?= e((string) ($lead['phone'] ?? '')) ?>
                        <?php if (!empty($lead['email'])): ?><div class="tiny muted"><?= e((string) $lead['email']) ?></div><?php endif; ?>
                    </td>
                    <td class="small">
                        <?php $card = $cards[(int) $lead['card_id']] ?? null; ?>
                        <?php if ($card !== null): ?>
                            <a href="<?= e(url('card/' . $card['slug'])) ?>" target="_blank" rel="noopener"><?= e((string) $card['title']) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><span class="badge <?= (string) $lead['status'] === 'new' ? 'badge-danger' : '' ?>"><?= e((string) $lead['status']) ?></span></td>
                    <td class="small nowrap"><?= e(date('d M, H:i', strtotime((string) $lead['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($result['data'] === []): ?><tr><td colspan="5" class="table-empty">No leads yet.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></div>
<?= $__view->include('partials.pagination', ['paginator' => $result, 'baseUrl' => url('admin/leads'), 'query' => $query]) ?>
<p class="tiny muted mt-2"><?= icon('shield', 13) ?> Lead details belong to the card owner. They are shown here for support purposes only and every view is subject to your audit policy.</p>
<?php $__view->stop(); ?>
