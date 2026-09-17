<?php
/** @var array<string,mixed> $card @var array<int,array<string,mixed>> $products @var array{allowed:bool,used:int,limit:int,message:string} $limit */
$__view->extend('layouts.panel');
$cardId = (int) $card['id'];
?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm btn-secondary" href="<?= e(url('cards/' . $cardId . '/editor')) ?>"><?= icon('arrow-left', 15) ?> Editor</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="row-between mb-3">
    <p class="small muted mb-0"><?= count($products) ?> product(s)<?= $limit['limit'] >= 0 ? ' of ' . (int) $limit['limit'] . ' allowed' : '' ?></p>
    <?php if ($limit['allowed']): ?>
        <button class="btn btn-sm" type="button" data-modal-open="product-modal"
                data-modal-action="<?= e(url('cards/' . $cardId . '/products')) ?>"
                data-modal-fill='{"name":"","description":"","price":"","discount_price":"","sku":"","category":"","cta_link":""}'>
            <?= icon('plus', 15) ?> Add product
        </button>
    <?php else: ?>
        <a class="btn btn-sm btn-secondary" href="<?= e(url('billing')) ?>">Upgrade for more</a>
    <?php endif; ?>
</div>

<?php if ($products === []): ?>
    <div class="card"><div class="empty-state">
        <div class="icon"><?= icon('package', 28) ?></div>
        <h3>No products yet</h3>
        <p class="muted">Show your catalogue with prices and a WhatsApp enquiry button.</p>
    </div></div>
<?php else: ?>
    <div class="grid grid-3">
        <?php foreach ($products as $product): ?>
            <div class="card">
                <?php if (!empty($product['image'])): ?>
                    <img src="<?= e((string) upload_url((string) $product['image'])) ?>" alt="" style="aspect-ratio:4/3;object-fit:cover;width:100%;border-radius:var(--radius) var(--radius) 0 0">
                <?php endif; ?>
                <div class="card-body">
                    <div class="row-between mb-1">
                        <div class="bold truncate"><?= e((string) $product['name']) ?></div>
                        <?php if ((int) $product['is_active'] !== 1): ?><span class="badge">Hidden</span><?php endif; ?>
                    </div>
                    <?php if ($product['price'] !== null && (float) $product['price'] > 0): ?>
                        <div class="small bold" style="color:var(--brand)">
                            <?= e(money((float) ($product['discount_price'] ?: $product['price']))) ?>
                            <?php if (!empty($product['discount_price'])): ?><del class="muted" style="font-weight:400"><?= e(money((float) $product['price'])) ?></del><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="tiny muted mb-2"><?= e((string) str_replace('_', ' ', (string) $product['stock_status'])) ?></div>
                    <div class="row" style="gap:6px">
                        <button class="btn btn-sm btn-secondary" type="button" data-modal-open="product-modal"
                                data-modal-action="<?= e(url('cards/' . $cardId . '/products/' . (int) $product['id'])) ?>"
                                data-modal-fill='<?= e(json_encode([
                                    'name' => $product['name'], 'description' => $product['description'],
                                    'price' => $product['price'], 'discount_price' => $product['discount_price'],
                                    'sku' => $product['sku'], 'category' => $product['category'],
                                    'stock_status' => $product['stock_status'], 'cta_type' => $product['cta_type'],
                                    'cta_link' => $product['cta_link'], 'is_active' => (int) $product['is_active'] === 1,
                                    'is_featured' => (int) $product['is_featured'] === 1,
                                ])) ?>'>
                            <?= icon('edit', 14) ?> Edit
                        </button>
                        <form method="post" action="<?= e(url('cards/' . $cardId . '/products/' . (int) $product['id'] . '/delete')) ?>" data-confirm="Remove this product?">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-ghost" type="submit"><?= icon('trash', 14) ?></button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="modal-backdrop" id="product-modal">
    <div class="modal" role="dialog" aria-label="Product">
        <form method="post" enctype="multipart/form-data" action="<?= e(url('cards/' . $cardId . '/products')) ?>">
            <?= csrf_field() ?>
            <div class="modal-head"><h3>Product</h3><button class="btn btn-ghost btn-icon" type="button" data-modal-close><?= icon('x', 18) ?></button></div>
            <div class="modal-body">
                <div class="field">
                    <label class="required" for="p_name">Product name</label>
                    <input class="input" type="text" id="p_name" name="name" required maxlength="190">
                </div>
                <div class="field">
                    <label for="p_description">Description</label>
                    <textarea class="textarea" id="p_description" name="description" rows="3" maxlength="1000"></textarea>
                </div>
                <div class="grid grid-2" style="gap:0 12px">
                    <div class="field"><label for="p_price">Price</label><input class="input" type="number" id="p_price" name="price" step="0.01" min="0"></div>
                    <div class="field"><label for="p_discount">Discounted price</label><input class="input" type="number" id="p_discount" name="discount_price" step="0.01" min="0"></div>
                </div>
                <div class="grid grid-2" style="gap:0 12px">
                    <div class="field"><label for="p_sku">SKU</label><input class="input" type="text" id="p_sku" name="sku" maxlength="60"></div>
                    <div class="field"><label for="p_category">Category</label><input class="input" type="text" id="p_category" name="category" maxlength="100"></div>
                </div>
                <div class="grid grid-2" style="gap:0 12px">
                    <div class="field">
                        <label for="p_stock">Availability</label>
                        <select id="p_stock" name="stock_status">
                            <option value="in_stock">In stock</option>
                            <option value="out_of_stock">Out of stock</option>
                            <option value="made_to_order">Made to order</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="p_cta">Button action</label>
                        <select id="p_cta" name="cta_type">
                            <option value="whatsapp">WhatsApp enquiry</option>
                            <option value="call">Call</option>
                            <option value="link">Open a link</option>
                            <option value="none">No button</option>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label for="p_cta_link">Button link (when "Open a link")</label>
                    <input class="input" type="url" id="p_cta_link" name="cta_link" maxlength="500" placeholder="https://">
                </div>
                <div class="field">
                    <label for="p_image">Product image</label>
                    <input class="input" type="file" id="p_image" name="image" accept="image/jpeg,image/png,image/webp">
                </div>
                <label class="checkbox"><input type="checkbox" name="is_active" value="1" checked><span class="small">Show on the card</span></label>
                <label class="checkbox"><input type="checkbox" name="is_featured" value="1"><span class="small">Feature this product</span></label>
            </div>
            <div class="modal-foot">
                <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
                <button class="btn" type="submit">Save product</button>
            </div>
        </form>
    </div>
</div>
<?php $__view->stop(); ?>
