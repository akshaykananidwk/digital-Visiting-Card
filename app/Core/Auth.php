<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Authentication + role based access control.
 *
 * Roles: super_admin > admin > reseller > customer.
 * Impersonation keeps the original admin id in the session so that the
 * operator can exit back into their own account.
 */
final class Auth
{
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN       = 'admin';
    public const ROLE_RESELLER    = 'reseller';
    public const ROLE_CUSTOMER    = 'customer';

    private const SESSION_KEY = '_auth_user_id';
    private const IMPERSONATOR_KEY = '_auth_impersonator_id';

    private static ?Auth $instance = null;

    /** @var array<string,mixed>|null */
    private ?array $user = null;

    private bool $resolved = false;

    public static function instance(): Auth
    {
        return self::$instance ??= new self();
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }
        $this->resolved = true;

        $id = Session::get(self::SESSION_KEY);
        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            return $this->user = null;
        }

        $user = (new User())->find((int) $id);
        if ($user === null || (string) $user['status'] !== 'active') {
            $this->logout();

            return $this->user = null;
        }

        // Invalidate every existing session when the password changes.
        $stamp = Session::get('_auth_stamp');
        $expected = $this->stamp($user);
        if (is_string($stamp) && !hash_equals($expected, $stamp)) {
            $this->logout();

            return $this->user = null;
        }

        return $this->user = $user;
    }

    public function id(): ?int
    {
        $user = $this->user();

        return $user === null ? null : (int) $user['id'];
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    /** @param array<string,mixed> $user */
    public function login(array $user, bool $regenerate = true): void
    {
        if ($regenerate) {
            Session::regenerate();
        }
        Session::put(self::SESSION_KEY, (int) $user['id']);
        Session::put('_auth_stamp', $this->stamp($user));
        Csrf::rotate();

        $this->user = $user;
        $this->resolved = true;

        (new User())->touchLogin((int) $user['id'], Request::current()?->ip() ?? '');
    }

    public function loginById(int $id): bool
    {
        $user = (new User())->find($id);
        if ($user === null || (string) $user['status'] !== 'active') {
            return false;
        }
        $this->login($user);

        return true;
    }

    public function logout(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::forget('_auth_stamp');
        Session::forget(self::IMPERSONATOR_KEY);
        $this->user = null;
        $this->resolved = true;
    }

    /**
     * Verify credentials without creating a session.
     *
     * @return array<string,mixed>|null
     */
    public function validateCredentials(string $email, string $password): ?array
    {
        $user = (new User())->findByEmail($email);

        // Always run a hash comparison so timing does not reveal account
        // existence.
        $hash = $user['password'] ?? '$2y$12$usesomesillystringfaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $valid = password_verify($password, (string) $hash);

        if (!$valid || $user === null) {
            return null;
        }

        if (password_needs_rehash((string) $user['password'], PASSWORD_DEFAULT, ['cost' => 12])) {
            (new User())->updatePassword((int) $user['id'], $password);
        }

        return $user;
    }

    // ------------------------------------------------------------- Roles --

    public function role(): ?string
    {
        $user = $this->user();

        return $user === null ? null : (string) $user['role'];
    }

    public function is(string ...$roles): bool
    {
        $role = $this->role();

        return $role !== null && in_array($role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->is(self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN);
    }

    public function isSuperAdmin(): bool
    {
        return $this->is(self::ROLE_SUPER_ADMIN);
    }

    public function isReseller(): bool
    {
        return $this->is(self::ROLE_RESELLER);
    }

    public function isCustomer(): bool
    {
        return $this->is(self::ROLE_CUSTOMER);
    }

    /** Role rank used for "can manage" checks. */
    public static function rank(?string $role): int
    {
        return match ($role) {
            self::ROLE_SUPER_ADMIN => 40,
            self::ROLE_ADMIN       => 30,
            self::ROLE_RESELLER    => 20,
            self::ROLE_CUSTOMER    => 10,
            default                => 0,
        };
    }

    public function outranks(?string $role): bool
    {
        return self::rank($this->role()) > self::rank($role);
    }

    /**
     * Permission check. Super admins implicitly hold every permission; other
     * roles are resolved through role_permissions.
     */
    public function can(string $permission): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        if ((string) $user['role'] === self::ROLE_SUPER_ADMIN) {
            return true;
        }

        return Permission::roleHas((string) $user['role'], $permission);
    }

    public function cannot(string $permission): bool
    {
        return !$this->can($permission);
    }

    // ----------------------------------------------------- Impersonation --

    public function startImpersonation(int $targetUserId): bool
    {
        $current = $this->user();
        if ($current === null || !$this->isAdmin()) {
            return false;
        }
        $target = (new User())->find($targetUserId);
        if ($target === null || (string) $target['status'] !== 'active') {
            return false;
        }
        if (!$this->outranks((string) $target['role'])) {
            return false;
        }

        $impersonatorId = (int) $current['id'];
        $this->login($target);
        Session::put(self::IMPERSONATOR_KEY, $impersonatorId);

        return true;
    }

    public function stopImpersonation(): bool
    {
        $impersonatorId = Session::get(self::IMPERSONATOR_KEY);
        if (!is_int($impersonatorId)) {
            return false;
        }
        Session::forget(self::IMPERSONATOR_KEY);

        return $this->loginById($impersonatorId);
    }

    public function isImpersonating(): bool
    {
        return Session::has(self::IMPERSONATOR_KEY);
    }

    public function impersonatorId(): ?int
    {
        $id = Session::get(self::IMPERSONATOR_KEY);

        return is_int($id) ? $id : null;
    }

    /** @param array<string,mixed> $user */
    private function stamp(array $user): string
    {
        return substr(hash_hmac('sha256', (string) $user['id'] . '|' . (string) $user['password'], (string) Config::get('app.key', 'dvc')), 0, 32);
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
    }
}
