<?php

declare(strict_types=1);

/**
 * Application route table.
 *
 * @var App\Core\Router $router
 */

use App\Core\Router;

/** @var Router $router */

// ---------------------------------------------------------------------------
// Installer (only reachable while the application is not yet installed)
// ---------------------------------------------------------------------------
$router->group(['prefix' => 'install'], static function (Router $router): void {
    $router->get('/', 'Install\InstallController@welcome')->name('install.welcome');
    $router->get('/requirements', 'Install\InstallController@requirements')->name('install.requirements');
    $router->get('/database', 'Install\InstallController@database')->name('install.database');
    $router->post('/database', 'Install\InstallController@saveDatabase');
    $router->post('/test-connection', 'Install\InstallController@testConnection');
    $router->get('/migrate', 'Install\InstallController@migrate')->name('install.migrate');
    $router->post('/migrate', 'Install\InstallController@runMigrations');
    $router->get('/admin', 'Install\InstallController@admin')->name('install.admin');
    $router->post('/admin', 'Install\InstallController@saveAdmin');
    $router->get('/site', 'Install\InstallController@site')->name('install.site');
    $router->post('/site', 'Install\InstallController@saveSite');
    $router->get('/payment', 'Install\InstallController@payment')->name('install.payment');
    $router->post('/payment', 'Install\InstallController@savePayment');
    $router->get('/designs', 'Install\InstallController@designs')->name('install.designs');
    $router->post('/designs', 'Install\InstallController@generateDesigns');
    $router->get('/security', 'Install\InstallController@security')->name('install.security');
    $router->post('/finish', 'Install\InstallController@finish');
    $router->get('/complete', 'Install\InstallController@complete')->name('install.complete');
});

// ---------------------------------------------------------------------------
// Public marketing site
// ---------------------------------------------------------------------------
$router->get('/', 'Site\SiteController@home')->name('home')->middleware('Maintenance');
$router->get('/features', 'Site\SiteController@features')->name('features')->middleware('Maintenance');
$router->get('/pricing', 'Site\SiteController@pricing')->name('pricing')->middleware('Maintenance');
$router->get('/how-it-works', 'Site\SiteController@howItWorks')->name('how-it-works')->middleware('Maintenance');
$router->get('/reseller-program', 'Site\SiteController@resellerProgram')->name('reseller-program')->middleware('Maintenance');
$router->get('/faq', 'Site\SiteController@faq')->name('faq')->middleware('Maintenance');
$router->get('/contact', 'Site\SiteController@contact')->name('contact')->middleware('Maintenance');
$router->post('/contact', 'Site\SiteController@submitContact')->middleware(['VerifyCsrf', 'Throttle:enquiry']);
$router->get('/terms', 'Site\SiteController@terms')->name('terms');
$router->get('/privacy', 'Site\SiteController@privacy')->name('privacy');

// Template marketplace
$router->get('/templates', 'Site\TemplateGalleryController@index')->name('templates')->middleware('Maintenance');
$router->get('/templates/search', 'Site\TemplateGalleryController@search')->name('templates.search');
$router->get('/templates/preview/{code}', 'Site\TemplateGalleryController@preview')->name('templates.preview');
$router->get('/templates/{code}', 'Site\TemplateGalleryController@show')->name('templates.show');

// PWA + SEO
$router->get('/manifest.webmanifest', 'Site\SiteController@manifest');
$router->get('/service-worker.js', 'Site\SiteController@serviceWorker');
$router->get('/offline', 'Site\SiteController@offline');
$router->get('/robots.txt', 'Site\SiteController@robots');
$router->get('/sitemap.xml', 'Site\SiteController@sitemap');

