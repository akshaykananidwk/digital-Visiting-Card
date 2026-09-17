<?php /** @var string $file @var array<int,string> $lines @var int $size */ $__view->extend('layouts.admin'); ?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm btn-secondary" href="<?= e(url('admin/logs')) ?>"><?= icon('arrow-left', 15) ?> All logs</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="card">
    <div class="card-header">
        <h2><?= e($file) ?></h2>
        <span class="small muted"><?= e(human_size($size)) ?> · showing the last <?= count($lines) ?> lines</span>
    </div>
    <div class="card-body">
        <pre style="font-size:.72rem;max-height:70vh"><?php foreach ($lines as $line) { echo e($line) . "\n"; } ?></pre>
    </div>
</div>
<?php $__view->stop(); ?>
