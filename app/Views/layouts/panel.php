<?php
/**
 * App shell for the customer and reseller panels.
 *
 * @var App\Core\View $__view
 * @var App\Core\Auth $auth
 * @var array<int,array{href:string,label:string,icon:string,count?:int}> $nav
 * @var array<int,array{href:string,label:string,icon:string}> $mobileNav
 */
$user = $auth->user() ?? [];
$path = App\Core\Request::current()?->path() ?? '/';
$isActive = static function (string $href) use ($path): bool {
    $target = '/' . trim((string) parse_url($href, PHP_URL_PATH), '/');
    $target = str_replace(rtrim(App\Core\Url::basePath(), '/'), '', $target);
    if ($target === '/' || $target === '') {
        return $path === '/';
    }

    return $path === $target || str_starts_with($path, $target . '/');
};
?><!doctype html>
<html lang="en" data-base="<?= e(url('/')) ?>" data-sw="1">
<head>
<?= $__view->include('partials.head', ['branding' => $branding, 'title' => ($title ?? 'Dashboard') . ' · ' . $branding['name']]) ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<?php if ($auth->isImpersonating()): ?>
    <div class="impersonation-bar">
        <span><?= icon('alert', 16) ?> You are signed in as <strong><?= e($user['name'] ?? '') ?></strong> (<?= e($user['email'] ?? '') ?>).</span>
        <form method="post" action="<?= e(url_path('impersonate/stop')) ?>" data-no-lock="1">
            <?= csrf_field() ?>
            <button class="btn btn-sm" type="submit">Exit impersonation</button>
        </form>
    </div>
<?php endif; ?>

<div class="app">
    <div class="sidebar-backdrop" data-sidebar-backdrop></div>

    <aside class="sidebar" data-sidebar>
        <div class="sidebar-brand">
            <?= $__view->include('partials.logo', ['branding' => $branding, 'href' => url($auth->isReseller() ? 'reseller' : 'dashboard')]) ?>
        </div>

        <nav aria-label="Panel">
            <?php foreach (($navGroups ?? []) as $groupLabel => $items): ?>
                <div class="nav-group">
                    <?php if ($groupLabel !== ''): ?>
                        <div class="nav-group-title"><?= e($groupLabel) ?></div>
                    <?php endif; ?>
                    <?php foreach ($items as $item): ?>
                        <a class="nav-item <?= $isActive($item['href']) ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
                            <?= icon($item['icon'], 18) ?>
                            <span><?= e($item['label']) ?></span>
                            <?php if (!empty($item['count'])): ?><span class="count"><?= (int) $item['count'] ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="row" style="align-items:center;gap:10px;margin-bottom:10px">
                <?php if (!empty($user['avatar'])): ?>
                    <img class="avatar" src="<?= e(upload_url((string) $user['avatar'])) ?>" alt="">
                <?php else: ?>
                    <span class="avatar avatar-fallback"><?= e(mb_strtoupper(mb_substr((string) ($user['name'] ?? '?'), 0, 1))) ?></span>
                <?php endif; ?>
                <div class="flex-1">
                    <div class="small bold truncate"><?= e($user['name'] ?? '') ?></div>
                    <div class="tiny muted truncate"><?= e($user['email'] ?? '') ?></div>
                </div>
            </div>
            <div class="row" style="gap:6px">
                <a class="btn btn-secondary btn-sm flex-1" href="<?= e(url('account')) ?>"><?= icon('settings', 15) ?> Account</a>
                <form method="post" action="<?= e(url_path('logout')) ?>" style="flex:1" data-no-lock="1">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost btn-sm btn-block" type="submit"><?= icon('log-out', 15) ?> Sign out</button>
                </form>
            </div>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <button class="btn btn-ghost btn-icon" data-sidebar-toggle aria-label="Toggle menu" aria-expanded="false" style="display:none">
                <?= icon('menu', 22) ?>
            </button>
            <h1 class="flex-1 truncate"><?= e($title ?? 'Dashboard') ?></h1>
            <?= $__view->section('topbar') ?>
            <button class="btn btn-ghost btn-icon" data-theme-toggle aria-label="Toggle dark mode"><?= icon('moon', 18) ?></button>
        </header>

        <main class="content" id="main">
            <?= $__view->include('partials.flash', ['flashes' => $flashes ?? [], 'errors' => $errors ?? []]) ?>
            <?= $__view->section('content') ?>
        </main>
    </div>
</div>

<nav class="mobile-nav" aria-label="Quick navigation">
    <?php foreach (($mobileNav ?? []) as $item): ?>
        <a class="<?= $isActive($item['href']) ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
            <?= icon($item['icon'], 20) ?>
            <span><?= e($item['label']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<div id="toasts"></div>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<script>document.querySelector('[data-sidebar-toggle]').style.display='';</script>
<?= $__view->section('scripts') ?>
</body>
</html>