// ---------------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------------
$router->get('/login', 'Auth\LoginController@show')->name('login')->middleware('Guest');
$router->post('/login', 'Auth\LoginController@login')->middleware(['Guest', 'VerifyCsrf', 'Throttle:login']);
$router->post('/logout', 'Auth\LoginController@logout')->name('logout')->middleware('VerifyCsrf');
$router->get('/register', 'Auth\RegisterController@show')->name('register')->middleware('Guest');
$router->post('/register', 'Auth\RegisterController@register')->middleware(['Guest', 'VerifyCsrf', 'Throttle:register']);
$router->get('/forgot-password', 'Auth\PasswordController@showForgot')->name('password.forgot')->middleware('Guest');
$router->post('/forgot-password', 'Auth\PasswordController@sendReset')->middleware(['Guest', 'VerifyCsrf', 'Throttle:forgot']);
$router->get('/reset-password/{token}', 'Auth\PasswordController@showReset')->name('password.reset')->middleware('Guest');
$router->post('/reset-password', 'Auth\PasswordController@resetPassword')->middleware(['Guest', 'VerifyCsrf', 'Throttle:forgot']);
$router->get('/verify-email/{token}', 'Auth\VerificationController@verify')->name('verify.email');
$router->post('/verify-email/resend', 'Auth\VerificationController@resend')->middleware(['Authenticate', 'VerifyCsrf', 'Throttle:forgot']);

