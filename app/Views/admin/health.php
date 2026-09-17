<?php /** @var array{healthy:bool,checks:array<int,array<string,string>>,failed:array<int,string>,ran_at:string} $report
 *  @var bool $endpoints */
$__view->extend('layouts.admin'); ?>
<?php $__view->start('content'); ?>
<div class="card mb-3">
    <div class="card-body row-between">
        <div>
            <h2 style="margin:0"><?= $report['healthy'] ? 'All systems healthy' : 'Attention required' ?></h2>
            <p class="small muted mb-0">Last run <?= e($report['ran_at']) ?><?= $report['failed'] !== [] ? ' · failing: ' . e(implode(', ', $report['failed'])) : '' ?></p>
        </div>
        <div class="row" style="gap:8px">
            <span class="badge <?= $report['healthy'] ? 'badge-success' : 'badge-danger' ?>"><?= $report['healthy'] ? 'healthy' : 'unhealthy' ?></span>
            <a class="btn btn-sm btn-secondary" href="<?= e(url('admin/health?endpoints=1')) ?>"><?= icon('refresh', 15) ?> Re-run with HTTP checks</a>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body" style="padding:6px 20px">
        <?php foreach ($report['checks'] as $check): ?>
            <div class="check-row">
                <span class="check-status <?= e($check['status']) ?>"><?= $check['status'] === 'pass' ? '✓' : ($check['status'] === 'warn' ? '!' : '✕') ?></span>
                <div class="flex-1">
                    <div class="row-between">
                        <strong class="small"><?= e($check['name']) ?></strong>
                        <span class="small muted"><?= e($check['message']) ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Maintenance tasks</h2></div>
    <div class="card-body">
        <p class="small muted">These normally run from cron. Run one manually if you need to.</p>
        <div class="row wrap" style="gap:10px">
            <?php foreach ([
                'expire-subscriptions' => ['Expire finished subscriptions', 'clock'],
                'prune-analytics'      => ['Remove analytics older than 400 days', 'bar-chart'],
                'purge-rate-limits'    => ['Purge expired rate-limit rows', 'shield'],
                'clear-cache'          => ['Clear cache and reset OPcache', 'refresh'],
            ] as $task => [$label, $iconName]): ?>
                <form method="post" action="<?= e(url('admin/maintenance/run/' . $task)) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-secondary btn-sm" type="submit"><?= icon($iconName, 14) ?> <?= e($label) ?></button>
                </form>
            <?php endforeach; ?>
        </div>

        <hr>
        <h3 style="font-size:.95rem">Recommended cron jobs</h3>
        <pre class="small"># Expire subscriptions and take expired cards offline (daily at 01:00)
0 1 * * * <?= e(PHP_BINARY) ?> <?= e(BASE_PATH) ?>/bin/console.php subscriptions:expire

# Housekeeping: analytics retention and rate-limit rows (weekly)
0 2 * * 0 <?= e(PHP_BINARY) ?> <?= e(BASE_PATH) ?>/bin/console.php cleanup</pre>
    </div>
</div>
<?php $__view->stop(); ?>
