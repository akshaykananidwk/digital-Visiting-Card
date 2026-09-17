<?php
/** @var array<string,mixed>|null $template @var array<int,array<string,mixed>> $categories
 *  @var array<string,string> $layouts @var array<int,string> $fonts @var array<int,string> $effects
 *  @var array<string,mixed> $defaults */
$__view->extend('layouts.admin');
$config = is_array($template['config'] ?? null) ? $template['config'] : $defaults;
$palette = array_merge($defaults['palette'], (array) ($config['palette'] ?? []));
$fontsUsed = array_merge($defaults['fonts'], (array) ($config['fonts'] ?? []));
$activeEffects = (array) ($config['effects'] ?? []);
$action = $template === null ? url_path('admin/templates') : url_path('admin/templates/' . (int) $template['id']);
?>
<?php $__view->start('content'); ?>
<div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,320px);align-items:start">
    <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="card">
        <?= csrf_field() ?>
        <div class="card-header"><h2><?= $template === null ? 'New template' : 'Edit template' ?></h2></div>
        <div class="card-body">
            <div class="grid grid-2" style="gap:0 16px">
                <div class="field">
                    <label class="required" for="name">Name</label>
                    <input class="input" type="text" id="name" name="name" value="<?= e((string) ($template['name'] ?? '')) ?>" required>
                </div>
                <div class="field">
                    <label class="required" for="code">Code</label>
                    <input class="input" type="text" id="code" name="code" value="<?= e((string) ($template['code'] ?? '')) ?>" required pattern="[A-Za-z0-9_\-]+">
                    <div class="hint">Unique identifier used in preview URLs.</div>
                </div>
            </div>

            <div class="grid grid-3" style="gap:0 12px">
                <div class="field">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id">
                        <option value="">None</option>
                        <?php foreach ($categories as $group): ?>
                            <optgroup label="<?= e((string) $group['name']) ?>">
                                <?php foreach ($group['children'] as $child): ?>
                                    <option value="<?= (int) $child['id'] ?>" <?= (int) ($template['category_id'] ?? 0) === (int) $child['id'] ? 'selected' : '' ?>><?= e((string) $child['name']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="required" for="layout">Layout</label>
                    <select id="layout" name="layout" required>
                        <?php foreach ($layouts as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= (string) ($template['layout'] ?? 'classic') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="required" for="theme_mode">Mode</label>
                    <select id="theme_mode" name="theme_mode" required>
                        <?php foreach (['light' => 'Light', 'dark' => 'Dark'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= (string) ($template['theme_mode'] ?? 'light') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-3" style="gap:0 12px">
                <div class="field"><label for="style">Style</label><input class="input" type="text" id="style" name="style" value="<?= e((string) ($template['style'] ?? 'modern')) ?>"></div>
                <div class="field"><label for="industry">Industry</label><input class="input" type="text" id="industry" name="industry" value="<?= e((string) ($template['industry'] ?? '')) ?>"></div>
                <div class="field"><label for="color_family">Colour family</label><input class="input" type="text" id="color_family" name="color_family" value="<?= e((string) ($template['color_family'] ?? '')) ?>"></div>
            </div>

            <div class="field">
                <label for="tags">Search tags</label>
                <input class="input" type="text" id="tags" name="tags" value="<?= e((string) ($template['tags'] ?? '')) ?>">
                <div class="hint">Space-separated keywords customers can search for.</div>
            </div>

            <fieldset>
                <legend>Palette</legend>
                <div class="grid grid-4" style="gap:0 10px">
                    <?php foreach (['primary' => 'Primary', 'secondary' => 'Secondary', 'accent' => 'Accent', 'bg' => 'Background', 'surface' => 'Surface', 'text' => 'Text', 'muted' => 'Muted', 'border' => 'Border'] as $key => $label): ?>
                        <div class="field">
                            <label class="tiny" for="color_<?= e($key) ?>"><?= e($label) ?></label>
                            <input class="input" type="color" id="color_<?= e($key) ?>" name="color_<?= e($key) ?>"
                                   value="<?= e(str_starts_with((string) $palette[$key], '#') ? (string) $palette[$key] : '#ffffff') ?>" style="height:40px;padding:4px">
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset>
                <legend>Typography &amp; shape</legend>
                <div class="grid grid-2" style="gap:0 12px">
                    <div class="field">
                        <label for="font_heading">Heading font</label>
                        <select id="font_heading" name="font_heading">
                            <?php foreach ($fonts as $font): ?>
                                <option value="<?= e($font) ?>" <?= (string) $fontsUsed['heading'] === $font ? 'selected' : '' ?>><?= e($font) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="font_body">Body font</label>
                        <select id="font_body" name="font_body">
                            <?php foreach ($fonts as $font): ?>
                                <option value="<?= e($font) ?>" <?= (string) $fontsUsed['body'] === $font ? 'selected' : '' ?>><?= e($font) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-4" style="gap:0 10px">
                    <div class="field"><label class="tiny" for="radius">Radius (px)</label><input class="input" type="number" id="radius" name="radius" value="<?= (int) ($config['radius'] ?? 22) ?>" min="0" max="40"></div>
                    <div class="field">
                        <label class="tiny" for="buttons">Buttons</label>
                        <select id="buttons" name="buttons">
                            <?php foreach (['pill', 'rounded', 'square'] as $value): ?>
                                <option value="<?= e($value) ?>" <?= (string) ($config['buttons'] ?? 'pill') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="tiny" for="avatar">Avatar</label>
                        <select id="avatar" name="avatar">
                            <?php foreach (['circle', 'squircle', 'square', 'hex'] as $value): ?>
                                <option value="<?= e($value) ?>" <?= (string) ($config['avatar'] ?? 'circle') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="tiny" for="density">Density</label>
                        <select id="density" name="density">
                            <?php foreach (['comfortable', 'compact', 'roomy'] as $value): ?>
                                <option value="<?= e($value) ?>" <?= (string) ($config['density'] ?? 'comfortable') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-4" style="gap:0 10px">
                    <div class="field">
                        <label class="tiny" for="cover">Cover</label>
                        <select id="cover" name="cover">
                            <?php foreach (['gradient', 'mesh', 'aurora', 'solid', 'pattern', 'image'] as $value): ?>
                                <option value="<?= e($value) ?>" <?= (string) ($config['cover'] ?? 'gradient') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="tiny" for="pattern">Pattern</label>
                        <select id="pattern" name="pattern">
                            <?php foreach (['none', 'dots', 'grid', 'waves', 'rings', 'diagonal'] as $value): ?>
                                <option value="<?= e($value) ?>" <?= (string) ($config['pattern'] ?? 'none') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label class="tiny" for="cover_height">Cover height</label><input class="input" type="number" id="cover_height" name="cover_height" value="<?= (int) ($config['cover_height'] ?? 190) ?>" min="0" max="420"></div>
                    <div class="field">
                        <label class="tiny" for="shadow">Shadow</label>
                        <select id="shadow" name="shadow">
                            <?php foreach (['none', 'soft', 'strong'] as $value): ?>
                                <option value="<?= e($value) ?>" <?= (string) ($config['shadow'] ?? 'soft') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Effects</legend>
                <div class="row wrap" style="gap:14px">
                    <?php foreach ($effects as $effect): ?>
                        <label class="checkbox" style="min-width:140px">
                            <input type="checkbox" name="effects[]" value="<?= e($effect) ?>" <?= in_array($effect, $activeEffects, true) ? 'checked' : '' ?>>
                            <span class="small"><?= e($effect) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="tiny muted" style="margin:8px 0 0">Effects are automatically reduced on low-powered devices and when the visitor prefers reduced motion.</p>
            </fieldset>

            <fieldset>
                <legend>Availability</legend>
                <div class="row wrap" style="gap:18px">
                    <?php foreach (['is_active' => 'Active', 'is_premium' => 'Premium', 'is_featured' => 'Featured', 'is_popular' => 'Popular'] as $flag => $label): ?>
                        <label class="switch">
                            <input type="checkbox" name="<?= e($flag) ?>" value="1" <?= (int) ($template[$flag] ?? ($flag === 'is_active' ? 1 : 0)) === 1 ? 'checked' : '' ?>>
                            <span class="track"></span><span class="small bold"><?= e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="field mt-2">
                    <label for="preview_image">Custom preview image (optional)</label>
                    <input class="input" type="file" id="preview_image" name="preview_image" accept="image/jpeg,image/png,image/webp">
                    <div class="hint">Leave empty to use the live preview, which always matches the real design.</div>
                </div>
            </fieldset>
        </div>
        <div class="card-footer row-between">
            <a class="btn btn-ghost" href="<?= e(url('admin/templates')) ?>">Back</a>
            <div class="row" style="gap:8px">
                <?php if ($template !== null): ?>
                    <button class="btn btn-ghost" type="submit" formaction="<?= e(url('admin/templates/' . (int) $template['id'] . '/delete')) ?>"
                            formnovalidate onclick="return confirm('Delete this template?')" style="color:var(--danger)">Delete</button>
                <?php endif; ?>
                <button class="btn" type="submit">Save template</button>
            </div>
        </div>
    </form>

    <aside class="editor-preview">
        <div class="card">
            <div class="card-header"><h3 style="font-size:.95rem">Preview</h3></div>
            <div class="card-body">
                <?php if ($template !== null): ?>
                    <div class="phone-frame"><iframe src="<?= e(url_path('templates/preview/' . $template['code'])) ?>" title="Preview"></iframe></div>
                    <p class="tiny muted text-center mt-2">Save to see your changes.</p>
                <?php else: ?>
                    <p class="small muted mb-0">Save the template to see a live preview.</p>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>
<?php $__view->stop(); ?>