// ---------------------------------------------------------------------------
// Customer panel
// ---------------------------------------------------------------------------
$router->group(['middleware' => ['Maintenance', 'Authenticate', 'RequirePermission:customer.access']], static function (Router $router): void {
    $router->get('/dashboard', 'Customer\DashboardController@index')->name('dashboard');
    $router->get('/onboarding', 'Customer\OnboardingController@index')->name('onboarding');
    $router->post('/onboarding', 'Customer\OnboardingController@store')->middleware('VerifyCsrf');

    // Cards
    $router->get('/cards', 'Customer\CardController@index')->name('cards');
    $router->get('/cards/create', 'Customer\CardController@create')->name('cards.create');
    $router->post('/cards', 'Customer\CardController@store')->middleware('VerifyCsrf');
    $router->get('/cards/{id}/editor', 'Customer\EditorController@index')->name('cards.editor');
    $router->post('/cards/{id}/profile', 'Customer\EditorController@saveProfile')->middleware('VerifyCsrf');
    $router->post('/cards/{id}/business', 'Customer\EditorController@saveBusiness')->middleware('VerifyCsrf');
    $router->post('/cards/{id}/contact', 'Customer\EditorController@saveContact')->middleware('VerifyCsrf');
    $router->post('/cards/{id}/social', 'Customer\EditorController@saveSocial')->middleware('VerifyCsrf');
    $router->post('/cards/{id}/seo', 'Customer\EditorController@saveSeo')->middleware('VerifyCsrf');
    $router->post('/cards/{id}/settings', 'Customer\EditorController@saveSettings')->middleware('VerifyCsrf');
    $router->post('/cards/{id}/sections', 'Customer\EditorController@saveSections')->middleware('VerifyCsrf');
    $router->post('/cards/{id}/media', 'Customer\EditorController@uploadMedia')->middleware(['VerifyCsrf', 'Throttle:upload']);
    $router->post('/cards/{id}/media/remove', 'Customer\EditorController@removeMedia')->middleware('VerifyCsrf');
    $router->post('/cards/{id}/publish', 'Customer\CardController@publish')->middleware('VerifyCsrf');
    $router->post('/cards/{id}/unpublish', 'Customer\CardController@unpublish')->middleware('VerifyCsrf');
    $router->post('/cards/{id}/duplicate', 'Customer\CardController@duplicate')->middleware('VerifyCsrf');
    $router->post('/cards/{id}/delete', 'Customer\CardController@destroy')->middleware('VerifyCsrf');
    $router->post('/cards/{id}/slug', 'Customer\CardController@updateSlug')->middleware('VerifyCsrf');
    $router->get('/cards/{id}/preview', 'Customer\CardController@preview')->name('cards.preview');

    // Design
    $router->get('/cards/{id}/design', 'Customer\DesignController@index')->name('cards.design');
    $router->post('/cards/{id}/design', 'Customer\DesignController@apply')->middleware('VerifyCsrf');
    $router->post('/cards/{id}/design/theme', 'Customer\DesignController@saveTheme')->middleware('VerifyCsrf');
    $router->post('/cards/{id}/design/reset', 'Customer\DesignController@resetTheme')->middleware('VerifyCsrf');

    // Services
    $router->get('/cards/{id}/services', 'Customer\ServiceController@index')->name('cards.services');
    $router->post('/cards/{id}/services', 'Customer\ServiceController@store')->middleware(['VerifyCsrf', 'Throttle:upload']);
    $router->post('/cards/{id}/services/{serviceId}', 'Customer\ServiceController@update')->middleware(['VerifyCsrf', 'Throttle:upload']);
    $router->post('/cards/{id}/services/{serviceId}/delete', 'Customer\ServiceController@destroy')->middleware('VerifyCsrf');
    $router->post('/cards/{id}/services/reorder', 'Customer\ServiceController@reorder')->middleware('VerifyCsrf');

    // Products
    $router->get('/cards/{id}/products', 'Customer\ProductController@index')->name('cards.products');
    $router->post('/cards/{id}/products', 'Customer\ProductController@store')->middleware(['VerifyCsrf', 'Throttle:upload']);
    $router->post('/cards/{id}/products/{productId}', 'Customer\ProductController@update')->middleware(['VerifyCsrf', 'Throttle:upload']);
    $router->post('/cards/{id}/products/{productId}/delete', 'Customer\ProductController@destroy')->middleware('VerifyCsrf');
    $router->post('/cards/{id}/products/reorder', 'Customer\ProductController@reorder')->middleware('VerifyCsrf');

    // Gallery
    $router->get('/cards/{id}/gallery', 'Customer\GalleryController@index')->name('cards.gallery');
    $router->post('/cards/{id}/gallery', 'Customer\GalleryController@store')->middleware(['VerifyCsrf', 'Throttle:upload']);
    $router->post('/cards/{id}/gallery/video', 'Customer\GalleryController@storeVideo')->middleware('VerifyCsrf');
    $router->post('/cards/{id}/gallery/{itemId}/delete', 'Customer\GalleryController@destroy')->middleware('VerifyCsrf');

    // QR + sharing
    $router->get('/cards/{id}/qr', 'Customer\QrController@index')->name('cards.qr');
    $router->post('/cards/{id}/qr', 'Customer\QrController@update')->middleware('VerifyCsrf');
    $router->get('/cards/{id}/qr/download', 'Customer\QrController@download')->name('cards.qr.download');

    // Analytics, leads, billing, account
    $router->get('/analytics', 'Customer\AnalyticsController@index')->name('analytics');
    $router->get('/analytics/{id}', 'Customer\AnalyticsController@card')->name('analytics.card');
    $router->get('/leads', 'Customer\LeadController@index')->name('leads');
    $router->get('/leads/{id}', 'Customer\LeadController@show')->name('leads.show');
    $router->post('/leads/{id}/status', 'Customer\LeadController@updateStatus')->middleware('VerifyCsrf');
    $router->post('/leads/{id}/delete', 'Customer\LeadController@destroy')->middleware('VerifyCsrf');
    $router->get('/leads/export/csv', 'Customer\LeadController@export')->name('leads.export');

    $router->get('/billing', 'Customer\BillingController@index')->name('billing');
    $router->get('/billing/checkout/{plan}', 'Customer\BillingController@checkout')->name('billing.checkout');
    $router->post('/billing/order', 'Customer\BillingController@createOrder')->middleware('VerifyCsrf');
    $router->post('/billing/verify', 'Customer\BillingController@verify')->middleware('VerifyCsrf');
    $router->get('/billing/success/{order}', 'Customer\BillingController@thankYou')->name('billing.success');
    $router->get('/billing/invoice/{id}', 'Customer\BillingController@invoice')->name('billing.invoice');

    $router->get('/account', 'Customer\AccountController@index')->name('account');
    $router->post('/account/profile', 'Customer\AccountController@updateProfile')->middleware(['VerifyCsrf', 'Throttle:upload']);
    $router->post('/account/password', 'Customer\AccountController@updatePassword')->middleware('VerifyCsrf');
    $router->post('/account/delete', 'Customer\AccountController@destroy')->middleware('VerifyCsrf');
    $router->post('/notifications/read', 'Customer\AccountController@markNotificationsRead')->middleware('VerifyCsrf');
});

