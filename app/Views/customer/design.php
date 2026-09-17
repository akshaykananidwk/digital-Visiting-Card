<?php
/**
 * @var array<string,mixed> $card
 * @var array{data:array<int,array<string,mixed>>,total:int,page:int,pages:int,from:int,to:int} $result
 * @var array<string,mixed> $filters
 * @var array<int,array<string,mixed>> $categories
 * @var array<string,array<int,string>> $facets
 * @var array<string,mixed>|null $current
 * @var bool $canPremium
 * @var array<string,mixed> $overrides
 * @var array<int,string> $fonts
 */
$__view->extend('layouts.panel');
$cardId = (int) $card['id'];
$palette = is_array($overrides['palette'] ?? null) ? $overrides['palette'] : [];
$overrideFonts = is_array($overrides['fonts'] ?? null) ? $overrides['fonts'] : [];
$query = array_filter(['q' => $filters['search'], 'category' => $filters['category'] ?: null, 'premium' => $filters['premium'], 'style' => $filters['style'], 'color' => $filters['color'], 'mode' => $filters['mode'], 'sort' => $filters['sort']]);
?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm btn-secondary" href="<?= e(url('cards/' . $cardId . '/editor')) ?>"><?= icon('arrow-left', 15) ?> Back to editor</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="alert alert-info">
    <?= icon('info', 18) ?>
    <div>Changing design never deletes your content — your details, services, products and gallery move to the new design automatically.</div>
</div>

