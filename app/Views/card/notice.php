<?php
/**
 * Expired / suspended / unavailable card notice.
 *
 * @var string $heading @var string $message @var string $iconName
 * @var array{name:string} $branding
 */
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($heading) ?></title>
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/card.css')) ?>">
<style>:root{--c-primary:#4f46e5;--c-primary-soft:#eef2ff;--c-bg:#f6f7fb;--c-surface:#fff;--c-text:#0f172a;--c-muted:#64748b;--c-border:#e2e8f0;--font-body:"Plus Jakarta Sans",sans-serif;--font-heading:"Plus Jakarta Sans",sans-serif;--radius:18px;--btn-radius:999px;--shadow:0 12px 32px -14px rgba(15,23,42,.25);--font-scale:1}</style>
</head>
<body>
<div class="dvc-notice">
    <div style="max-width:420px">
        <div class="icon"><?= icon($iconName ?? 'alert', 36) ?></div>
        <h1 style="font-family:var(--font-heading)"><?= e($heading) ?></h1>
        <p style="color:var(--c-muted)"><?= e($message) ?></p>
        <?php if (!empty($ownerAction)): ?>
            <a class="dvc-btn" style="margin-top:18px" href="<?= e(url('login')) ?>">Sign in to renew</a>
        <?php endif; ?>
        <p style="margin-top:26px;font-size:.8rem;color:var(--c-muted)">
            <a href="<?= e(url('/')) ?>" style="color:var(--c-primary);font-weight:700"><?= e($branding['name'] ?? 'Home') ?></a>
        </p>
    </div>
</div>
</body>
</html>
