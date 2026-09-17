<?php
/**
 * Shared <head> contents.
 *
 * @var App\Core\View $__view
 * @var array{name:string,logo:?string,favicon:?string} $branding
 */
$title = $title ?? ($branding['name'] ?? 'Digital Visiting Card');
$metaDescription = $metaDescription ?? (string) (setting('meta_description') ?? '');
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#4f46e5">
<title><?= e($title) ?></title>
<?php if ($metaDescription !== ''): ?>
<meta name="description" content="<?= e($metaDescription) ?>">
<?php endif; ?>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="canonical" href="<?= e($canonical ?? App\Core\Url::current()) ?>">
<?php if (!empty($branding['favicon'])): ?>
<link rel="icon" href="<?= e($branding['favicon']) ?>">
<?php else: ?>
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="8" fill="#4f46e5"/><text x="16" y="22" font-size="17" font-family="sans-serif" font-weight="bold" fill="#fff" text-anchor="middle">D</text></svg>') ?>">
<?php endif; ?>
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<?= $__view->section('head') ?>
