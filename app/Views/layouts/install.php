<?php
/**
 * Installer wizard shell.
 *
 * @var App\Core\View $__view
 * @var array<string,array{0:string,1:int}> $steps
 * @var string $currentStep
 */
?><!doctype html>
<html lang="en" data-base="<?= e(url('/')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install · Digital Visiting Card</title>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body>
<div class="install-shell">
    <div class="install-card">
        <div class="install-head">
            <div class="row-between">
                <div>
                    <div class="small muted bold">Step <?= (int) ($stepNumber ?? 1) ?> of <?= (int) ($totalSteps ?? 10) ?></div>
                    <h1 style="font-size:1.4rem;margin:2px 0 0"><?= e($stepTitle ?? 'Install') ?></h1>
                </div>
                <span class="badge badge-brand">v<?= e(APP_VERSION) ?></span>
            </div>
            <div class="progress mt-2"><span style="width:<?= (int) round((($stepNumber ?? 1) / max(1, (int) ($totalSteps ?? 10))) * 100) ?>%"></span></div>
            <div class="step-list">
                <?php foreach (($steps ?? []) as $key => [$label, $number]): ?>
                    <span class="step-pill <?= $key === $currentStep ? 'current' : ($number < ($stepNumber ?? 1) ? 'done' : '') ?>"><?= e($label) ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="install-body">
            <?= $__view->include('partials.flash', ['flashes' => $flashes ?? [], 'errors' => $errors ?? []]) ?>
            <?= $__view->section('content') ?>
        </div>

        <?php if ($__view->hasSection('actions')): ?>
            <div class="install-foot"><?= $__view->section('actions') ?></div>
        <?php endif; ?>
    </div>
</div>
<div id="toasts"></div>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<?= $__view->section('scripts') ?>
</body>
</html>
