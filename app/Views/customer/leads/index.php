<?php
/** @var array{data:array<int,array<string,mixed>>,total:int,page:int,pages:int,from:int,to:int} $result
 *  @var array<string,mixed> $filters @var array<int,array<string,mixed>> $cards @var int $unread */
$__view->extend('layouts.panel');
$query = array_filter(['q' => $filters['search'], 'status' => $filters['status'], 'card_id' => $filters['card_id'] ?: null]);
?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm btn-secondary" href="<?= e(url('leads/export/csv?' . http_build_query($query))) ?>"><?= icon('download', 15) ?> Export CSV</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<form method="get" action="<?= e(url_path('leads')) ?>" class="card mb-3">
    <div class="card-body filter-bar">
        <div class="input-group flex-1" style="min-width:220px">
            <span class="addon"><?= icon('search', 16) ?></span>
            <input class="input" type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Search name, phone or email…" data-live-search>
        </div>
        <select name="status" data-auto-submit aria-label="Status">
            <option value="">All statuses</option>
            <?php foreach (['new' => 'New', 'read' => 'Read', 'contacted' => 'Contacted', 'converted' => 'Converted', 'spam' => 'Spam', 'archived' => 'Archived'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="card_id" data-auto-submit aria-label="Card">
            <option value="">All cards</option>
            <?php foreach ($cards as $card): ?>
                <option value="<?= (int) $card['id'] ?>" <?= (int) $filters['card_id'] === (int) $card['id'] ? 'selected' : '' ?>><?= e((string) $card['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if ($result['data'] === []): ?>
    <div class="card"><div class="empty-state">
        <div class="icon"><?= icon('inbox', 28) ?></div>
        <h3>No leads yet</h3>
        <p class="muted">Enquiries sent from your published cards will appear here.</p>
    </div></div>
<?php else: ?>
    <div class="card">
        <div class="card-body" style="padding:0">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Name</th><th>Contact</th><th>Message</th><th>Status</th><th>Received</th></tr></thead>
                    <tbody>
                        <?php foreach ($result['data'] as $lead): ?>
                            <tr onclick="location.href='<?= e(url('leads/' . (int) $lead['id'])) ?>'" style="cursor:pointer">
                                <td class="bold"><?= e((string) $lead['name']) ?></td>
                                <td class="small">
                                    <?php if (!empty($lead['phone'])): ?><div><?= e((string) $lead['phone']) ?></div><?php endif; ?>
                                    <?php if (!empty($lead['email'])): ?><div class="muted"><?= e((string) $lead['email']) ?></div><?php endif; ?>
                                </td>
                                <td class="small muted truncate" style="max-width:280px"><?= e(mb_substr((string) ($lead['message'] ?? ''), 0, 90)) ?></td>
                                <td>
                                    <?php
                                    $badge = match ((string) $lead['status']) {
                                        'new' => 'badge-danger', 'converted' => 'badge-success',
                                        'contacted' => 'badge-info', 'spam' => 'badge-warning', default => '',
                                    };
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= e((string) $lead['status']) ?></span>
                                </td>
                                <td class="small nowrap"><?= e(date('d M, H:i', strtotime((string) $lead['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?= $__view->include('partials.pagination', ['paginator' => $result, 'baseUrl' => url('leads'), 'query' => $query]) ?>
<?php endif; ?>
<?php $__view->stop(); ?>
