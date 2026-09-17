<?php /** @var array{passed:bool,items:array<int,array<string,mixed>>} $report */ $__view->extend('layouts.install'); ?>
<?php $__view->start('content'); ?>
    <p class="muted">We checked your server against the platform requirements.</p>

    <?php if ($report['passed']): ?>
        <div class="alert alert-success"><?= icon('check-circle', 18) ?><div>Your server meets all of the required conditions.</div></div>
    <?php else: ?>
        <div class="alert alert-error"><?= icon('alert', 18) ?><div>Some required checks failed. Fix the items marked in red, then reload this page.</div></div>
    <?php endif; ?>

    <div class="card"><div class="card-body" style="padding:6px 18px">
        <?php foreach ($report['items'] as $item): ?>
            <div class="check-row">
                <span class="check-status <?= e($item['status']) ?>"><?= $item['status'] === 'pass' ? '✓' : ($item['status'] === 'warn' ? '!' : '✕') ?></span>
                <div class="flex-1">
                    <div class="row-between">
                        <strong class="small"><?= e($item['label']) ?></strong>
                        <span class="small muted"><?= e($item['value']) ?></span>
                    </div>
                    <?php if ($item['hint'] !== ''): ?>
                        <div class="tiny muted"><?= e($item['hint']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div></div>
<?php $__view->stop(); ?>

<?php $__view->start('actions'); ?>
    <a class="btn btn-secondary" href="<?= e(url('install')) ?>"><?= icon('arrow-left', 16) ?> Back</a>
    <div class="row" style="gap:8px">
        <a class="btn btn-secondary" href="<?= e(url('install/requirements')) ?>"><?= icon('refresh', 16) ?> Re-check</a>
        <a class="btn <?= $report['passed'] ? '' : 'btn-secondary' ?>" href="<?= e(url('install/database')) ?>" <?= $report['passed'] ? '' : 'aria-disabled="true" onclick="return confirm(\'Some required checks failed. Continue anyway?\')"' ?>>
            Continue <?= icon('arrow-right', 16) ?>
        </a>
    </div>
<?php $__view->stop(); ?>
