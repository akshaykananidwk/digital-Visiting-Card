<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Http;
use App\Core\Migrator;
use App\Core\Settings;
use App\Core\Url;
use Throwable;

/**
 * Post-update / on-demand health check.
 *
 * Every check returns pass / warn / fail. A single `fail` marks the system
 * unhealthy, which is what triggers an automatic rollback after an update.
 */
final class HealthCheck
{
    /** Tables that must exist for the application to function. */
    private const REQUIRED_TABLES = [
        'users', 'roles', 'permissions', 'role_permissions', 'plans', 'subscriptions',
        'orders', 'payments', 'invoices', 'resellers', 'wallet_transactions',
        'templates', 'template_categories', 'cards', 'card_services', 'card_products',
        'card_gallery', 'card_social_links', 'card_sections', 'card_views', 'card_events',
        'card_daily_stats', 'leads', 'qr_codes', 'settings', 'notifications', 'audit_logs',
        'update_logs', 'backups', 'rate_limits', 'migrations',
    ];

    /** Files without which the application cannot boot. */
    private const REQUIRED_FILES = [
        'index.php', 'app/bootstrap.php', 'app/version.php', 'config/routes.php',
        'config/app.php', 'config/database.php', 'app/Core/App.php', 'app/Core/Database.php',
        'assets/css/app.css', 'assets/css/card.css',
    ];

