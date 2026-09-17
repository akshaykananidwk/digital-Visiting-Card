<?php /** @var array{data:array<int,array<string,mixed>>,total:int,page:int,pages:int,from:int,to:int} $result
 *  @var array<string,mixed> $filters @var array<int,string> $actions */
$__view->extend('layouts.admin');
$query = array_filter(['q' => $filters['search'], 'action' => $filters['action']]);
?>
<?php $__view->start('content'); ?>
<form method="get" action="<?= e(url('admin/logs/audit')) ?>" class="card mb-3">
    <div class="card-body filter-bar">
        <div class="input-group flex-1" style="min-width:220px">
            <span class="addon"><?= icon('search', 16) ?></span>
            <input class="input" type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Action, entity or IP…" data-live-search>
        </div>
        <select name="action" data-auto-submit aria-label="Action">
            <option value="">All actions</option>
            <?php foreach ($actions as $action): ?>
                <option value="<?= e($action) ?>" <?= $filters['action'] === $action ? 'selected' : '' ?>><?= e($action) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="card"><div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Action</th><th>Actor</th><th>Entity</th><th>Context</th><th>IP</th><th>When</th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $entry): ?>
                <tr>
                    <td class="small bold"><?= e((string) $entry['action']) ?></td>
                    <td class="small">
                        <?php if ($entry['user_id'] !== null): ?>
                            <a href="<?= e(url('admin/users/' . (int) $entry['user_id'])) ?>">#<?= (int) $entry['user_id'] ?></a>
                        <?php else: ?>system<?php endif; ?>
                        <div class="tiny muted"><?= e((string) ($entry['actor_role'] ?? '')) ?></div>
                    </td>
                    <td class="small muted"><?= e((string) ($entry['entity_type'] ?? '')) ?><?= $entry['entity_id'] !== null ? ' #' . (int) $entry['entity_id'] : '' ?></td>
                    <td class="tiny muted truncate" style="max-width:260px"><?= e(is_array($entry['context'] ?? null) ? (string) json_encode($entry['context']) : '') ?></td>
                    <td class="tiny muted"><?= e((string) ($entry['ip_address'] ?? '')) ?></td>
                    <td class="small nowrap"><?= e(date('d M, H:i:s', strtotime((string) $entry['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($result['data'] === []): ?><tr><td colspan="6" class="table-empty">No audit entries matched.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></div>
<?= $__view->include('partials.pagination', ['paginator' => $result, 'baseUrl' => url('admin/logs/audit'), 'query' => $query]) ?>
<?php $__view->stop(); ?>
