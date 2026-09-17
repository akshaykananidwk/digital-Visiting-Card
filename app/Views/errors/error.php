<?php
/**
 * Friendly error page. Technical details are never shown unless APP_DEBUG is
 * explicitly enabled.
 *
 * @var int $status @var string $message @var string $reference @var bool $debug
 */
$titles = [
    400 => 'Bad request',
    401 => 'Sign in required',
    403 => 'Access denied',
    404 => 'Page not found',
    405 => 'Method not allowed',
    419 => 'Session expired',
    429 => 'Too many requests',
    500 => 'Something went wrong',
    503 => 'Under maintenance',
];
$heading = $titles[$status] ?? 'Error';
$icons = [403 => 'lock', 404 => 'search', 419 => 'refresh', 429 => 'clock', 503 => 'settings'];
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= (int) $status ?> · <?= e($heading) ?></title>
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body>
<div style="min-height:100vh;display:grid;place-items:center;padding:30px 20px">
    <div class="text-center" style="max-width:480px">
        <div class="feature-icon" style="margin:0 auto 18px;width:72px;height:72px">
            <?= icon($icons[$status] ?? 'alert', 32) ?>
        </div>
        <div class="badge badge-brand mb-2">Error <?= (int) $status ?></div>
        <h1 style="font-size:1.8rem"><?= e($heading) ?></h1>
        <p class="muted"><?= e($message) ?></p>

        <?php if (($reference ?? 'N/A') !== 'N/A'): ?>
            <p class="small muted">Reference code: <code><?= e($reference) ?></code><br>Quote this code when contacting support.</p>
        <?php endif; ?>

        <div class="row" style="justify-content:center;gap:10px;margin-top:20px">
            <a class="btn" href="<?= e(url('/')) ?>"><?= icon('home', 16) ?> Go to home</a>
            <?php if ($status === 401): ?>
                <a class="btn btn-secondary" href="<?= e(url('login')) ?>">Sign in</a>
            <?php elseif ($status === 419): ?>
                <button class="btn btn-secondary" type="button" onclick="location.reload()">Reload page</button>
            <?php endif; ?>
        </div>

        <?php if (!empty($debug) && isset($e) && $e instanceof Throwable): ?>
            <pre class="text-left mt-4" style="font-size:.72rem;max-height:340px"><?= e($e::class . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString()) ?></pre>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