// ---------------------------------------------------------------------------
// Reseller panel
// ---------------------------------------------------------------------------
$router->group(['prefix' => 'reseller', 'middleware' => ['Maintenance', 'Authenticate', 'RequirePermission:reseller.access']], static function (Router $router): void {
    $router->get('/', 'Reseller\DashboardController@index')->name('reseller.dashboard');
    $router->get('/customers', 'Reseller\CustomerController@index')->name('reseller.customers');
    $router->get('/customers/create', 'Reseller\CustomerController@create')->name('reseller.customers.create');
    $router->post('/customers', 'Reseller\CustomerController@store')->middleware('VerifyCsrf');
    $router->get('/customers/{id}', 'Reseller\CustomerController@show')->name('reseller.customers.show');
    $router->post('/customers/{id}/status', 'Reseller\CustomerController@updateStatus')->middleware('VerifyCsrf');
    $router->post('/customers/{id}/plan', 'Reseller\CustomerController@assignPlan')->middleware('VerifyCsrf');
    $router->post('/customers/{id}/password', 'Reseller\CustomerController@resetPassword')->middleware('VerifyCsrf');

    $router->get('/cards', 'Reseller\CardController@index')->name('reseller.cards');
    $router->get('/wallet', 'Reseller\WalletController@index')->name('reseller.wallet');
    $router->get('/orders', 'Reseller\WalletController@orders')->name('reseller.orders');
    $router->get('/branding', 'Reseller\BrandingController@index')->name('reseller.branding');
    $router->post('/branding', 'Reseller\BrandingController@update')->middleware(['VerifyCsrf', 'Throttle:upload']);
    $router->post('/branding/domain', 'Reseller\BrandingController@saveDomain')->middleware('VerifyCsrf');
    $router->post('/branding/domain/verify', 'Reseller\BrandingController@verifyDomain')->middleware('VerifyCsrf');
});

