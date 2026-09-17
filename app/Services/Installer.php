<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Crypto;
use App\Core\Database;
use App\Core\Env;
use App\Core\Migrator;
use App\Core\Permission;
use App\Core\Settings;
use App\Models\Model;
use App\Models\Plan;
use App\Models\User;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Installation logic shared by the web installer and the CLI bootstrap
 * script: requirement checks, .env generation, migrations and default data.
 */
final class Installer
{
    public const MIN_PHP = '8.1.0';

    public const REQUIRED_EXTENSIONS = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'fileinfo', 'curl'];

    public const RECOMMENDED_EXTENSIONS = ['gd', 'zip', 'exif', 'intl', 'opcache'];

    public const WRITABLE_PATHS = ['/storage', '/storage/logs', '/storage/cache', '/storage/backups', '/storage/sessions', '/uploads', '/'];

    /**
     * Server requirement report.
     *
     * @return array{passed:bool,items:array<int,array{label:string,status:string,value:string,hint:string,required:bool}>}
     */
    public static function requirements(): array
    {
        $items = [];

        $phpOk = version_compare(PHP_VERSION, self::MIN_PHP, '>=');
        $items[] = [
            'label'    => 'PHP version',
            'status'   => $phpOk ? 'pass' : 'fail',
            'value'    => PHP_VERSION,
            'hint'     => $phpOk ? '' : 'PHP ' . self::MIN_PHP . ' or newer is required. Ask your host to switch the PHP version in cPanel.',
            'required' => true,
        ];

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $loaded = extension_loaded($extension);
            $items[] = [
                'label'    => 'Extension: ' . $extension,
                'status'   => $loaded ? 'pass' : 'fail',
                'value'    => $loaded ? 'enabled' : 'missing',
                'hint'     => $loaded ? '' : 'Enable the ' . $extension . ' extension (cPanel → Select PHP Version → Extensions).',
                'required' => true,
            ];
        }

        foreach (self::RECOMMENDED_EXTENSIONS as $extension) {
            $loaded = extension_loaded($extension);
            $items[] = [
                'label'    => 'Extension: ' . $extension . ' (recommended)',
                'status'   => $loaded ? 'pass' : 'warn',
                'value'    => $loaded ? 'enabled' : 'missing',
                'hint'     => $loaded ? '' : match ($extension) {
                    'gd'      => 'Image resizing, WebP conversion and PNG QR codes need GD or Imagick.',
                    'zip'     => 'The GitHub auto-updater and backup system need ZipArchive.',
                    'exif'    => 'Photo orientation is corrected automatically when exif is available.',
                    default   => 'Optional, but improves performance.',
                },
                'required' => false,
            ];
        }

        foreach (self::WRITABLE_PATHS as $path) {
            $absolute = BASE_PATH . $path;
            $writable = is_dir($absolute) && is_writable($absolute);
            $items[] = [
                'label'    => 'Writable: ' . ($path === '/' ? '/ (project root)' : $path),
                'status'   => $writable ? 'pass' : ($path === '/' ? 'warn' : 'fail'),
                'value'    => $writable ? 'writable' : 'not writable',
                'hint'     => $writable ? '' : 'chmod 755 (or 775) ' . $absolute,
                'required' => $path !== '/',
            ];
        }

        $items[] = [
            'label'    => 'URL rewriting',
            'status'   => is_file(BASE_PATH . '/.htaccess') ? 'pass' : 'warn',
            'value'    => is_file(BASE_PATH . '/.htaccess') ? '.htaccess present' : '.htaccess missing',
            'hint'     => is_file(BASE_PATH . '/.htaccess') ? '' : 'Upload the .htaccess file shipped with the package and enable mod_rewrite.',
            'required' => false,
        ];

        $passed = true;
        foreach ($items as $item) {
            if ($item['required'] && $item['status'] === 'fail') {
                $passed = false;
            }
        }

        return ['passed' => $passed, 'items' => $items];
    }

    /**
     * Test a database connection and (optionally) create the database.
     *
     * @param array<string,mixed> $config
     * @return array{success:bool,message:string,version:string}
     */
    public static function testDatabase(array $config, bool $createIfMissing = true): array
    {
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 3306);
        $database = (string) ($config['database'] ?? '');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');

        if ($database === '') {
            return ['success' => false, 'message' => 'Please enter a database name.', 'version' => ''];
        }

        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
                $username,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8]
            );
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Could not connect to the MySQL server: ' . self::friendlyPdoMessage($e->getMessage()),
                'version' => '',
            ];
        }

        $version = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

        $exists = $pdo->query(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ' . $pdo->quote($database)
        );
        $found = $exists !== false && $exists->fetch() !== false;

        if (!$found) {
            if (!$createIfMissing) {
                return ['success' => false, 'message' => 'Database "' . $database . '" does not exist.', 'version' => $version];
            }
            try {
                $pdo->exec(sprintf(
                    'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                    str_replace('`', '', $database)
                ));
            } catch (Throwable $e) {
                return [
                    'success' => false,
                    'message' => 'Database "' . $database . '" does not exist and could not be created automatically. Create it in cPanel → MySQL Databases and try again.',
                    'version' => $version,
                ];
            }
        }

        try {
            $pdo->exec('USE `' . str_replace('`', '', $database) . '`');
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'The user does not have access to "' . $database . '".', 'version' => $version];
        }

        return [
            'success' => true,
            'message' => 'Connected successfully to MySQL ' . $version . ($found ? '' : ' (database created)'),
            'version' => $version,
        ];
    }

    private static function friendlyPdoMessage(string $message): string
    {
        if (str_contains($message, 'Access denied')) {
            return 'access denied — check the database username and password.';
        }
        if (str_contains($message, 'Connection refused') || str_contains($message, 'No such file')) {
            return 'connection refused — check the host name and port (most shared hosts use 127.0.0.1 or localhost).';
        }
        if (str_contains($message, 'Unknown MySQL server host')) {
            return 'unknown host name.';
        }

        return preg_replace('/SQLSTATE\[[^\]]+\]\s*/', '', $message) ?? $message;
    }

    /**
     * Write the .env file.
     *
     * @param array<string,mixed> $values
     */
    public static function writeEnv(array $values): bool
    {
        $path = BASE_PATH . '/.env';

        if (!is_file($path)) {
            $example = BASE_PATH . '/.env.example';
            $seed = is_file($example) ? (string) file_get_contents($example) : '';
            if (@file_put_contents($path, $seed) === false) {
                return false;
            }
            @chmod($path, 0600);
        }

        return Env::write($path, $values);
    }

    /** Run all pending migrations. @return array{success:bool,ran:array<int,string>,error:?string} */
    public static function migrate(): array
    {
        $migrator = new Migrator(Database::instance());
        $result = $migrator->run();

        return ['success' => $result['success'], 'ran' => $result['ran'], 'error' => $result['error']];
    }

    /** Seed roles, permissions and the default plan/setting rows. */
    public static function seedDefaults(): void
    {
        $db = Database::instance();

        // ------------------------------------------------------ Roles/ACL --
        $roles = [
            Auth::ROLE_SUPER_ADMIN => ['Super Administrator', 40],
            Auth::ROLE_ADMIN       => ['Administrator', 30],
            Auth::ROLE_RESELLER    => ['Reseller', 20],
            Auth::ROLE_CUSTOMER    => ['Customer', 10],
        ];
        $roleIds = [];
        foreach ($roles as $slug => [$name, $rank]) {
            $existing = $db->selectOne('SELECT `id` FROM `' . $db->table('roles') . '` WHERE `slug` = :slug', ['slug' => $slug]);
            $roleIds[$slug] = $existing !== null
                ? (int) $existing['id']
                : $db->insert('roles', [
                    'slug' => $slug, 'name' => $name, 'rank' => $rank, 'is_system' => 1, 'created_at' => now(),
                ]);
        }

        $permissionIds = [];
        foreach (Permission::CATALOGUE as $slug => $name) {
            $existing = $db->selectOne('SELECT `id` FROM `' . $db->table('permissions') . '` WHERE `slug` = :slug', ['slug' => $slug]);
            $permissionIds[$slug] = $existing !== null
                ? (int) $existing['id']
                : $db->insert('permissions', [
                    'slug' => $slug, 'name' => $name, 'group' => explode('.', $slug)[0], 'created_at' => now(),
                ]);
        }

        foreach (Permission::defaults() as $roleSlug => $permissions) {
            foreach ($permissions as $permissionSlug) {
                if (!isset($roleIds[$roleSlug], $permissionIds[$permissionSlug])) {
                    continue;
                }
                $db->execute(
                    'INSERT IGNORE INTO `' . $db->table('role_permissions') . '` (`role_id`, `permission_id`) VALUES (:role, :permission)',
                    ['role' => $roleIds[$roleSlug], 'permission' => $permissionIds[$permissionSlug]]
                );
            }
        }
        Permission::flush();

        // ---------------------------------------------------------- Plans --
        self::seedPlans();

        // ------------------------------------------------------- Settings --
        self::seedSettings();
    }

    public static function seedPlans(): void
    {
        $plans = new Plan();

        $definitions = [
            ['free', 'Free', 'Get online in minutes with a single digital card.', 0, 365, 1, 3, 3, 4, 0, 25, 0, 0, 0, 1, 1],
            ['basic', 'Basic', 'Perfect for a small shop or an individual professional.', 499, 365, 1, 15, 15, 20, 2, 100, 0, 0, 0, 1, 0],
            ['pro', 'Pro', 'For growing businesses that need products and premium designs.', 999, 365, 3, 60, 60, 60, 8, 500, 1, 0, 1, 1, 0],
            ['premium', 'Premium', 'Everything in Pro plus custom domain support and SEO controls.', 1999, 365, 6, 200, 200, 200, 25, 2048, 1, 1, 1, 1, 0],
            ['business', 'Business', 'Unlimited cards and content for teams and agencies.', 4999, 365, -1, -1, -1, -1, -1, 10240, 1, 1, 1, 1, 0],
        ];

        $order = 0;
        foreach ($definitions as [$slug, $name, $description, $price, $days, $cards, $products, $services, $gallery, $videos, $storage, $premium, $domain, $branding, $analytics, $isFree]) {
            if ($plans->findBySlug($slug) !== null) {
                continue;
            }
            $plans->create([
                'slug'              => $slug,
                'name'              => $name,
                'description'       => $description,
                'price'             => $price,
                'reseller_price'    => $price > 0 ? round($price * 0.7, 2) : 0,
                'mrp'               => $price > 0 ? round($price * 1.5, 2) : null,
                'currency'          => 'INR',
                'duration_days'     => $days,
                'card_limit'        => $cards,
                'product_limit'     => $products,
                'service_limit'     => $services,
                'gallery_limit'     => $gallery,
                'video_limit'       => $videos,
                'lead_limit'        => -1,
                'storage_limit_mb'  => $storage,
                'premium_templates' => $premium,
                'custom_domain'     => $domain,
                'remove_branding'   => $branding,
                'analytics'         => $analytics,
                'qr_download'       => 1,
                'vcard'             => 1,
                'enquiry_form'      => 1,
                'seo_controls'      => $slug === 'free' ? 0 : 1,
                'api_access'        => in_array($slug, ['premium', 'business'], true) ? 1 : 0,
                'priority_support'  => in_array($slug, ['premium', 'business'], true) ? 1 : 0,
                'features'          => self::planFeatures($slug),
                'is_free'           => $isFree,
                'is_active'         => 1,
                'is_featured'       => $slug === 'pro' ? 1 : 0,
                'sort_order'        => ++$order,
                'created_at'        => now(),
            ]);
        }
    }

    /** @return array<int,string> */
    private static function planFeatures(string $slug): array
    {
        return match ($slug) {
            'free'     => ['1 digital card', 'Free designs', 'QR code', 'Save contact (vCard)', 'WhatsApp button', 'Basic analytics'],
            'basic'    => ['1 digital card', 'All free designs', 'Up to 15 products & services', 'QR code + vCard', 'Enquiry form', 'Analytics dashboard'],
            'pro'      => ['3 digital cards', 'Premium & 3D designs', '60 products & services', 'Gallery + videos', 'Lead management', 'SEO controls'],
            'premium'  => ['6 digital cards', 'Premium designs', 'Custom domain ready', 'Remove branding', 'API access', 'Priority support'],
            'business' => ['Unlimited cards', 'Unlimited products', 'Unlimited gallery', 'Custom domain', 'White-label ready', 'Priority support'],
            default    => [],
        };
    }

    public static function seedSettings(): void
    {
        $defaults = [
            'general' => [
                'site_name'        => 'Digital Visiting Card',
                'site_tagline'     => 'Your business, one link away.',
                'site_description' => 'Create a premium digital visiting card in minutes and share it on WhatsApp, Instagram and QR.',
                'support_email'    => '',
                'support_phone'    => '',
                'support_whatsapp' => '',
                'currency_code'    => 'INR',
                'currency_symbol'  => '₹',
                'timezone'         => 'Asia/Kolkata',
                'maintenance_mode' => false,
                'allow_registration' => true,
                'default_country'  => 'India',
            ],
            'cards' => [
                'card_url_mode'            => 'both',
                'expired_card_behaviour'   => 'expired_page',
                'subscription_grace_days'  => 3,
                'show_platform_branding'   => true,
                'default_whatsapp_message' => 'Hello, I found your digital visiting card and would like to know more.',
                'reduced_motion_default'   => false,
            ],
            'payment' => [
                'razorpay_enabled'        => false,
                'razorpay_key_id'         => '',
                'razorpay_key_secret'     => '',
                'razorpay_webhook_secret' => '',
                'gst_enabled'             => false,
                'gst_rate'                => 18,
                'gst_inclusive'           => false,
                'gst_number'              => '',
                'gst_state'               => '',
                'gst_hsn'                 => '998314',
                'invoice_prefix'          => 'INV',
                'company_legal_name'      => '',
                'company_address'         => '',
            ],
            'mail' => [
                'smtp_host'         => '',
                'smtp_port'         => 587,
                'smtp_username'     => '',
                'smtp_password'     => '',
                'smtp_encryption'   => 'tls',
                'mail_from_address' => '',
                'mail_from_name'    => 'Digital Visiting Card',
                'notify_admin_on_signup' => true,
                'notify_user_on_lead'    => true,
            ],
            'seo' => [
                'meta_title'       => 'Digital Visiting Card — Create your card in 5 minutes',
                'meta_description' => 'Build a mobile-first digital visiting card with QR code, WhatsApp button and vCard download.',
                'meta_keywords'    => 'digital visiting card, digital business card, qr card, vcard',
                'og_image'         => '',
                'google_verification' => '',
                'enable_sitemap'   => true,
            ],
            'uploads' => [
                'max_upload_mb'       => 5,
                'image_quality'       => 82,
                'generate_webp'       => true,
                'max_image_width'     => 1600,
            ],
            'updates' => [
                'github_repo'          => '',
                'github_branch'        => 'main',
                'github_token'         => '',
                'auto_backup_before_update' => true,
                'backup_retention'     => 5,
                'protected_paths'      => ".env\nconfig/local.php\nuploads/\nstorage/\n",
            ],
        ];

        foreach ($defaults as $group => $values) {
            foreach ($values as $key => $value) {
                if (Settings::has($key)) {
                    continue;
                }
                $type = match (true) {
                    is_bool($value)  => 'boolean',
                    is_int($value)   => 'integer',
                    is_float($value) => 'float',
                    is_array($value) => 'json',
                    default          => 'string',
                };
                Settings::set($key, $value, $group, $type);
            }
        }
    }

    /**
     * Create the first super administrator.
     *
     * @return array<string,mixed>
     */
    public static function createAdmin(string $name, string $email, string $password, string $phone = ''): array
    {
        $users = new User();
        $existing = $users->findByEmail($email);

        if ($existing !== null) {
            $users->updateById((int) $existing['id'], [
                'name'     => $name,
                'password' => Auth::hash($password),
                'role'     => Auth::ROLE_SUPER_ADMIN,
                'status'   => 'active',
                'phone'    => $phone !== '' ? $phone : null,
                'email_verified_at' => now(),
            ]);

            return (array) $users->find((int) $existing['id']);
        }

        $id = $users->create([
            'uuid'              => Model::uuid(),
            'name'              => $name,
            'email'             => strtolower($email),
            'phone'             => $phone !== '' ? $phone : null,
            'password'          => Auth::hash($password),
            'role'              => Auth::ROLE_SUPER_ADMIN,
            'status'            => 'active',
            'email_verified_at' => now(),
            'onboarding_step'   => 99,
            'created_at'        => now(),
        ]);

        return (array) $users->find($id);
    }

    /** Write the installation lock file. */
    public static function lock(): bool
    {
        $payload = json_encode([
            'installed_at' => now(),
            'version'      => APP_VERSION,
            'php'          => PHP_VERSION,
        ], JSON_PRETTY_PRINT);

        if (@file_put_contents(INSTALL_LOCK, (string) $payload, LOCK_EX) === false) {
            return false;
        }
        @chmod(INSTALL_LOCK, 0600);

        // Best effort: neutralise the installer routes on disk as well.
        $marker = BASE_PATH . '/install/.installed';
        @file_put_contents($marker, (string) $payload);

        return true;
    }

    public static function generateAppKey(): string
    {
        return Crypto::generateKey();
    }

    /** Security posture check shown on the last installer step. */
    public static function securityReport(): array
    {
        $items = [];

        $envPath = BASE_PATH . '/.env';
        $permissions = is_file($envPath) ? substr(sprintf('%o', fileperms($envPath)), -4) : '----';
        $items[] = [
            'label'  => '.env permissions',
            'status' => in_array($permissions, ['0600', '0640', '0644'], true) ? 'pass' : 'warn',
            'value'  => $permissions,
            'hint'   => 'Set .env to 600 so other hosting accounts cannot read your database password.',
        ];

        $debug = (bool) Env::get('APP_DEBUG', false);
        $items[] = [
            'label'  => 'Debug mode',
            'status' => $debug ? 'warn' : 'pass',
            'value'  => $debug ? 'on' : 'off',
            'hint'   => $debug ? 'Turn APP_DEBUG off before going live.' : '',
        ];

        $https = str_starts_with((string) Env::get('APP_URL', ''), 'https://');
        $items[] = [
            'label'  => 'HTTPS',
            'status' => $https ? 'pass' : 'warn',
            'value'  => $https ? 'enabled' : 'not configured',
            'hint'   => $https ? '' : 'Install an SSL certificate and set APP_URL to the https:// address.',
        ];

        $uploadsHtaccess = is_file(BASE_PATH . '/uploads/.htaccess');
        $items[] = [
            'label'  => 'Upload directory hardening',
            'status' => $uploadsHtaccess ? 'pass' : 'fail',
            'value'  => $uploadsHtaccess ? 'protected' : 'missing .htaccess',
            'hint'   => $uploadsHtaccess ? '' : 'Restore uploads/.htaccess so PHP files cannot execute from the uploads folder.',
        ];

        $key = (string) Env::get('APP_KEY', '');
        $items[] = [
            'label'  => 'Application key',
            'status' => $key !== '' ? 'pass' : 'fail',
            'value'  => $key !== '' ? 'generated' : 'missing',
            'hint'   => $key !== '' ? '' : 'APP_KEY encrypts stored secrets — it must be set.',
        ];

        return $items;
    }
}
