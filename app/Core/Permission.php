<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/** Role → permission resolution, cached per request. */
final class Permission
{
    /** @var array<string,array<int,string>>|null */
    private static ?array $map = null;

    /** Canonical permission catalogue used by the installer/seed. */
    public const CATALOGUE = [
        'admin.access'        => 'Access the admin panel',
        'admin.users'         => 'Manage users',
        'admin.cards'         => 'Manage all cards',
        'admin.templates'     => 'Manage templates',
        'admin.plans'         => 'Manage plans',
        'admin.orders'        => 'View orders and payments',
        'admin.resellers'     => 'Manage resellers',
        'admin.leads'         => 'View all leads',
        'admin.settings'      => 'Manage settings',
        'admin.updates'       => 'Run application updates',
        'admin.backups'       => 'Manage backups',
        'admin.logs'          => 'View logs and audit trail',
        'admin.impersonate'   => 'Log in as another user',
        'reseller.access'     => 'Access the reseller panel',
        'reseller.customers'  => 'Manage own customers',
        'reseller.wallet'     => 'Use the reseller wallet',
        'reseller.branding'   => 'Configure white-label branding',
        'customer.access'     => 'Access the customer dashboard',
        'customer.cards'      => 'Manage own cards',
        'customer.billing'    => 'Manage own subscription',
    ];

    public static function roleHas(string $role, string $permission): bool
    {
        self::load();

        return in_array($permission, self::$map[$role] ?? [], true);
    }

    /** @return array<int,string> */
    public static function forRole(string $role): array
    {
        self::load();

        return self::$map[$role] ?? [];
    }

    public static function flush(): void
    {
        self::$map = null;
    }

    private static function load(): void
    {
        if (self::$map !== null) {
            return;
        }
        self::$map = self::defaults();

        if (!is_installed()) {
            return;
        }

        try {
            $db = Database::instance();
            $rows = $db->select(
                'SELECT r.`slug` AS role, p.`slug` AS permission
                   FROM ' . $db->table('role_permissions') . ' rp
                   JOIN ' . $db->table('roles') . ' r ON r.id = rp.role_id
                   JOIN ' . $db->table('permissions') . ' p ON p.id = rp.permission_id'
            );
        } catch (Throwable) {
            return;
        }

        if ($rows === []) {
            return;
        }

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['role']][] = (string) $row['permission'];
        }
        self::$map = $map;
    }

    /** @return array<string,array<int,string>> */
    public static function defaults(): array
    {
        $all = array_keys(self::CATALOGUE);

        return [
            Auth::ROLE_SUPER_ADMIN => $all,
            Auth::ROLE_ADMIN       => array_values(array_filter($all, static fn ($p) => !in_array($p, ['admin.updates', 'admin.backups'], true))),
            Auth::ROLE_RESELLER    => ['reseller.access', 'reseller.customers', 'reseller.wallet', 'reseller.branding', 'customer.access', 'customer.cards', 'customer.billing'],
            Auth::ROLE_CUSTOMER    => ['customer.access', 'customer.cards', 'customer.billing'],
        ];
    }
}
