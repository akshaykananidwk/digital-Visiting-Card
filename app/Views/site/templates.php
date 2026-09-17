<?php
/**
 * Public design marketplace.
 *
 * @var array{data:array<int,array<string,mixed>>,total:int,page:int,pages:int,from:int,to:int} $result
 * @var array<string,mixed> $filters
 * @var array<int,array<string,mixed>> $categories
 * @var array<string,array<int,string>> $facets
 */
$__view->extend('layouts.public');
$query = array_filter([
    'q' => $filters['search'], 'category' => $filters['category'] ?: null, 'premium' => $filters['premium'],
    'style' => $filters['style'], 'layout' => $filters['layout'], 'color' => $filters['color'],
    'mode' => $filters['mode'], 'sort' => $filters['sort'],
]);
?>
<?php $__view->start('content'); ?>
<div class="container section" style="padding-top:36px">
    <div class="text-center mb-4">
        <span class="eyebrow"><?= number_format((int) $total) ?> designs</span>
        <h1>Find a design for your business</h1>
        <p class="lead" style="margin-inline:auto">Every design is fully responsive and works with your content — switch any time without losing data.</p>
    </div>

    <form method="get" action="<?= e(url_path('templates')) ?>" class="card mb-3">
        <div class="card-body">
            <div class="filter-bar">
                <div class="input-group flex-1" style="min-width:230px">
                    <span class="addon"><?= icon('search', 16) ?></span>
                    <input class="input" type="search" name="q" value="<?= e((string) $filters['search']) ?>"
                           placeholder="Search: CCTV, hotel, doctor, Krishna, 3D, dark…" data-live-search>
                </div>

                <select name="category" data-auto-submit aria-label="Category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $group): ?>
                        <optgroup label="<?= e((string) $group['name']) ?>">
                            <?php foreach ($group['children'] as $child): ?>
                                <option value="<?= (int) $child['id'] ?>" <?= (int) $filters['category'] === (int) $child['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $child['name']) ?> (<?= (int) $child['template_count'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>

                <select name="premium" data-auto-submit aria-label="Availability">
                    <option value="">Free &amp; premium</option>
                    <option value="free" <?= $filters['premium'] === 'free' ? 'selected' : '' ?>>Free only</option>
                    <option value="premium" <?= $filters['premium'] === 'premium' ? 'selected' : '' ?>>Premium only</option>
                </select>

                <select name="style" data-auto-submit aria-label="Style">
                    <option value="">Any style</option>
                    <?php foreach ($facets['style'] ?? [] as $style): ?>
                        <option value="<?= e($style) ?>" <?= $filters['style'] === $style ? 'selected' : '' ?>><?= e(ucfirst($style)) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="color" data-auto-submit aria-label="Colour">
                    <option value="">Any colour</option>
                    <?php foreach ($facets['color_family'] ?? [] as $color): ?>
                        <option value="<?= e($color) ?>" <?= $filters['color'] === $color ? 'selected' : '' ?>><?= e(ucfirst($color)) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="mode" data-auto-submit aria-label="Theme">
                    <option value="">Light &amp; dark</option>
                    <option value="light" <?= $filters['mode'] === 'light' ? 'selected' : '' ?>>Light</option>
                    <option value="dark" <?= $filters['mode'] === 'dark' ? 'selected' : '' ?>>Dark</option>
                </select>

                <select name="sort" data-auto-submit aria-label="Sort">
                    <option value="featured" <?= $filters['sort'] === 'featured' ? 'selected' : '' ?>>Featured</option>
                    <option value="popular" <?= $filters['sort'] === 'popular' ? 'selected' : '' ?>>Most used</option>
                    <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Newest</option>
                    <option value="name" <?= $filters['sort'] === 'name' ? 'selected' : '' ?>>Name</option>
                </select>

                <noscript><button class="btn btn-sm" type="submit">Apply</button></noscript>
                <?php if ($query !== []): ?>
                    <a class="chip" href="<?= e(url('templates')) ?>"><?= icon('x', 13) ?> Clear</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <?php if ($result['data'] === []): ?>
        <div class="empty-state">
            <div class="icon"><?= icon('search', 28) ?></div>
            <h3>No designs matched that search</h3>
            <p class="muted">Try a different keyword or clear the filters.</p>
            <a class="btn btn-secondary" href="<?= e(url('templates')) ?>">Show all designs</a>
        </div>
    <?php else: ?>
        <p class="small muted mb-2">Showing <?= (int) $result['from'] ?>–<?= (int) $result['to'] ?> of <?= number_format((int) $result['total']) ?> designs</p>
        <div class="template-grid">
            <?php foreach ($result['data'] as $template): ?>
                <a class="template-card" href="<?= e(url('templates/' . $template['code'])) ?>">
                    <div class="template-thumb">
                        <div class="template-badges">
                            <?php if ((int) $template['is_premium'] === 1): ?><span class="badge badge-warning">Premium</span><?php else: ?><span class="badge badge-success">Free</span><?php endif; ?>
                        </div>
                        <iframe src="<?= e(url_path('templates/preview/' . $template['code'])) ?>" title="<?= e((string) $template['name']) ?>" loading="lazy" tabindex="-1" scrolling="no"></iframe>
                    </div>
                    <div class="template-meta">
                        <div class="name truncate"><?= e((string) $template['name']) ?></div>
                        <div class="tiny muted"><?= e((string) $template['industry']) ?> · <?= e((string) $template['style']) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?= $__view->include('partials.pagination', ['paginator' => $result, 'baseUrl' => url('templates'), 'query' => $query]) ?>
    <?php endif; ?>
</div>
<?php $__view->stop(); ?>
