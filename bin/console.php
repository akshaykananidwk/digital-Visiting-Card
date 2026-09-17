<?php

declare(strict_types=1);

/**
 * Command line utility.
 *
 *   php bin/console.php install --db-name=... --admin-email=...
 *   php bin/console.php migrate
 *   php bin/console.php seed
 *   php bin/console.php templates:generate [--per-category=24]
 *   php bin/console.php subscriptions:expire
 *   php bin/console.php health
 *   php bin/console.php backup
 *   php bin/console.php maintenance:on|off
 *   php bin/console.php cleanup
 */

if (PHP_SAPI !== 'cli') {
    exit("This script may only be run from the command line.\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Migrator;
use App\Core\Settings;
use App\Middleware\Maintenance;
use App\Services\BackupService;
use App\Services\HealthCheck;
use App\Services\Installer;
use App\Services\SubscriptionService;
use App\Services\TemplateFactory;

$argv = $_SERVER['argv'];
$command = $argv[1] ?? 'help';

/** @var array<string,string> $options */
$options = [];
foreach (array_slice($argv, 2) as $argument) {
    if (preg_match('/^--([a-z0-9\-_]+)(?:=(.*))?$/i', $argument, $matches) === 1) {
        $options[$matches[1]] = $matches[2] ?? '1';
    }
}

$out = static function (string $message, string $type = 'info'): void {
    $colours = ['info' => "\033[0;36m", 'ok' => "\033[0;32m", 'warn' => "\033[0;33m", 'error' => "\033[0;31m"];
    $prefix = $colours[$type] ?? '';
    echo $prefix . $message . "\033[0m\n";
};

try {
    switch ($command) {
        case 'install':
            $config = [
                'host'     => $options['db-host'] ?? '127.0.0.1',
                'port'     => (int) ($options['db-port'] ?? 3306),
                'database' => $options['db-name'] ?? '',
                'username' => $options['db-user'] ?? 'root',
                'password' => $options['db-pass'] ?? '',
                'prefix'   => $options['db-prefix'] ?? '',
                'charset'  => 'utf8mb4',
                'socket'   => '',
            ];

            if ($config['database'] === '') {
                $out('--db-name is required.', 'error');
                exit(1);
            }

            $out('Testing the database connection…');
            $test = Installer::testDatabase($config);
            if (!$test['success']) {
                $out($test['message'], 'error');
                exit(1);
            }
            $out($test['message'], 'ok');

            $appUrl = rtrim($options['url'] ?? 'http://localhost', '/');
            $written = Installer::writeEnv([
                'APP_NAME'        => $options['name'] ?? 'Digital Visiting Card',
                'APP_ENV'         => $options['env'] ?? 'production',
                'APP_DEBUG'       => ($options['debug'] ?? '0') === '1' ? 'true' : 'false',
                'APP_URL'         => $appUrl,
                'APP_KEY'         => (string) (App\Core\Env::get('APP_KEY', '')) ?: Installer::generateAppKey(),
                'APP_TIMEZONE'    => $options['timezone'] ?? 'Asia/Kolkata',
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
                $out('Could not write the .env file.', 'error');
                exit(1);
            }
            $out('.env written.', 'ok');

            Config::set('database', $config);
            Config::set('app.url', $appUrl);
            Database::reset();

            $out('Running migrations…');
            $result = Installer::migrate();
            if (!$result['success']) {
                $out((string) $result['error'], 'error');
                exit(1);
            }
            $out(count($result['ran']) . ' migration(s) applied.', 'ok');

            $out('Seeding default data…');
            Installer::seedDefaults();
            Settings::flush();
            $out('Roles, permissions, plans and settings created.', 'ok');

            $adminEmail = $options['admin-email'] ?? '';
            $adminPassword = $options['admin-password'] ?? '';
            if ($adminEmail !== '' && $adminPassword !== '') {
                Installer::createAdmin($options['admin-name'] ?? 'Administrator', $adminEmail, $adminPassword, $options['admin-phone'] ?? '');
                $out('Administrator created: ' . $adminEmail, 'ok');
            } else {
                $out('Skipped admin creation (pass --admin-email and --admin-password).', 'warn');
            }

            if (($options['templates'] ?? '1') === '1') {
                $perCategory = (int) ($options['per-category'] ?? 24);
                $out('Generating the design library (' . $perCategory . ' per category)…');
                $generated = (new TemplateFactory())->generate($perCategory);
                $out($generated['created'] . ' designs created (' . $generated['total'] . ' total).', 'ok');
            }

            if (($options['lock'] ?? '1') === '1') {
                Installer::lock();
                $out('Installation locked.', 'ok');
            }

            $out('Installation complete. Sign in at ' . $appUrl . '/login', 'ok');
            break;

        case 'migrate':
            $migrator = new Migrator(Database::instance());
            $pending = $migrator->pending();
            if ($pending === []) {
                $out('Nothing to migrate.', 'ok');
                break;
            }
            $out(count($pending) . ' pending migration(s).');
            $result = $migrator->run();
            foreach ($migrator->log() as $line) {
                $out('  ' . $line);
            }
            $out($result['success'] ? 'Migrations complete.' : 'Migration failed: ' . (string) $result['error'], $result['success'] ? 'ok' : 'error');
            exit($result['success'] ? 0 : 1);

        case 'migrate:status':
            $migrator = new Migrator(Database::instance());
            foreach ($migrator->files() as $name => $path) {
                $applied = in_array($name, $migrator->applied(), true);
                $out(sprintf('  [%s] %s', $applied ? 'x' : ' ', $name));
            }
            break;

        case 'seed':
            Installer::seedDefaults();
            $out('Default data seeded.', 'ok');
            break;

        case 'templates:generate':
            $perCategory = (int) ($options['per-category'] ?? 24);
            $result = (new TemplateFactory())->generate($perCategory, static function (int $count) use ($out): void {
                $out('  … ' . $count . ' designs');
            });
            $out(sprintf('Created %d, skipped %d, total %d.', $result['created'], $result['skipped'], $result['total']), 'ok');
            break;

        case 'subscriptions:expire':
            $result = (new SubscriptionService())->expireDue();
            $out(sprintf('%d subscription(s) expired, %d card(s) updated.', $result['expired'], $result['cards']), 'ok');
            break;

        case 'health':
            $report = (new HealthCheck())->run();
            foreach ($report['checks'] as $check) {
                $out(sprintf('  [%s] %-28s %s', strtoupper(substr($check['status'], 0, 4)), $check['name'], $check['message']), $check['status'] === 'pass' ? 'ok' : ($check['status'] === 'warn' ? 'warn' : 'error'));
            }
            $out($report['healthy'] ? 'System healthy.' : 'System has failures.', $report['healthy'] ? 'ok' : 'error');
            exit($report['healthy'] ? 0 : 1);

        case 'backup':
            $backup = (new BackupService())->create('full', 'cli');
            $out($backup['status'] === 'completed'
                ? 'Backup #' . $backup['id'] . ' created (' . human_size((int) $backup['files_size'] + (int) $backup['database_size']) . ').'
                : 'Backup failed: ' . (string) ($backup['error'] ?? ''), $backup['status'] === 'completed' ? 'ok' : 'error');
            break;

        case 'maintenance:on':
            Maintenance::enable($options['message'] ?? 'We are performing scheduled maintenance.');
            $out('Maintenance mode enabled.', 'warn');
            break;

        case 'maintenance:off':
            Maintenance::disable();
            $out('Maintenance mode disabled.', 'ok');
            break;

        case 'cleanup':
            $rates = App\Core\RateLimiter::purge();
            $analytics = (new App\Services\AnalyticsService())->prune((int) ($options['days'] ?? 400));
            $out(sprintf('Purged %d rate-limit rows and %d analytics rows.', $rates, $analytics), 'ok');
            break;

        case 'key:generate':
            $key = Installer::generateAppKey();
            Installer::writeEnv(['APP_KEY' => $key]);
            $out('New APP_KEY written to .env.', 'ok');
            $out('Warning: previously encrypted settings (tokens, secrets) must be re-entered.', 'warn');
            break;

        default:
            $out('Digital Visiting Card CLI (v' . APP_VERSION . ')');
            $out('');
            $out('  install                 Install the application');
            $out('  migrate                 Run pending migrations');
            $out('  migrate:status          Show migration state');
            $out('  seed                    Seed roles, plans and settings');
            $out('  templates:generate      Build the design library');
            $out('  subscriptions:expire    Expire finished subscriptions (cron)');
            $out('  health                  Run the health check');
            $out('  backup                  Create a full backup');
            $out('  maintenance:on|off      Toggle maintenance mode');
            $out('  cleanup                 Purge old rate-limit and analytics rows (cron)');
            $out('  key:generate            Generate a new APP_KEY');
    }
} catch (Throwable $e) {
    $out('Error: ' . $e->getMessage(), 'error');
    $out($e->getFile() . ':' . $e->getLine());
    if ((bool) App\Core\Env::get('APP_DEBUG', false)) {
        $out($e->getTraceAsString());
    }
    exit(1);
}
