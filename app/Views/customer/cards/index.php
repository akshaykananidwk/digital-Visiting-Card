<?php
/** @var array<int,array<string,mixed>> $cards @var array{allowed:bool,message:string} $canCreate */
$__view->extend('layouts.panel');
?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm" href="<?= e(url('cards/create')) ?>"><?= icon('plus', 16) ?> New card</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<?php if (!$canCreate['allowed']): ?>
    <div class="alert alert-info">
        <?= icon('info', 18) ?>
        <div><?= e($canCreate['message']) ?> <a href="<?= e(url('billing')) ?>">See plans</a></div>
    </div>
<?php endif; ?>

<?php if ($cards === []): ?>
    <div class="card"><div class="empty-state">
        <div class="icon"><?= icon('layout', 28) ?></div>
        <h3>No cards yet</h3>
        <p class="muted">Create your first digital visiting card and share it in minutes.</p>
        <a class="btn" href="<?= e(url('cards/create')) ?>"><?= icon('plus', 16) ?> Create a card</a>
    </div></div>
<?php else: ?>
    <div class="grid grid-2">
        <?php foreach ($cards as $card): ?>
            <div class="card">
                <div class="card-body">
                    <div class="row-between mb-2">
                        <div style="min-width:0">
                            <h3 class="truncate" style="margin-bottom:2px;font-size:1.05rem"><?= e((string) $card['title']) ?></h3>
                            <a class="small" href="<?= e(url('card/' . $card['slug'])) ?>" target="_blank" rel="noopener">/<?= e((string) $card['slug']) ?> <?= icon('external', 12) ?></a>
                        </div>
                        <?php
                        $badge = match ((string) $card['status']) {
                            'published' => 'badge-success', 'expired' => 'badge-warning',
                            'suspended' => 'badge-danger', default => '',
                        };
                        ?>
                        <span class="badge <?= $badge ?>"><?= e((string) $card['status']) ?></span>
                    </div>

                    <div class="grid grid-4 mb-3" style="gap:8px">
                        <?php foreach ([
                            ['Views', $card['stats']['views'] ?? 0],
                            ['Calls', $card['stats']['calls'] ?? 0],
                            ['WhatsApp', $card['stats']['whatsapp'] ?? 0],
                            ['Leads', $card['stats']['leads'] ?? 0],
                        ] as [$label, $value]): ?>
                            <div style="text-align:center;padding:8px;border-radius:9px;background:var(--surface-2)">
                                <div class="bold"><?= number_format((int) $value) ?></div>
                                <div class="tiny muted"><?= e($label) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="row" style="gap:6px">
                        <a class="btn btn-sm flex-1" href="<?= e(url('cards/' . (int) $card['id'] . '/editor')) ?>"><?= icon('edit', 14) ?> Edit</a>
                        <a class="btn btn-sm btn-secondary" href="<?= e(url('cards/' . (int) $card['id'] . '/design')) ?>"><?= icon('layers', 14) ?></a>
                        <a class="btn btn-sm btn-secondary" href="<?= e(url('cards/' . (int) $card['id'] . '/qr')) ?>"><?= icon('qr', 14) ?></a>
                        <a class="btn btn-sm btn-secondary" href="<?= e(url('analytics/' . (int) $card['id'])) ?>"><?= icon('bar-chart', 14) ?></a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php $__view->stop(); ?>
