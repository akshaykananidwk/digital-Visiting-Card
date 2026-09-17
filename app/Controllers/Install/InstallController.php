<?php

declare(strict_types=1);

namespace App\Controllers\Install;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Url;
use App\Services\Installer;
use App\Services\TemplateFactory;
use Throwable;

/**
 * Step-by-step installation wizard. Nothing here is reachable once
 * storage/installed.lock exists (enforced by the kernel).
 */
final class InstallController extends Controller
{
    private const STEPS = [
        'welcome'      => ['Welcome', 1],
        'requirements' => ['Server requirements', 2],
        'database'     => ['Database', 3],
        'migrate'      => ['Create tables', 4],
        'admin'        => ['Administrator', 5],
        'site'         => ['Site settings', 6],
        'payment'      => ['Payments', 7],
        'designs'      => ['Design library', 8],
        'security'     => ['Security check', 9],
        'complete'     => ['Finish', 10],
    ];

    public function welcome(): Response
    {
        return $this->step('welcome', [
            'php'      => PHP_VERSION,
            'version'  => APP_VERSION,
        ]);
    }

    public function requirements(): Response
    {
        $report = Installer::requirements();

        return $this->step('requirements', ['report' => $report]);
    }

    public function database(): Response
    {
        return $this->step('database', [
            'values' => Session::get('install.database', [
                'host'     => '127.0.0.1',
                'port'     => 3306,
                'database' => '',
                'username' => '',
                'password' => '',
                'prefix'   => '',
            ]),
            'app_url' => Session::get('install.app_url', $this->guessAppUrl()),
        ]);
    }

    public function testConnection(): Response
    {
        $config = $this->databaseInput();
        $result = Installer::testDatabase($config);

        return $this->json($result);
    }

    public function saveDatabase(): Response
    {
        $config = $this->databaseInput();
        $appUrl = rtrim($this->request->string('app_url'), '/');

        if ($appUrl === '' || filter_var($appUrl, FILTER_VALIDATE_URL) === false) {
            $appUrl = $this->guessAppUrl();
        }

        $result = Installer::testDatabase($config);
        if (!$result['success']) {
            $this->error($result['message']);
            Session::put('install.database', $config);

            return $this->redirect('install/database');
        }

        $key = (string) Env::get('APP_KEY', '');
        if ($key === '') {
            $key = Installer::generateAppKey();
        }

        $written = Installer::writeEnv([
            'APP_NAME'        => 'Digital Visiting Card',
            'APP_ENV'         => 'production',
            'APP_DEBUG'       => 'false',
            'APP_URL'         => $appUrl,
            'APP_KEY'         => $key,
            'APP_TIMEZONE'    => 'Asia/Kolkata',
            'APP_FORCE_HTTPS' => str_starts_with($appUrl, 'https://') ? 'true' : 'false',
            'DB_HOST'         => $config['host'],
            'DB_PORT'         => (string) $config['port'],
            'DB_DATABASE'     => $config['database'],
            'DB_USERNAME'     => $config['username'],
            'DB_PASSWORD'     => $config['password'],
            'DB_PREFIX'       => $config['prefix'],
            'SESSION_SECURE'  => str_starts_with($appUrl, 'https://') ? 'true' : 'false',
        ]);

        if (!$written) {
            $this->error('Could not write the .env file. Make sure the project root is writable (chmod 755) and try again.');
            Session::put('install.database', $config);

            return $this->redirect('install/database');
        }

        // Re-point the runtime at the new configuration.
        Config::set('database', $config + ['charset' => 'utf8mb4', 'socket' => '']);
        Config::set('app.url', $appUrl);
        Database::reset();

        Session::put('install.database', $config);
        Session::put('install.app_url', $appUrl);
        $this->success('Database connection saved. ' . $result['message']);

        return $this->redirect('install/migrate');
    }

