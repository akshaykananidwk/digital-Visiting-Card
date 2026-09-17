<?php /** @var App\Services\CardPresenter $card @var string $title */ ?>
<section class="dvc-section">
    <div class="dvc-panel">
        <h2 class="dvc-section-title"><?= icon('info', 15) ?> <?= e($title) ?></h2>
        <p class="dvc-about"><?= e((string) $card->get('about')) ?></p>
    </div>
</section>
