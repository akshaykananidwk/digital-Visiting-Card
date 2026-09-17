<?php /** @var array<int,array<string,mixed>> $tree @var array<int,array<string,mixed>> $flat */ $__view->extend('layouts.admin'); ?>
<?php $__view->start('topbar'); ?>
<button class="btn btn-sm" type="button" data-modal-open="category-modal" data-modal-action="<?= e(url('admin/categories')) ?>"
        data-modal-fill='{"name":"","icon":"tag","description":"","sort_order":0}'><?= icon('plus', 15) ?> New category</button>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="grid grid-2">
    <?php foreach ($tree as $group): ?>
        <div class="card">
            <div class="card-header">
                <h3 style="font-size:1rem"><?= e((string) $group['name']) ?></h3>
                <span class="badge"><?= count($group['children']) ?> sub-categories</span>
            </div>
            <div class="card-body" style="padding:0">
                <table class="table">
                    <tbody>
                        <?php foreach ($group['children'] as $child): ?>
                            <tr>
                                <td>
                                    <div class="bold small"><?= e((string) $child['name']) ?></div>
                                    <div class="tiny muted"><?= e((string) $child['slug']) ?> · <?= (int) $child['template_count'] ?> templates</div>
                                </td>
                                <td class="text-right nowrap">
                                    <?php if ((int) $child['is_active'] !== 1): ?><span class="badge badge-danger">Off</span><?php endif; ?>
                                    <button class="btn btn-sm btn-secondary" type="button" data-modal-open="category-modal"
                                            data-modal-action="<?= e(url('admin/categories/' . (int) $child['id'])) ?>"
                                            data-modal-fill='<?= e(json_encode([
                                                'name' => $child['name'], 'icon' => $child['icon'],
                                                'description' => $child['description'], 'sort_order' => $child['sort_order'],
                                                'is_active' => (int) $child['is_active'] === 1,
                                            ])) ?>'><?= icon('edit', 13) ?></button>
                                    <form method="post" action="<?= e(url_path('admin/categories/' . (int) $child['id'] . '/delete')) ?>" style="display:inline" data-confirm="Delete this category? Templates are kept but lose their category.">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-ghost" type="submit"><?= icon('trash', 13) ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($group['children'] === []): ?>
                            <tr><td class="small muted" style="padding:16px">No sub-categories.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="modal-backdrop" id="category-modal">
    <div class="modal" role="dialog" aria-label="Category">
        <form method="post" action="<?= e(url_path('admin/categories')) ?>">
            <?= csrf_field() ?>
            <div class="modal-head"><h3>Category</h3><button class="btn btn-ghost btn-icon" type="button" data-modal-close><?= icon('x', 18) ?></button></div>
            <div class="modal-body">
                <div class="field"><label class="required" for="c_name">Name</label><input class="input" type="text" id="c_name" name="name" required maxlength="120"></div>
                <div class="field">
                    <label for="c_parent">Parent group</label>
                    <select id="c_parent" name="parent_id">
                        <option value="">Top level</option>
                        <?php foreach ($flat as $row): ?>
                            <?php if ($row['parent_id'] === null): ?>
                                <option value="<?= (int) $row['id'] ?>"><?= e((string) $row['name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-2" style="gap:0 12px">
                    <div class="field"><label for="c_icon">Icon</label><input class="input" type="text" id="c_icon" name="icon" value="tag" maxlength="60"></div>
                    <div class="field"><label for="c_sort">Sort order</label><input class="input" type="number" id="c_sort" name="sort_order" value="0" min="0" max="1000"></div>
                </div>
                <div class="field"><label for="c_description">Description</label><input class="input" type="text" id="c_description" name="description" maxlength="255"></div>
                <label class="checkbox"><input type="checkbox" name="is_active" value="1" checked><span class="small">Visible to customers</span></label>
            </div>
            <div class="modal-foot">
                <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
                <button class="btn" type="submit">Save category</button>
            </div>
        </form>
    </div>
</div>
<?php $__view->stop(); ?>
