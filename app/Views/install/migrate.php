<?php /** @var array<int,string> $pending @var array<int,string> $applied */ $__view->extend('layouts.install'); ?>
<?php $__view->start('content'); ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= icon('alert', 18) ?><div><?= e($error) ?></div></div>
        <a class="btn btn-secondary" href="<?= e(url('install/database')) ?>"><?= icon('arrow-left', 16) ?> Back to database settings</a>
    <?php else: ?>
        <p class="muted">The installer will now create every table, index and foreign key the platform needs.</p>

        <div class="card mb-3"><div class="card-body">
            <div class="row-between mb-2">
                <strong class="small">Pending migrations</strong>
                <span class="badge badge-brand"><?= count($pending) ?></span>
            </div>
            <?php if ($pending === []): ?>
                <p class="small muted mb-0">All migrations have already been applied.</p>
            <?php else: ?>
                <ul class="small muted" style="margin:0;padding-left:18px">
                    <?php foreach ($pending as $migration): ?>
                        <li><code><?= e($migration) ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($applied !== []): ?>
                <hr>
                <div class="small muted"><?= count($applied) ?> migration(s) already applied.</div>
            <?php endif; ?>
        </div></div>

        <form method="post" action="<?= e(url_path('install/migrate')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-lg btn-block" type="submit">
                <?= icon('database', 18) ?> <?= $pending === [] ? 'Re-run seed data and continue' : 'Create tables now' ?>
            </button>
        </form>
    <?php endif; ?>
<?php $__view->stop(); ?>