// ---------------------------------------------------------------------------
// Admin panel
// ---------------------------------------------------------------------------
$router->group(['prefix' => 'admin', 'middleware' => ['Authenticate', 'RequirePermission:admin.access']], static function (Router $router): void {
    $router->get('/', 'Admin\DashboardController@index')->name('admin.dashboard');
    $router->get('/chart/{metric}', 'Admin\DashboardController@chart')->name('admin.chart');

    // Users
    $router->get('/users', 'Admin\UserController@index')->name('admin.users')->middleware('RequirePermission:admin.users');
    $router->get('/users/create', 'Admin\UserController@create')->middleware('RequirePermission:admin.users');
    $router->post('/users', 'Admin\UserController@store')->middleware(['VerifyCsrf', 'RequirePermission:admin.users']);
    $router->get('/users/{id}', 'Admin\UserController@show')->name('admin.users.show')->middleware('RequirePermission:admin.users');
    $router->post('/users/{id}', 'Admin\UserController@update')->middleware(['VerifyCsrf', 'RequirePermission:admin.users']);
    $router->post('/users/{id}/status', 'Admin\UserController@updateStatus')->middleware(['VerifyCsrf', 'RequirePermission:admin.users']);
    $router->post('/users/{id}/password', 'Admin\UserController@resetPassword')->middleware(['VerifyCsrf', 'RequirePermission:admin.users']);
    $router->post('/users/{id}/plan', 'Admin\UserController@assignPlan')->middleware(['VerifyCsrf', 'RequirePermission:admin.users']);
    $router->post('/users/{id}/extend', 'Admin\UserController@extendSubscription')->middleware(['VerifyCsrf', 'RequirePermission:admin.users']);
    $router->post('/users/{id}/delete', 'Admin\UserController@destroy')->middleware(['VerifyCsrf', 'RequirePermission:admin.users']);
    $router->post('/users/{id}/impersonate', 'Admin\ImpersonationController@start')->middleware(['VerifyCsrf', 'RequirePermission:admin.impersonate']);

    // Cards
    $router->get('/cards', 'Admin\CardController@index')->name('admin.cards')->middleware('RequirePermission:admin.cards');
    $router->post('/cards/{id}/status', 'Admin\CardController@updateStatus')->middleware(['VerifyCsrf', 'RequirePermission:admin.cards']);
    $router->post('/cards/{id}/delete', 'Admin\CardController@destroy')->middleware(['VerifyCsrf', 'RequirePermission:admin.cards']);

    // Templates
    $router->get('/templates', 'Admin\TemplateController@index')->name('admin.templates')->middleware('RequirePermission:admin.templates');
    $router->get('/templates/create', 'Admin\TemplateController@create')->middleware('RequirePermission:admin.templates');
    $router->post('/templates', 'Admin\TemplateController@store')->middleware(['VerifyCsrf', 'RequirePermission:admin.templates']);
    $router->get('/templates/{id}/edit', 'Admin\TemplateController@edit')->name('admin.templates.edit')->middleware('RequirePermission:admin.templates');
    $router->post('/templates/{id}', 'Admin\TemplateController@update')->middleware(['VerifyCsrf', 'RequirePermission:admin.templates']);
    $router->post('/templates/{id}/toggle', 'Admin\TemplateController@toggle')->middleware(['VerifyCsrf', 'RequirePermission:admin.templates']);
    $router->post('/templates/{id}/duplicate', 'Admin\TemplateController@duplicate')->middleware(['VerifyCsrf', 'RequirePermission:admin.templates']);
    $router->post('/templates/{id}/delete', 'Admin\TemplateController@destroy')->middleware(['VerifyCsrf', 'RequirePermission:admin.templates']);
    $router->post('/templates/generate', 'Admin\TemplateController@generate')->middleware(['VerifyCsrf', 'RequirePermission:admin.templates']);
    $router->get('/categories', 'Admin\CategoryController@index')->name('admin.categories')->middleware('RequirePermission:admin.templates');
    $router->post('/categories', 'Admin\CategoryController@store')->middleware(['VerifyCsrf', 'RequirePermission:admin.templates']);
    $router->post('/categories/{id}', 'Admin\CategoryController@update')->middleware(['VerifyCsrf', 'RequirePermission:admin.templates']);
    $router->post('/categories/{id}/delete', 'Admin\CategoryController@destroy')->middleware(['VerifyCsrf', 'RequirePermission:admin.templates']);

    // Plans, orders, payments, invoices
    $router->get('/plans', 'Admin\PlanController@index')->name('admin.plans')->middleware('RequirePermission:admin.plans');
    $router->get('/plans/create', 'Admin\PlanController@create')->middleware('RequirePermission:admin.plans');
    $router->post('/plans', 'Admin\PlanController@store')->middleware(['VerifyCsrf', 'RequirePermission:admin.plans']);
    $router->get('/plans/{id}/edit', 'Admin\PlanController@edit')->name('admin.plans.edit')->middleware('RequirePermission:admin.plans');
    $router->post('/plans/{id}', 'Admin\PlanController@update')->middleware(['VerifyCsrf', 'RequirePermission:admin.plans']);
    $router->post('/plans/{id}/delete', 'Admin\PlanController@destroy')->middleware(['VerifyCsrf', 'RequirePermission:admin.plans']);

    $router->get('/orders', 'Admin\OrderController@index')->name('admin.orders')->middleware('RequirePermission:admin.orders');
    $router->get('/orders/{id}', 'Admin\OrderController@show')->name('admin.orders.show')->middleware('RequirePermission:admin.orders');
    $router->post('/orders/{id}/refund', 'Admin\OrderController@refund')->middleware(['VerifyCsrf', 'RequirePermission:admin.orders']);
    $router->get('/payments', 'Admin\OrderController@payments')->name('admin.payments')->middleware('RequirePermission:admin.orders');
    $router->get('/invoices', 'Admin\OrderController@invoices')->name('admin.invoices')->middleware('RequirePermission:admin.orders');
    $router->get('/invoices/{id}', 'Admin\OrderController@invoice')->name('admin.invoices.show')->middleware('RequirePermission:admin.orders');

    // Resellers
    $router->get('/resellers', 'Admin\ResellerController@index')->name('admin.resellers')->middleware('RequirePermission:admin.resellers');
    $router->get('/resellers/create', 'Admin\ResellerController@create')->middleware('RequirePermission:admin.resellers');
    $router->post('/resellers', 'Admin\ResellerController@store')->middleware(['VerifyCsrf', 'RequirePermission:admin.resellers']);
    $router->get('/resellers/{id}', 'Admin\ResellerController@show')->name('admin.resellers.show')->middleware('RequirePermission:admin.resellers');
    $router->post('/resellers/{id}', 'Admin\ResellerController@update')->middleware(['VerifyCsrf', 'RequirePermission:admin.resellers']);
    $router->post('/resellers/{id}/wallet', 'Admin\ResellerController@adjustWallet')->middleware(['VerifyCsrf', 'RequirePermission:admin.resellers']);
    $router->post('/resellers/{id}/domain', 'Admin\ResellerController@verifyDomain')->middleware(['VerifyCsrf', 'RequirePermission:admin.resellers']);

    // Leads
    $router->get('/leads', 'Admin\LeadController@index')->name('admin.leads')->middleware('RequirePermission:admin.leads');

    // Settings
    $router->get('/settings', 'Admin\SettingsController@index')->name('admin.settings')->middleware('RequirePermission:admin.settings');
    $router->post('/settings/{group}', 'Admin\SettingsController@update')->middleware(['VerifyCsrf', 'RequirePermission:admin.settings']);
    $router->post('/settings/test/razorpay', 'Admin\SettingsController@testRazorpay')->middleware(['VerifyCsrf', 'RequirePermission:admin.settings']);
    $router->post('/settings/test/smtp', 'Admin\SettingsController@testSmtp')->middleware(['VerifyCsrf', 'RequirePermission:admin.settings']);

    // Updates / backups / logs
    $router->get('/updates', 'Admin\UpdateController@index')->name('admin.updates')->middleware('RequirePermission:admin.updates');
    $router->post('/updates/settings', 'Admin\UpdateController@saveSettings')->middleware(['VerifyCsrf', 'RequirePermission:admin.updates']);
    $router->post('/updates/check', 'Admin\UpdateController@check')->middleware(['VerifyCsrf', 'RequirePermission:admin.updates']);
    $router->post('/updates/run', 'Admin\UpdateController@run')->middleware(['VerifyCsrf', 'RequirePermission:admin.updates']);
    $router->post('/updates/rollback', 'Admin\UpdateController@rollback')->middleware(['VerifyCsrf', 'RequirePermission:admin.updates']);
    $router->get('/updates/history', 'Admin\UpdateController@history')->name('admin.updates.history')->middleware('RequirePermission:admin.updates');
    $router->get('/updates/history/{id}', 'Admin\UpdateController@show')->name('admin.updates.show')->middleware('RequirePermission:admin.updates');
    $router->post('/updates/maintenance', 'Admin\UpdateController@toggleMaintenance')->middleware(['VerifyCsrf', 'RequirePermission:admin.updates']);

    $router->get('/backups', 'Admin\BackupController@index')->name('admin.backups')->middleware('RequirePermission:admin.backups');
    $router->post('/backups', 'Admin\BackupController@store')->middleware(['VerifyCsrf', 'RequirePermission:admin.backups']);
    $router->post('/backups/{id}/restore', 'Admin\BackupController@restore')->middleware(['VerifyCsrf', 'RequirePermission:admin.backups']);
    $router->post('/backups/{id}/verify', 'Admin\BackupController@verify')->middleware(['VerifyCsrf', 'RequirePermission:admin.backups']);
    $router->get('/backups/{id}/download/{type}', 'Admin\BackupController@download')->name('admin.backups.download')->middleware('RequirePermission:admin.backups');
    $router->post('/backups/{id}/delete', 'Admin\BackupController@destroy')->middleware(['VerifyCsrf', 'RequirePermission:admin.backups']);

    $router->get('/logs', 'Admin\LogController@index')->name('admin.logs')->middleware('RequirePermission:admin.logs');
    $router->get('/logs/audit', 'Admin\LogController@audit')->name('admin.logs.audit')->middleware('RequirePermission:admin.logs');
    $router->get('/logs/file/{name}', 'Admin\LogController@file')->name('admin.logs.file')->middleware('RequirePermission:admin.logs');
    $router->get('/health', 'Admin\LogController@health')->name('admin.health')->middleware('RequirePermission:admin.logs');
    $router->post('/maintenance/run/{task}', 'Admin\LogController@runTask')->middleware(['VerifyCsrf', 'RequirePermission:admin.settings']);
});

