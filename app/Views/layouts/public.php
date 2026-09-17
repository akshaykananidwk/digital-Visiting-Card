<?php
/**
 * Marketing / public site layout.
 *
 * @var App\Core\View $__view
 * @var App\Core\Auth $auth
 * @var array $branding
 */
$path = App\Core\Request::current()?->path() ?? '/';
$navItems = [
    '/features'         => 'Features',
    '/templates'        => 'Designs',
    '/pricing'          => 'Pricing',
    '/how-it-works'     => 'How it works',
    '/reseller-program' => 'Reseller',
    '/faq'              => 'FAQ',
];
?><!doctype html>
<html lang="en" data-base="<?= e(url('/')) ?>" data-sw="1">
<head>
<?= $__view->include('partials.head', ['branding' => $branding, 'title' => $title ?? null, 'metaDescription' => $metaDescription ?? null, 'canonical' => $canonical ?? null]) ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="container inner">
        <?= $__view->include('partials.logo', ['branding' => $branding]) ?>

        <nav class="site-nav" data-nav aria-label="Main">
            <?php foreach ($navItems as $href => $label): ?>
                <a href="<?= e(url($href)) ?>" class="<?= str_starts_with($path, $href) ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <?php if ($auth->check()): ?>
                <a class="btn btn-sm" href="<?= e(url($auth->isAdmin() ? 'admin' : ($auth->isReseller() ? 'reseller' : 'dashboard'))) ?>">My dashboard</a>
            <?php else: ?>
                <a href="<?= e(url('login')) ?>">Sign in</a>
                <a class="btn btn-sm" href="<?= e(url('register')) ?>">Create free card</a>
            <?php endif; ?>
        </nav>

        <button class="btn btn-ghost btn-icon nav-toggle" data-nav-toggle aria-label="Toggle navigation" aria-expanded="false">
            <?= icon('menu', 22) ?>
        </button>
    </div>
</header>

<main id="main">
    <?php if ($__view->hasSection('hero')): ?>
        <?= $__view->section('hero') ?>
    <?php endif; ?>

    <div class="container" style="padding-top:12px">
        <?= $__view->include('partials.flash', ['flashes' => $flashes ?? [], 'errors' => $errors ?? []]) ?>
    </div>

    <?= $__view->section('content') ?>
</main>

<footer style="border-top:1px solid var(--border);background:var(--surface);padding:44px 0 32px;margin-top:40px">
    <div class="container">
        <div class="grid grid-4" style="gap:28px">
            <div>
                <?= $__view->include('partials.logo', ['branding' => $branding]) ?>
                <p class="small muted mt-2"><?= e($branding['tagline'] ?? '') ?></p>
                <?php if (!empty($branding['support_phone'])): ?>
                    <p class="small muted">Support: <a href="tel:<?= e($branding['support_phone']) ?>"><?= e($branding['support_phone']) ?></a></p>
                <?php endif; ?>
                <?php if (!empty($branding['support_email'])): ?>
                    <p class="small muted"><a href="mailto:<?= e($branding['support_email']) ?>"><?= e($branding['support_email']) ?></a></p>
                <?php endif; ?>
            </div>
            <div>
                <h4 class="small bold">Product</h4>
                <ul style="list-style:none;padding:0;margin:0;display:grid;gap:7px" class="small">
                    <li><a href="<?= e(url('features')) ?>">Features</a></li>
                    <li><a href="<?= e(url('templates')) ?>">Design library</a></li>
                    <li><a href="<?= e(url('pricing')) ?>">Pricing</a></li>
                    <li><a href="<?= e(url('how-it-works')) ?>">How it works</a></li>
                </ul>
            </div>
            <div>
                <h4 class="small bold">Company</h4>
                <ul style="list-style:none;padding:0;margin:0;display:grid;gap:7px" class="small">
                    <li><a href="<?= e(url('reseller-program')) ?>">Reseller program</a></li>
                    <li><a href="<?= e(url('contact')) ?>">Contact</a></li>
                    <li><a href="<?= e(url('faq')) ?>">FAQ</a></li>
                </ul>
            </div>
            <div>
                <h4 class="small bold">Legal</h4>
                <ul style="list-style:none;padding:0;margin:0;display:grid;gap:7px" class="small">
                    <li><a href="<?= e(url('terms')) ?>">Terms of service</a></li>
                    <li><a href="<?= e(url('privacy')) ?>">Privacy policy</a></li>
                </ul>
            </div>
        </div>
        <hr>
        <div class="row-between small muted">
            <span>&copy; <?= date('Y') ?> <?= e($branding['name']) ?>. All rights reserved.</span>
            <span>v<?= e(APP_VERSION) ?></span>
        </div>
    </div>
</footer>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<?= $__view->section('scripts') ?>
</body>
</html>
