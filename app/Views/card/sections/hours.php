<?php /** @var App\Services\CardPresenter $card @var string $title */ $open = $card->openState(); ?>
<section class="dvc-section">
    <div class="dvc-panel">
        <h2 class="dvc-section-title"><?= icon('clock', 15) ?> <?= e($title) ?></h2>
        <?php if ($open !== null): ?>
            <p style="margin:0 0 10px"><span class="dvc-open-badge <?= e($open['state']) ?>"><?= e($open['label']) ?></span></p>
        <?php endif; ?>
        <div class="dvc-hours">
            <?php foreach ($card->businessHours() as $row): ?>
                <div class="dvc-hours-row <?= $row['today'] ? 'today' : '' ?>">
                    <span><?= e($row['label']) ?><?= $row['today'] ? ' · today' : '' ?></span>
                    <?php if ($row['closed'] || $row['open'] === '' || $row['close'] === ''): ?>
                        <span class="closed">Closed</span>
                    <?php else: ?>
                        <span><?= e($card->formatTime($row['open'])) ?> – <?= e($card->formatTime($row['close'])) ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
