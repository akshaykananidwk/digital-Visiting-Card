<?php
/**
 * @var array<string,mixed> $card
 * @var array<int,array<string,mixed>> $images @var array<int,array<string,mixed>> $videos
 * @var array{allowed:bool,used:int,limit:int,message:string} $imageLimit
 * @var array{allowed:bool,used:int,limit:int,message:string} $videoLimit
 * @var array{used:int,limit:int,percent:int} $storage
 */
$__view->extend('layouts.panel');
$cardId = (int) $card['id'];
?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm btn-secondary" href="<?= e(url('cards/' . $cardId . '/editor')) ?>"><?= icon('arrow-left', 15) ?> Editor</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<?php if ($storage['limit'] > 0): ?>
    <div class="card mb-3"><div class="card-body">
        <div class="row-between small mb-1">
            <span>Storage used</span>
            <strong><?= e(human_size($storage['used'])) ?> of <?= e(human_size($storage['limit'])) ?></strong>
        </div>
        <div class="progress <?= $storage['percent'] > 85 ? 'is-danger' : ($storage['percent'] > 65 ? 'is-warning' : '') ?>">
            <span style="width:<?= (int) $storage['percent'] ?>%"></span>
        </div>
    </div></div>
<?php endif; ?>

<div class="grid grid-2 mb-3">
    <div class="card">
        <div class="card-header"><h3 style="font-size:.98rem">Add photos</h3>
            <span class="badge"><?= count($images) ?><?= $imageLimit['limit'] >= 0 ? ' / ' . (int) $imageLimit['limit'] : '' ?></span>
        </div>
        <div class="card-body">
            <?php if ($imageLimit['allowed']): ?>
                <form method="post" action="<?= e(url('cards/' . $cardId . '/gallery')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <label class="upload-zone" for="gallery-files">
                        <?= icon('upload', 26) ?>
                        <div class="small bold mt-1">Choose images or drop them here</div>
                        <div class="tiny muted">JPG, PNG or WebP · up to <?= e(human_size((int) config('app.uploads.max_size'))) ?> each</div>
                        <input id="gallery-files" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple hidden>
                    </label>
                    <button class="btn btn-block mt-2" type="submit"><?= icon('upload', 16) ?> Upload images</button>
                </form>
            <?php else: ?>
                <div class="alert alert-info" style="margin:0"><?= icon('info', 18) ?><div><?= e($imageLimit['message']) ?> <a href="<?= e(url('billing')) ?>">Upgrade</a></div></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 style="font-size:.98rem">Add a YouTube video</h3>
            <span class="badge"><?= count($videos) ?><?= $videoLimit['limit'] >= 0 ? ' / ' . (int) $videoLimit['limit'] : '' ?></span>
        </div>
        <div class="card-body">
            <?php if ($videoLimit['allowed']): ?>
                <form method="post" action="<?= e(url('cards/' . $cardId . '/gallery/video')) ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="video-url">YouTube link</label>
                        <input class="input" type="url" id="video-url" name="url" required placeholder="https://youtu.be/…">
                    </div>
                    <div class="field">
                        <label for="video-title">Title</label>
                        <input class="input" type="text" id="video-title" name="title" maxlength="190">
                    </div>
                    <button class="btn btn-block" type="submit"><?= icon('video', 16) ?> Add video</button>
                </form>
            <?php else: ?>
                <div class="alert alert-info" style="margin:0"><?= icon('info', 18) ?><div><?= e($videoLimit['message']) ?> <a href="<?= e(url('billing')) ?>">Upgrade</a></div></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($images === [] && $videos === []): ?>
    <div class="card"><div class="empty-state">
        <div class="icon"><?= icon('image', 28) ?></div>
        <h3>Your gallery is empty</h3>
        <p class="muted">Add photos of your shop, work or products.</p>
    </div></div>
<?php else: ?>
    <div class="card">
        <div class="card-header"><h3 style="font-size:.98rem">Gallery items</h3></div>
        <div class="card-body">
            <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px">
                <?php foreach (array_merge($images, $videos) as $item): ?>
                    <div>
                        <div style="aspect-ratio:1;border-radius:11px;overflow:hidden;border:1px solid var(--border);background:var(--surface-2);position:relative">
                            <?php
                            $thumb = (string) $item['type'] === 'image'
                                ? (string) upload_url((string) $item['path'])
                                : (string) ($item['thumbnail'] ?: '');
                            ?>
                            <?php if ($thumb !== ''): ?>
                                <img src="<?= e($thumb) ?>" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover">
                            <?php else: ?>
                                <div style="display:grid;place-items:center;height:100%"><?= icon('video', 26) ?></div>
                            <?php endif; ?>
                            <?php if ((string) $item['type'] !== 'image'): ?>
                                <span style="position:absolute;inset:0;display:grid;place-items:center;background:rgba(0,0,0,.35);color:#fff"><?= icon('play', 24) ?></span>
                            <?php endif; ?>
                        </div>
                        <form method="post" action="<?= e(url('cards/' . $cardId . '/gallery/' . (int) $item['id'] . '/delete')) ?>" data-confirm="Remove this item?" class="mt-1">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost btn-sm btn-block" type="submit"><?= icon('trash', 13) ?> Remove</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php $__view->stop(); ?>
