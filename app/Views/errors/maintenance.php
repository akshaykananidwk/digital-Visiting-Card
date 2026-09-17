<?php /** @var string $message */ ?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Under maintenance</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body>
<div style="min-height:100vh;display:grid;place-items:center;padding:30px 20px;text-align:center">
    <div style="max-width:440px">
        <div class="feature-icon" style="margin:0 auto 18px;width:72px;height:72px"><?= icon('settings', 32) ?></div>
        <h1>We will be right back</h1>
        <p class="muted"><?= e($message ?? 'We are performing scheduled maintenance.') ?></p>
    </div>
</div>
</body>
</html>
