<?php
/** @var array<string,mixed> $card @var array<int,array<string,mixed>> $services @var array{allowed:bool,used:int,limit:int,message:string} $limit */
$__view->extend('layouts.panel');
$cardId = (int) $card['id'];
?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm btn-secondary" href="<?= e(url('cards/' . $cardId . '/editor')) ?>"><?= icon('arrow-left', 15) ?> Editor</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="row-between mb-3">
    <p class="small muted mb-0">
        <?= count($services) ?> service(s)<?= $limit['limit'] >= 0 ? ' of ' . (int) $limit['limit'] . ' allowed' : '' ?>
    </p>
    <?php if ($limit['allowed']): ?>
        <button class="btn btn-sm" type="button" data-modal-open="service-modal"
                data-modal-action="<?= e(url('cards/' . $cardId . '/services')) ?>"
                data-modal-fill='{"title":"","description":"","price":"","price_label":"","cta_label":"","cta_link":"","icon":""}'>
            <?= icon('plus', 15) ?> Add service
        </button>
    <?php else: ?>
        <a class="btn btn-sm btn-secondary" href="<?= e(url('billing')) ?>">Upgrade for more</a>
    <?php endif; ?>
</div>

<?php if ($services === []): ?>
    <div class="card"><div class="empty-state">
        <div class="icon"><?= icon('zap', 28) ?></div>
        <h3>No services added</h3>
        <p class="muted">List what you do so visitors know how you can help.</p>
    </div></div>
<?php else: ?>
    <div class="grid grid-2">
        <?php foreach ($services as $service): ?>
            <div class="card">
                <div class="card-body">
                    <div class="row-between mb-2">
                        <div class="row" style="gap:12px;align-items:center;min-width:0">
                            <?php if (!empty($service['image'])): ?>
                                <img class="avatar" style="border-radius:11px" src="<?= e((string) upload_url((string) $service['image'])) ?>" alt="">
                            <?php else: ?>
                                <span class="feature-icon" style="margin:0;width:38px;height:38px"><?= icon(App\Core\Icon::exists((string) ($service['icon'] ?? '')) ? (string) $service['icon'] : 'zap', 18) ?></span>
                            <?php endif; ?>
                            <div style="min-width:0">
                                <div class="bold truncate"><?= e((string) $service['title']) ?></div>
                                <?php if ($service['price'] !== null && (float) $service['price'] > 0): ?>
                                    <div class="tiny muted"><?= e((string) ($service['price_label'] ?: 'From')) ?> <?= e(money((float) $service['price'])) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ((int) $service['is_active'] !== 1): ?><span class="badge">Hidden</span><?php endif; ?>
                    </div>
                    <?php if (!empty($service['description'])): ?>
                        <p class="small muted"><?= e(mb_substr((string) $service['description'], 0, 160)) ?></p>
                    <?php endif; ?>
                    <div class="row" style="gap:6px">
                        <button class="btn btn-sm btn-secondary" type="button" data-modal-open="service-modal"
                                data-modal-action="<?= e(url('cards/' . $cardId . '/services/' . (int) $service['id'])) ?>"
                                data-modal-fill='<?= e(json_encode([
                                    'title' => $service['title'], 'description' => $service['description'],
                                    'price' => $service['price'], 'price_label' => $service['price_label'],
                                    'cta_label' => $service['cta_label'], 'cta_link' => $service['cta_link'],
                                    'icon' => $service['icon'], 'is_active' => (int) $service['is_active'] === 1,
                                ])) ?>'>
                            <?= icon('edit', 14) ?> Edit
                        </button>
                        <form method="post" action="<?= e(url_path('cards/' . $cardId . '/services/' . (int) $service['id'] . '/delete')) ?>" data-confirm="Remove this service?">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-ghost" type="submit"><?= icon('trash', 14) ?></button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="modal-backdrop" id="service-modal">
    <div class="modal" role="dialog" aria-label="Service">
        <form method="post" enctype="multipart/form-data" action="<?= e(url_path('cards/' . $cardId . '/services')) ?>">
            <?= csrf_field() ?>
            <div class="modal-head"><h3>Service</h3><button class="btn btn-ghost btn-icon" type="button" data-modal-close><?= icon('x', 18) ?></button></div>
            <div class="modal-body">
                <div class="field">
                    <label class="required" for="s_title">Title</label>
                    <input class="input" type="text" id="s_title" name="title" required maxlength="150">
                </div>
                <div class="field">
                    <label for="s_description">Description</label>
                    <textarea class="textarea" id="s_description" name="description" rows="3" maxlength="1000"></textarea>
                </div>
                <div class="grid grid-2" style="gap:0 12px">
                    <div class="field">
                        <label for="s_price">Price</label>
                        <input class="input" type="number" id="s_price" name="price" step="0.01" min="0">
                    </div>
                    <div class="field">
                        <label for="s_price_label">Price label</label>
                        <input class="input" type="text" id="s_price_label" name="price_label" maxlength="60" placeholder="Starting at">
                    </div>
                </div>
                <div class="grid grid-2" style="gap:0 12px">
                    <div class="field">
                        <label for="s_cta_label">Button label</label>
                        <input class="input" type="text" id="s_cta_label" name="cta_label" maxlength="60" placeholder="Enquire">
                    </div>
                    <div class="field">
                        <label for="s_cta_link">Button link</label>
                        <input class="input" type="url" id="s_cta_link" name="cta_link" maxlength="500" placeholder="https://">
                    </div>
                </div>
                <div class="field">
                    <label for="s_icon">Icon</label>
                    <select id="s_icon" name="icon">
                        <option value="">Default</option>
                        <?php foreach (['zap', 'shield', 'settings', 'package', 'phone', 'globe', 'award', 'briefcase', 'image', 'video', 'clock', 'star', 'key', 'activity'] as $iconName): ?>
                            <option value="<?= e($iconName) ?>"><?= e(ucfirst($iconName)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="s_image">Image (optional)</label>
                    <input class="input" type="file" id="s_image" name="image" accept="image/jpeg,image/png,image/webp">
                </div>
                <label class="checkbox"><input type="checkbox" name="is_active" value="1" checked><span class="small">Show on the card</span></label>
            </div>
            <div class="modal-foot">
                <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
                <button class="btn" type="submit">Save service</button>
            </div>
        </form>
    </div>
</div>
<?php $__view->stop(); ?>