<div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,320px);align-items:start">
    <div>
        <form method="get" action="<?= e(url('cards/' . $cardId . '/design')) ?>" class="card mb-3">
            <div class="card-body">
                <div class="filter-bar">
                    <div class="input-group flex-1" style="min-width:200px">
                        <span class="addon"><?= icon('search', 16) ?></span>
                        <input class="input" type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Search designs…" data-live-search>
                    </div>
                    <select name="category" data-auto-submit aria-label="Category">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $group): ?>
                            <optgroup label="<?= e((string) $group['name']) ?>">
                                <?php foreach ($group['children'] as $child): ?>
                                    <option value="<?= (int) $child['id'] ?>" <?= (int) $filters['category'] === (int) $child['id'] ? 'selected' : '' ?>><?= e((string) $child['name']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <select name="premium" data-auto-submit aria-label="Availability">
                        <option value="">Free &amp; premium</option>
                        <option value="free" <?= $filters['premium'] === 'free' ? 'selected' : '' ?>>Free</option>
                        <option value="premium" <?= $filters['premium'] === 'premium' ? 'selected' : '' ?>>Premium</option>
                    </select>
                    <select name="mode" data-auto-submit aria-label="Theme">
                        <option value="">Light &amp; dark</option>
                        <option value="light" <?= $filters['mode'] === 'light' ? 'selected' : '' ?>>Light</option>
                        <option value="dark" <?= $filters['mode'] === 'dark' ? 'selected' : '' ?>>Dark</option>
                    </select>
                </div>
            </div>
        </form>

        <p class="small muted">Showing <?= (int) $result['from'] ?>–<?= (int) $result['to'] ?> of <?= number_format((int) $result['total']) ?> designs</p>

        <div class="template-grid">
            <?php foreach ($result['data'] as $template): ?>
                <?php $isCurrent = (int) ($card['template_id'] ?? 0) === (int) $template['id']; ?>
                <?php $locked = (int) $template['is_premium'] === 1 && !$canPremium; ?>
                <div class="template-card <?= $isCurrent ? 'selected' : '' ?>">
                    <div class="template-thumb">
                        <div class="template-badges">
                            <?php if ($isCurrent): ?><span class="badge badge-success">In use</span><?php endif; ?>
                            <?php if ((int) $template['is_premium'] === 1): ?><span class="badge badge-warning">Premium</span><?php endif; ?>
                        </div>
                        <iframe src="<?= e(url('templates/preview/' . $template['code'])) ?>" title="<?= e((string) $template['name']) ?>" loading="lazy" tabindex="-1" scrolling="no"></iframe>
                    </div>
                    <div class="template-meta">
                        <div class="name truncate"><?= e((string) $template['name']) ?></div>
                        <div class="tiny muted mb-1"><?= e((string) $template['industry']) ?></div>
                        <?php if ($isCurrent): ?>
                            <button class="btn btn-sm btn-block btn-secondary" type="button" disabled>Current design</button>
                        <?php elseif ($locked): ?>
                            <a class="btn btn-sm btn-block btn-secondary" href="<?= e(url('billing')) ?>"><?= icon('lock', 13) ?> Upgrade</a>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('cards/' . $cardId . '/design')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="template_id" value="<?= (int) $template['id'] ?>">
                                <button class="btn btn-sm btn-block" type="submit">Use this design</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?= $__view->include('partials.pagination', ['paginator' => $result, 'baseUrl' => url('cards/' . $cardId . '/design'), 'query' => $query]) ?>
    </div>

    <aside class="editor-preview">
        <div class="card mb-3">
            <div class="card-header"><h3 style="font-size:.95rem">Preview</h3></div>
            <div class="card-body">
                <div class="phone-frame">
                    <iframe src="<?= e(url('cards/' . $cardId . '/preview')) ?>" title="Current design"></iframe>
                </div>
                <?php if ($current !== null): ?>
                    <p class="tiny muted text-center mt-2">Current: <?= e((string) $current['name']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <form method="post" action="<?= e(url('cards/' . $cardId . '/design/theme')) ?>" class="card">
            <?= csrf_field() ?>
            <div class="card-header"><h3 style="font-size:.95rem">Customise</h3></div>
            <div class="card-body">
                <p class="tiny muted">Override the design's colours and typography for this card only.</p>

                <div class="grid grid-2" style="gap:0 12px">
                    <?php foreach (['primary' => 'Primary', 'accent' => 'Accent', 'bg' => 'Background', 'text' => 'Text'] as $key => $label): ?>
                        <div class="field">
                            <label for="color_<?= e($key) ?>" class="tiny"><?= e($label) ?></label>
                            <input class="input" type="color" id="color_<?= e($key) ?>" name="color_<?= e($key) ?>"
                                   value="<?= e((string) ($palette[$key] ?? '#4f46e5')) ?>" style="height:40px;padding:4px">
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="field">
                    <label for="font_heading" class="tiny">Heading font</label>
                    <select id="font_heading" name="font_heading">
                        <option value="">Use design default</option>
                        <?php foreach ($fonts as $font): ?>
                            <option value="<?= e($font) ?>" <?= ($overrideFonts['heading'] ?? '') === $font ? 'selected' : '' ?>><?= e($font) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="font_body" class="tiny">Body font</label>
                    <select id="font_body" name="font_body">
                        <option value="">Use design default</option>
                        <?php foreach ($fonts as $font): ?>
                            <option value="<?= e($font) ?>" <?= ($overrideFonts['body'] ?? '') === $font ? 'selected' : '' ?>><?= e($font) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="buttons" class="tiny">Button shape</label>
                    <select id="buttons" name="buttons">
                        <option value="">Design default</option>
                        <?php foreach (['pill' => 'Pill', 'rounded' => 'Rounded', 'square' => 'Square'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($overrides['buttons'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="mode" class="tiny">Colour mode</label>
                    <select id="mode" name="mode">
                        <option value="">Design default</option>
                        <option value="light" <?= ($overrides['mode'] ?? '') === 'light' ? 'selected' : '' ?>>Light</option>
                        <option value="dark" <?= ($overrides['mode'] ?? '') === 'dark' ? 'selected' : '' ?>>Dark</option>
                    </select>
                </div>
            </div>
            <div class="card-footer row" style="gap:8px">
                <button class="btn btn-sm flex-1" type="submit">Apply</button>
                <button class="btn btn-sm btn-ghost" type="submit" formaction="<?= e(url('cards/' . $cardId . '/design/reset')) ?>">Reset</button>
            </div>
        </form>
    </aside>
</div>
<?php $__view->stop(); ?>
