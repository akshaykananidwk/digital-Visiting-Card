<?php
/**
 * Admin console shell.
 *
 * @var App\Core\View $__view
 * @var App\Core\Auth $auth
 */
$user = $auth->user() ?? [];
$path = App\Core\Request::current()?->path() ?? '/';
$isActive = static function (string $href) use ($path): bool {
    $target = '/' . trim((string) parse_url($href, PHP_URL_PATH), '/');
    $target = str_replace(rtrim(App\Core\Url::basePath(), '/'), '', $target);

    return $path === $target || ($target !== '/admin' && str_starts_with($path, $target . '/'));
};
$maintenance = App\Middleware\Maintenance::isActive();
?><!doctype html>
<html lang="en" data-base="<?= e(url('/')) ?>">
<head>
<?= $__view->include('partials.head', ['branding' => $branding, 'title' => ($title ?? 'Admin') . ' · Admin']) ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<?php if ($maintenance): ?>
    <div class="impersonation-bar" style="background:var(--danger)">
        <span><?= icon('alert', 16) ?> Maintenance mode is ON — visitors cannot access the site.</span>
        <form method="post" action="<?= e(url_path('admin/updates/maintenance')) ?>" data-no-lock="1">
            <?= csrf_field() ?>
            <input type="hidden" name="enabled" value="0">
            <button class="btn btn-sm" type="submit">Turn off</button>
        </form>
    </div>
<?php endif; ?>

<div class="app">
    <div class="sidebar-backdrop" data-sidebar-backdrop></div>

    <aside class="sidebar" data-sidebar>
        <div class="sidebar-brand">
            <?= $__view->include('partials.logo', ['branding' => $branding, 'href' => url('admin')]) ?>
            <span class="badge badge-brand" style="margin-top:8px">Admin console</span>
        </div>

        <nav aria-label="Admin">
            <?php
            $groups = [
                'Overview' => [
                    ['href' => url('admin'), 'label' => 'Dashboard', 'icon' => 'grid', 'permission' => 'admin.access'],
                ],
                'Customers' => [
                    ['href' => url('admin/users'), 'label' => 'Users', 'icon' => 'users', 'permission' => 'admin.users'],
                    ['href' => url('admin/cards'), 'label' => 'Cards', 'icon' => 'layout', 'permission' => 'admin.cards'],
                    ['href' => url('admin/leads'), 'label' => 'Leads', 'icon' => 'inbox', 'permission' => 'admin.leads'],
                    ['href' => url('admin/resellers'), 'label' => 'Resellers', 'icon' => 'briefcase', 'permission' => 'admin.resellers'],
                ],
                'Catalogue' => [
                    ['href' => url('admin/templates'), 'label' => 'Templates', 'icon' => 'layers', 'permission' => 'admin.templates'],
                    ['href' => url('admin/categories'), 'label' => 'Categories', 'icon' => 'folder', 'permission' => 'admin.templates'],
                    ['href' => url('admin/plans'), 'label' => 'Plans', 'icon' => 'award', 'permission' => 'admin.plans'],
                ],
                'Revenue' => [
                    ['href' => url('admin/orders'), 'label' => 'Orders', 'icon' => 'package', 'permission' => 'admin.orders'],
                    ['href' => url('admin/payments'), 'label' => 'Payments', 'icon' => 'credit-card', 'permission' => 'admin.orders'],
                    ['href' => url('admin/invoices'), 'label' => 'Invoices', 'icon' => 'file-text', 'permission' => 'admin.orders'],
                ],
                'System' => [
                    ['href' => url('admin/settings'), 'label' => 'Settings', 'icon' => 'settings', 'permission' => 'admin.settings'],
                    ['href' => url('admin/updates'), 'label' => 'Updates', 'icon' => 'git', 'permission' => 'admin.updates'],
                    ['href' => url('admin/backups'), 'label' => 'Backups', 'icon' => 'archive', 'permission' => 'admin.backups'],
                    ['href' => url('admin/health'), 'label' => 'Health check', 'icon' => 'activity', 'permission' => 'admin.logs'],
                    ['href' => url('admin/logs'), 'label' => 'Logs', 'icon' => 'clipboard', 'permission' => 'admin.logs'],
                ],
            ];
            foreach ($groups as $groupLabel => $items):
                $visible = array_values(array_filter($items, static fn (array $item): bool => $auth->can($item['permission'])));
                if ($visible === []) {
                    continue;
                }
                ?>
                <div class="nav-group">
                    <div class="nav-group-title"><?= e($groupLabel) ?></div>
                    <?php foreach ($visible as $item): ?>
                        <a class="nav-item <?= $isActive($item['href']) ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
                            <?= icon($item['icon'], 18) ?><span><?= e($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="small bold truncate"><?= e($user['name'] ?? '') ?></div>
            <div class="tiny muted mb-2"><?= e(str_replace('_', ' ', (string) ($user['role'] ?? ''))) ?></div>
            <div class="row" style="gap:6px">
                <a class="btn btn-secondary btn-sm flex-1" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= icon('external', 15) ?> Site</a>
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
            <h1 class="flex-1 truncate"><?= e($title ?? 'Admin') ?></h1>
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
    <a class="<?= $path === '/admin' ? 'active' : '' ?>" href="<?= e(url('admin')) ?>"><?= icon('grid', 20) ?><span>Home</span></a>
    <a class="<?= str_starts_with($path, '/admin/users') ? 'active' : '' ?>" href="<?= e(url('admin/users')) ?>"><?= icon('users', 20) ?><span>Users</span></a>
    <a class="<?= str_starts_with($path, '/admin/cards') ? 'active' : '' ?>" href="<?= e(url('admin/cards')) ?>"><?= icon('layout', 20) ?><span>Cards</span></a>
    <a class="<?= str_starts_with($path, '/admin/orders') ? 'active' : '' ?>" href="<?= e(url('admin/orders')) ?>"><?= icon('package', 20) ?><span>Orders</span></a>
    <a class="<?= str_starts_with($path, '/admin/settings') ? 'active' : '' ?>" href="<?= e(url('admin/settings')) ?>"><?= icon('settings', 20) ?><span>Settings</span></a>
</nav>

<div id="toasts"></div>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<script>document.querySelector('[data-sidebar-toggle]').style.display='';</script>
<?= $__view->section('scripts') ?>
</body>
</html>