    public function migrate(): Response
    {
        if (($redirect = $this->bootDatabase()) !== null) {
            return $redirect;
        }

        $migrator = new \App\Core\Migrator(Database::instance());
        $pending = [];
        $error = null;

        try {
            $pending = $migrator->pending();
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return $this->step('migrate', [
            'pending' => $pending,
            'applied' => $error === null ? $migrator->applied() : [],
            'error'   => $error,
        ]);
    }

    public function runMigrations(): Response
    {
        if (($redirect = $this->bootDatabase()) !== null) {
            return $redirect;
        }

        try {
            $result = Installer::migrate();
            if (!$result['success']) {
                $this->error('Migration failed: ' . (string) $result['error']);

                return $this->redirect('install/migrate');
            }

            Installer::seedDefaults();
            Settings::flush();

            $this->success(count($result['ran']) . ' migration(s) applied and default data created.');

            return $this->redirect('install/admin');
        } catch (Throwable $e) {
            Logger::exception($e, 'install');
            $this->error('Could not create the tables: ' . $e->getMessage());

            return $this->redirect('install/migrate');
        }
    }

    public function admin(): Response
    {
        if (($redirect = $this->bootDatabase()) !== null) {
            return $redirect;
        }

        return $this->step('admin', []);
    }

    public function saveAdmin(): Response
    {
        if (($redirect = $this->bootDatabase()) !== null) {
            return $redirect;
        }

        $data = $this->validate([
            'name'     => 'required|string|min:2|max:150',
            'email'    => 'required|email',
            'password' => 'required|password|confirmed',
            'phone'    => 'nullable|phone',
        ]);

        try {
            $admin = Installer::createAdmin(
                (string) $data['name'],
                (string) $data['email'],
                (string) $data['password'],
                (string) ($data['phone'] ?? '')
            );

            Session::put('install.admin_email', $admin['email']);
            $this->success('Administrator account created.');

            return $this->redirect('install/site');
        } catch (Throwable $e) {
            Logger::exception($e, 'install');
            $this->error('Could not create the administrator: ' . $e->getMessage());

            return $this->redirect('install/admin');
        }
    }

    public function site(): Response
    {
        if (($redirect = $this->bootDatabase()) !== null) {
            return $redirect;
        }

        return $this->step('site', ['settings' => Settings::group('general')]);
    }

    public function saveSite(): Response
    {
        if (($redirect = $this->bootDatabase()) !== null) {
            return $redirect;
        }

        $data = $this->validate([
            'site_name'        => 'required|string|max:100',
            'site_tagline'     => 'nullable|string|max:190',
            'support_email'    => 'nullable|email',
            'support_phone'    => 'nullable|phone',
            'support_whatsapp' => 'nullable|phone',
            'currency_symbol'  => 'nullable|string|max:5',
        ]);

        Settings::setMany([
            'site_name'        => $data['site_name'],
            'site_tagline'     => $data['site_tagline'] ?? '',
            'support_email'    => $data['support_email'] ?? '',
            'support_phone'    => $data['support_phone'] ?? '',
            'support_whatsapp' => $data['support_whatsapp'] ?? '',
            'currency_symbol'  => $data['currency_symbol'] ?: '₹',
        ], 'general');

        Installer::writeEnv(['APP_NAME' => (string) $data['site_name']]);

        $this->success('Site settings saved.');

        return $this->redirect('install/payment');
    }

    public function payment(): Response
    {
        if (($redirect = $this->bootDatabase()) !== null) {
            return $redirect;
        }

        return $this->step('payment', ['settings' => Settings::group('payment')]);
    }

    public function savePayment(): Response
    {
        if (($redirect = $this->bootDatabase()) !== null) {
            return $redirect;
        }

        $enabled = $this->request->bool('razorpay_enabled');
        $keyId = $this->request->string('razorpay_key_id');
        $keySecret = $this->request->string('razorpay_key_secret');

        if ($enabled && ($keyId === '' || $keySecret === '')) {
            $this->error('Enter both the Razorpay Key ID and Key Secret, or leave payments disabled for now.');

            return $this->redirect('install/payment');
        }

        Settings::setMany([
            'razorpay_enabled'        => $enabled,
            'razorpay_key_id'         => $keyId,
            'gst_enabled'             => $this->request->bool('gst_enabled'),
            'gst_rate'                => max(0, min(28, $this->request->float('gst_rate', 18))),
            'gst_number'              => $this->request->string('gst_number'),
            'gst_state'               => $this->request->string('gst_state'),
        ], 'payment');

        if ($keySecret !== '') {
            Settings::set('razorpay_key_secret', $keySecret, 'payment');
        }
        $webhookSecret = $this->request->string('razorpay_webhook_secret');
        if ($webhookSecret !== '') {
            Settings::set('razorpay_webhook_secret', $webhookSecret, 'payment');
        }

        $this->success('Payment settings saved.');

        return $this->redirect('install/designs');
    }

    public function designs(): Response
    {
        if (($redirect = $this->bootDatabase()) !== null) {
            return $redirect;
        }

        $templates = new \App\Models\Template();

        return $this->step('designs', [
            'existing'   => $templates->count([]),
            'categories' => count(TemplateFactory::CATEGORIES),
            'estimate'   => TemplateFactory::catalogueSize(24),
        ]);
    }

    public function generateDesigns(): Response
    {
        if (($redirect = $this->bootDatabase()) !== null) {
            return $redirect;
        }

        $perCategory = max(4, min(60, $this->request->int('per_category', 24)));

        try {
            @set_time_limit(300);
            $result = (new TemplateFactory())->generate($perCategory);
            $this->success(sprintf(
                '%d designs generated across %d categories (%d total in the library).',
                $result['created'],
                count(TemplateFactory::CATEGORIES),
                $result['total']
            ));

            return $this->redirect('install/security');
        } catch (Throwable $e) {
            Logger::exception($e, 'install');
            $this->error('Design generation failed: ' . $e->getMessage());

            return $this->redirect('install/designs');
        }
    }

    public function security(): Response
    {
        if (($redirect = $this->bootDatabase()) !== null) {
            return $redirect;
        }

        return $this->step('security', ['report' => Installer::securityReport()]);
    }

    public function finish(): Response
    {
        if (($redirect = $this->bootDatabase()) !== null) {
            return $redirect;
        }

        try {
            $migrator = new \App\Core\Migrator(Database::instance());
            if ($migrator->pending() !== []) {
                $this->error('There are still pending migrations. Go back to the "Create tables" step.');

                return $this->redirect('install/migrate');
            }

            $admins = (new \App\Models\User())->count(['role' => 'super_admin']);
            if ($admins === 0) {
                $this->error('No administrator account exists yet.');

                return $this->redirect('install/admin');
            }

            if (!Installer::lock()) {
                $this->error('Could not write storage/installed.lock — check that the storage folder is writable.');

                return $this->redirect('install/security');
            }

            \App\Core\AuditLog::record('app.installed', 'system', null, ['version' => APP_VERSION]);
            Session::put('install.finished', true);

            return $this->redirect('install/complete');
        } catch (Throwable $e) {
            Logger::exception($e, 'install');
            $this->error('Could not complete the installation: ' . $e->getMessage());

            return $this->redirect('install/security');
        }
    }

    public function complete(): Response
    {
        // The kernel redirects away once the lock exists, so this renders
        // only in the brief window right after finishing.
        return $this->step('complete', [
            'admin_email' => Session::get('install.admin_email', ''),
            'login_url'   => Url::to('login'),
        ]);
    }

    // ------------------------------------------------------------ Helpers --

    /** @param array<string,mixed> $data */
    private function step(string $step, array $data): Response
    {
        return $this->render('install.' . $step, $data + [
            'steps'       => self::STEPS,
            'currentStep' => $step,
            'stepNumber'  => self::STEPS[$step][1] ?? 1,
            'stepTitle'   => self::STEPS[$step][0] ?? '',
            'totalSteps'  => count(self::STEPS),
        ]);
    }

    /** @return array<string,mixed> */
    private function databaseInput(): array
    {
        return [
            'host'     => $this->request->string('host', '127.0.0.1') ?: '127.0.0.1',
            'port'     => max(1, min(65535, $this->request->int('port', 3306))),
            'database' => $this->request->string('database'),
            'username' => $this->request->string('username'),
            'password' => (string) $this->request->input('password', ''),
            'prefix'   => preg_replace('/[^a-zA-Z0-9_]/', '', $this->request->string('prefix')) ?? '',
            'charset'  => 'utf8mb4',
            'socket'   => '',
        ];
    }

    /**
     * After the .env is written the runtime may still hold the old (empty)
     * configuration, so re-point it before touching the database.
     */
    private function bootDatabase(): ?Response
    {
        $config = Session::get('install.database');
        if (is_array($config) && ($config['database'] ?? '') !== '') {
            Config::set('database', $config + ['charset' => 'utf8mb4', 'socket' => '']);
        }
        if (Env::get('DB_DATABASE', '') === '' && is_array($config)) {
            foreach (['DB_HOST' => 'host', 'DB_PORT' => 'port', 'DB_DATABASE' => 'database', 'DB_USERNAME' => 'username', 'DB_PASSWORD' => 'password'] as $env => $key) {
                Env::set($env, (string) ($config[$key] ?? ''));
            }
        }

        try {
            Database::instance()->pdo();
            Settings::load(true);
        } catch (Throwable $e) {
            $this->error('Database connection lost: ' . $e->getMessage() . ' — please re-enter the database details.');

            return $this->redirect('install/database');
        }

        return null;
    }

    private function guessAppUrl(): string
    {
        $scheme = $this->request->isSecure() ? 'https' : 'http';
        $base = Url::basePath();

        return $scheme . '://' . $this->request->host() . $base;
    }
}