    /**
     * @param bool $withEndpoints Also probe the public HTTP endpoints.
     * @return array{healthy:bool,checks:array<int,array{name:string,status:string,message:string}>,failed:array<int,string>,ran_at:string}
     */
    public function run(bool $withEndpoints = false): array
    {
        $checks = [];

        $checks[] = $this->phpRuntime();
        $checks[] = $this->extensions();
        $checks[] = $this->databaseConnection();
        $checks[] = $this->requiredTables();
        $checks[] = $this->pendingMigrations();
        $checks[] = $this->requiredFiles();
        $checks[] = $this->configuration();
        $checks[] = $this->storageWritable();
        $checks[] = $this->settingsReadable();
        $checks[] = $this->adminExists();
        $checks[] = $this->templateLibrary();
        $checks[] = $this->diskSpace();

        if ($withEndpoints) {
            foreach ($this->endpoints() as $check) {
                $checks[] = $check;
            }
        }

        $failed = [];
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $failed[] = $check['name'];
            }
        }

        return [
            'healthy' => $failed === [],
            'checks'  => $checks,
            'failed'  => $failed,
            'ran_at'  => now(),
        ];
    }

    /** @return array{name:string,status:string,message:string} */
    private function phpRuntime(): array
    {
        $ok = version_compare(PHP_VERSION, Installer::MIN_PHP, '>=');

        return $this->result('PHP runtime', $ok ? 'pass' : 'fail', 'PHP ' . PHP_VERSION . ($ok ? '' : ' (requires ' . Installer::MIN_PHP . '+)'));
    }

    /** @return array{name:string,status:string,message:string} */
    private function extensions(): array
    {
        $missing = [];
        foreach (Installer::REQUIRED_EXTENSIONS as $extension) {
            if (!extension_loaded($extension)) {
                $missing[] = $extension;
            }
        }

        return $this->result(
            'PHP extensions',
            $missing === [] ? 'pass' : 'fail',
            $missing === [] ? 'All required extensions present' : 'Missing: ' . implode(', ', $missing)
        );
    }

    /** @return array{name:string,status:string,message:string} */
    private function databaseConnection(): array
    {
        try {
            $db = Database::instance();
            $value = $db->scalar('SELECT 1');

            return $this->result('Database connection', (int) $value === 1 ? 'pass' : 'fail', 'MySQL ' . $db->serverVersion());
        } catch (Throwable $e) {
            return $this->result('Database connection', 'fail', $e->getMessage());
        }
    }

    /** @return array{name:string,status:string,message:string} */
    private function requiredTables(): array
    {
        try {
            $db = Database::instance();
            $existing = array_flip($db->listTables());
            $missing = [];
            foreach (self::REQUIRED_TABLES as $table) {
                if (!isset($existing[$db->table($table)])) {
                    $missing[] = $table;
                }
            }

            return $this->result(
                'Required tables',
                $missing === [] ? 'pass' : 'fail',
                $missing === [] ? count(self::REQUIRED_TABLES) . ' tables present' : 'Missing: ' . implode(', ', array_slice($missing, 0, 6)) . (count($missing) > 6 ? '…' : '')
            );
        } catch (Throwable $e) {
            return $this->result('Required tables', 'fail', $e->getMessage());
        }
    }

    /** @return array{name:string,status:string,message:string} */
    private function pendingMigrations(): array
    {
        try {
            $pending = (new Migrator(Database::instance()))->pending();

            // A pending migration means the schema is behind the code, not
            // that the site is down — the update pipeline fails explicitly
            // when a migration errors, so this stays a warning. Treating it
            // as fatal would block a rollback that actually succeeded.
            return $this->result(
                'Database migrations',
                $pending === [] ? 'pass' : 'warn',
                $pending === [] ? 'Schema up to date' : count($pending) . ' migration(s) pending — run them from the CLI or the updater'
            );
        } catch (Throwable $e) {
            return $this->result('Database migrations', 'fail', $e->getMessage());
        }
    }

    /** @return array{name:string,status:string,message:string} */
    private function requiredFiles(): array
    {
        $missing = [];
        foreach (self::REQUIRED_FILES as $file) {
            if (!is_file(BASE_PATH . '/' . $file)) {
                $missing[] = $file;
            }
        }

        return $this->result(
            'Application files',
            $missing === [] ? 'pass' : 'fail',
            $missing === [] ? count(self::REQUIRED_FILES) . ' core files present' : 'Missing: ' . implode(', ', $missing)
        );
    }

    /** @return array{name:string,status:string,message:string} */
    private function configuration(): array
    {
        $problems = [];
        if ((string) Env::get('APP_KEY', '') === '') {
            $problems[] = 'APP_KEY missing';
        }
        if ((string) Env::get('DB_DATABASE', '') === '') {
            $problems[] = 'DB_DATABASE missing';
        }
        if (!is_file(BASE_PATH . '/.env')) {
            $problems[] = '.env missing';
        }

        return $this->result(
            'Configuration',
            $problems === [] ? 'pass' : 'fail',
            $problems === [] ? 'Environment configured' : implode(', ', $problems)
        );
    }

    /** @return array{name:string,status:string,message:string} */
    private function storageWritable(): array
    {
        $notWritable = [];
        foreach (['/storage', '/storage/logs', '/storage/cache', '/storage/backups', '/uploads'] as $path) {
            $absolute = BASE_PATH . $path;
            if (!is_dir($absolute) || !is_writable($absolute)) {
                $notWritable[] = $path;
            }
        }

        return $this->result(
            'Writable directories',
            $notWritable === [] ? 'pass' : 'fail',
            $notWritable === [] ? 'storage and uploads writable' : 'Not writable: ' . implode(', ', $notWritable)
        );
    }

    /** @return array{name:string,status:string,message:string} */
    private function settingsReadable(): array
    {
        try {
            Settings::load(true);
            $name = (string) (Settings::get('site_name') ?? '');

            return $this->result('Settings store', $name !== '' ? 'pass' : 'warn', $name !== '' ? 'Settings loaded' : 'Settings table is empty');
        } catch (Throwable $e) {
            return $this->result('Settings store', 'fail', $e->getMessage());
        }
    }

    /** @return array{name:string,status:string,message:string} */
    private function adminExists(): array
    {
        try {
            $count = (new \App\Models\User())->count(['role' => 'super_admin', 'status' => 'active']);

            return $this->result('Administrator account', $count > 0 ? 'pass' : 'fail', $count > 0 ? $count . ' active super admin(s)' : 'No active administrator');
        } catch (Throwable $e) {
            return $this->result('Administrator account', 'fail', $e->getMessage());
        }
    }

    /** @return array{name:string,status:string,message:string} */
    private function templateLibrary(): array
    {
        try {
            $count = (new \App\Models\Template())->count(['is_active' => 1]);

            return $this->result(
                'Design library',
                $count > 0 ? 'pass' : 'warn',
                $count . ' active design(s)' . ($count === 0 ? ' — run templates:generate' : '')
            );
        } catch (Throwable $e) {
            return $this->result('Design library', 'fail', $e->getMessage());
        }
    }

    /** @return array{name:string,status:string,message:string} */
    private function diskSpace(): array
    {
        $free = @disk_free_space(BASE_PATH);
        if ($free === false) {
            return $this->result('Disk space', 'warn', 'Could not determine free space');
        }

        $status = $free < 52_428_800 ? 'fail' : ($free < 209_715_200 ? 'warn' : 'pass');

        return $this->result('Disk space', $status, human_size((float) $free) . ' free');
    }

    /**
     * Probe the login, admin and public card endpoints over HTTP.
     *
     * @return array<int,array{name:string,status:string,message:string}>
     */
    private function endpoints(): array
    {
        $checks = [];
        $base = rtrim((string) Config::get('app.url', ''), '/');

        if ($base === '' || !function_exists('curl_init')) {
            $checks[] = $this->result('HTTP endpoints', 'warn', 'Skipped (APP_URL or cURL unavailable)');

            return $checks;
        }

        $targets = [
            'Home page'     => ['/', [200, 302, 503]],
            'Login page'    => ['/login', [200, 302]],
            'Admin panel'   => ['/admin', [200, 302, 401, 403]],
            'Sitemap'       => ['/sitemap.xml', [200, 404]],
        ];

        $card = (new \App\Models\Card())->firstWhere(['status' => 'published']);
        if ($card !== null) {
            $targets['Public card'] = ['/card/' . (string) $card['slug'], [200]];
        }

        foreach ($targets as $name => [$path, $acceptable]) {
            $response = Http::get($base . $path, ['User-Agent' => 'DVC-HealthCheck'], 12);
            $ok = in_array($response['status'], $acceptable, true);
            $checks[] = $this->result(
                $name,
                $ok ? 'pass' : 'fail',
                $response['status'] > 0 ? 'HTTP ' . $response['status'] : ($response['error'] !== '' ? $response['error'] : 'No response')
            );
        }

        return $checks;
    }

    /** @return array{name:string,status:string,message:string} */
    private function result(string $name, string $status, string $message): array
    {
        return ['name' => $name, 'status' => $status, 'message' => $message];
    }
}