// Exiting impersonation must stay reachable while acting as the customer.
$router->post('/impersonate/stop', 'Admin\ImpersonationController@stop')->name('impersonate.stop')->middleware(['Authenticate', 'VerifyCsrf']);

// ---------------------------------------------------------------------------
// Webhooks + internal API
// ---------------------------------------------------------------------------
$router->post('/webhooks/razorpay', 'Api\WebhookController@razorpay')->name('webhooks.razorpay');

$router->group(['prefix' => 'api/v1', 'middleware' => ['Throttle:api']], static function (Router $router): void {
    $router->post('/track', 'Api\TrackController@store')->name('api.track')->middleware('Throttle:track');
    $router->get('/slug/available', 'Api\UtilityController@slugAvailable');
    $router->get('/templates', 'Api\TemplateApiController@index');
    $router->get('/cards/{slug}', 'Api\CardApiController@show');
    $router->get('/me/cards', 'Api\CardApiController@mine')->middleware('Authenticate');
    $router->post('/editor/{id}/autosave', 'Api\EditorApiController@autosave')->middleware(['Authenticate', 'VerifyCsrf']);
    $router->get('/editor/{id}/preview', 'Api\EditorApiController@preview')->middleware('Authenticate');
});

// ---------------------------------------------------------------------------
// Public digital cards  (registered last: /{slug} is the catch-all)
// ---------------------------------------------------------------------------
$router->get('/card/{slug}', 'Site\CardController@show')->name('card.show');
$router->get('/card/{slug}/vcard', 'Site\CardController@vcard')->name('card.vcard');
$router->get('/card/{slug}/qr', 'Site\CardController@qr')->name('card.qr');
$router->post('/card/{slug}/enquiry', 'Site\CardController@enquiry')->name('card.enquiry')->middleware(['VerifyCsrf', 'Throttle:enquiry']);
$router->get('/{slug:[a-z0-9][a-z0-9\-]{1,98}}', 'Site\CardController@showDirect')->name('card.direct');
