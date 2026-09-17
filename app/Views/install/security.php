<?php /** @var array<int,array<string,mixed>> $report */ $__view->extend('layouts.install'); ?>
<?php $__view->start('content'); ?>
    <p class="muted">Final security review before going live.</p>

    <div class="card mb-3"><div class="card-body" style="padding:6px 18px">
        <?php foreach ($report as $item): ?>
            <div class="check-row">
                <span class="check-status <?= e($item['status']) ?>"><?= $item['status'] === 'pass' ? '✓' : ($item['status'] === 'warn' ? '!' : '✕') ?></span>
                <div class="flex-1">
                    <div class="row-between">
                        <strong class="small"><?= e($item['label']) ?></strong>
                        <span class="small muted"><?= e($item['value']) ?></span>
                    </div>
                    <?php if ($item['hint'] !== ''): ?><div class="tiny muted"><?= e($item['hint']) ?></div><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div></div>

    <form method="post" action="<?= e(url('install/finish')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-lg btn-block btn-success" type="submit"><?= icon('check-circle', 18) ?> Complete installation</button>
    </form>
    <p class="tiny muted text-center mt-2">This writes the installation lock file and permanently disables the installer.</p>
<?php $__view->stop(); ?>
