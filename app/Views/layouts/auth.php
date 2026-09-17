<?php
/**
 * Centred layout for sign-in / sign-up / password screens.
 *
 * @var App\Core\View $__view
 */
?><!doctype html>
<html lang="en" data-base="<?= e(url('/')) ?>">
<head>
<?= $__view->include('partials.head', ['branding' => $branding, 'title' => $title ?? 'Sign in']) ?>
</head>
<body style="background:linear-gradient(160deg,#eef2ff,#f8fafc 50%,#ecfeff);min-height:100vh">
<div style="min-height:100vh;display:grid;place-items:center;padding:28px 16px">
    <div style="width:100%;max-width:440px">
        <div class="text-center mb-3">
            <?= $__view->include('partials.logo', ['branding' => $branding]) ?>
        </div>

        <div class="card">
            <div class="card-body" style="padding:26px">
                <?= $__view->include('partials.flash', ['flashes' => $flashes ?? [], 'errors' => $errors ?? []]) ?>
                <?= $__view->section('content') ?>
            </div>
        </div>

        <p class="text-center small muted mt-3">
            <a href="<?= e(url('/')) ?>">&larr; Back to <?= e($branding['name']) ?></a>
        </p>
    </div>
</div>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<?= $__view->section('scripts') ?>
</body>
</html>
