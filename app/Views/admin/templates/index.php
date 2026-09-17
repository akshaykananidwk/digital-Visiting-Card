<?php
/** @var array{data:array<int,array<string,mixed>>,total:int,page:int,pages:int,from:int,to:int} $result
 *  @var array<string,mixed> $filters @var array<int,array<string,mixed>> $categories
 *  @var array<string,array<int,string>> $facets @var array<string,int> $counts @var int $catalogue */
$__view->extend('layouts.admin');
$query = array_filter(['q' => $filters['search'], 'category' => $filters['category'] ?: null, 'premium' => $filters['premium'], 'style' => $filters['style'], 'sort' => $filters['sort']]);
?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm btn-secondary" href="<?= e(url('admin/categories')) ?>"><?= icon('folder', 15) ?> Categories</a>
<a class="btn btn-sm" href="<?= e(url('admin/templates/create')) ?>"><?= icon('plus', 15) ?> New template</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="grid grid-4 mb-3">
    <?php foreach ([['Total', $counts['total']], ['Active', $counts['active']], ['Premium', $counts['premium']], ['Featured', $counts['featured']]] as [$label, $value]): ?>
        <div class="stat"><div class="stat-label"><?= e($label) ?></div><div class="stat-value"><?= number_format((int) $value) ?></div></div>
    <?php endforeach; ?>
</div>

<div class="card mb-3">
    <div class="card-body row-between">
        <div>
            <strong class="small">Bulk generator</strong>
            <div class="tiny muted">Build designs from the built-in design-token catalogue across <?= (int) $catalogue ?> industry categories. Existing codes are skipped.</div>
        </div>
        <form method="post" action="<?= e(url_path('admin/templates/generate')) ?>" class="row" style="gap:8px"
              data-confirm="Generate designs now? On a slow shared host this can take a minute.">
            <?= csrf_field() ?>
            <input class="input" type="number" name="per_category" value="24" min="1" max="80" style="max-width:110px" aria-label="Designs per category">
            <button class="btn btn-sm" type="submit"><?= icon('sparkles', 15) ?> Generate</button>
        </form>
    </div>
</div>

<form method="get" action="<?= e(url_path('admin/templates')) ?>" class="card mb-3">
    <div class="card-body filter-bar">
        <div class="input-group flex-1" style="min-width:200px">
            <span class="addon"><?= icon('search', 16) ?></span>
            <input class="input" type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Search templates…" data-live-search>
        </div>
        <select name="category" data-auto-submit aria-label="Category">
            <option value="">All categories</option>
            <?php foreach ($categories as $group): ?>
                <optgroup label="<?= e((string) $group['name']) ?>">
                    <?php foreach ($group['children'] as $child): ?>
                        <option value="<?= (int) $child['id'] ?>" <?= (int) $filters['category'] === (int) $child['id'] ? 'selected' : '' ?>><?= e((string) $child['name']) ?> (<?= (int) $child['template_count'] ?>)</option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <select name="premium" data-auto-submit aria-label="Type">
            <option value="">Free &amp; premium</option>
            <option value="free" <?= $filters['premium'] === 'free' ? 'selected' : '' ?>>Free</option>
            <option value="premium" <?= $filters['premium'] === 'premium' ? 'selected' : '' ?>>Premium</option>
        </select>
        <select name="sort" data-auto-submit aria-label="Sort">
            <option value="featured" <?= $filters['sort'] === 'featured' ? 'selected' : '' ?>>Sort order</option>
            <option value="popular" <?= $filters['sort'] === 'popular' ? 'selected' : '' ?>>Most used</option>
            <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="name" <?= $filters['sort'] === 'name' ? 'selected' : '' ?>>Name</option>
        </select>
    </div>
</form>

<p class="small muted">Showing <?= (int) $result['from'] ?>–<?= (int) $result['to'] ?> of <?= number_format((int) $result['total']) ?></p>

<div class="template-grid">
    <?php foreach ($result['data'] as $template): ?>
        <div class="template-card" style="<?= (int) $template['is_active'] === 0 ? 'opacity:.55' : '' ?>">
            <div class="template-thumb">
                <div class="template-badges">
                    <?php if ((int) $template['is_premium'] === 1): ?><span class="badge badge-warning">Premium</span><?php endif; ?>
                    <?php if ((int) $template['is_active'] === 0): ?><span class="badge badge-danger">Off</span><?php endif; ?>
                </div>
                <iframe src="<?= e(url_path('templates/preview/' . $template['code'])) ?>" title="<?= e((string) $template['name']) ?>" loading="lazy" tabindex="-1" scrolling="no"></iframe>
            </div>
            <div class="template-meta">
                <div class="name truncate"><?= e((string) $template['name']) ?></div>
                <div class="tiny muted mb-1"><?= e((string) $template['code']) ?> · <?= (int) $template['usage_count'] ?> uses</div>
                <div class="row" style="gap:4px">
                    <a class="btn btn-sm btn-secondary flex-1" href="<?= e(url('admin/templates/' . (int) $template['id'] . '/edit')) ?>"><?= icon('edit', 13) ?></a>
                    <form method="post" action="<?= e(url_path('admin/templates/' . (int) $template['id'] . '/toggle')) ?>" style="flex:1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="field" value="is_active">
                        <button class="btn btn-sm btn-secondary btn-block" type="submit" title="Toggle active"><?= icon((int) $template['is_active'] === 1 ? 'eye' : 'x', 13) ?></button>
                    </form>
                    <form method="post" action="<?= e(url_path('admin/templates/' . (int) $template['id'] . '/duplicate')) ?>" style="flex:1">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-secondary btn-block" type="submit" title="Duplicate"><?= icon('copy', 13) ?></button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?= $__view->include('partials.pagination', ['paginator' => $result, 'baseUrl' => url('admin/templates'), 'query' => $query]) ?>
<?php $__view->stop(); ?>
