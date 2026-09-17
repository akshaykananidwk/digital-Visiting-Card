<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Reseller;
use Throwable;

/**
 * White-label tenancy. When the platform is reached through a reseller's own
 * domain/subdomain, branding (logo, name, support details) is swapped for
 * that reseller's. Card ownership is never affected — tenancy only changes
 * presentation and the default reseller attribution for new sign-ups.
 */
final class Tenant
{
    /** @var array<string,mixed>|null */
    private static ?array $reseller = null;

    private static bool $resolved = false;

    public static function resolve(string $host): void
    {
        if (self::$resolved) {
            return;
        }
        self::$resolved = true;

        $host = strtolower(preg_replace('/:\d+$/', '', $host) ?? $host);
        $primary = strtolower((string) parse_url((string) Config::get('app.url', ''), PHP_URL_HOST));

        if ($host === '' || $host === $primary || $host === 'www.' . $primary) {
            return;
        }

        try {
            $reseller = (new Reseller())->findByDomain($host);
        } catch (Throwable) {
            return;
        }

        if ($reseller === null || (string) $reseller['domain_status'] !== 'verified' || (int) $reseller['is_active'] !== 1) {
            return;
        }

        self::$reseller = $reseller;
        Url::setRoot((Request::current()?->isSecure() ? 'https://' : 'http://') . $host);
    }

    /** @return array<string,mixed>|null */
    public static function reseller(): ?array
    {
        return self::$reseller;
    }

    public static function resellerId(): ?int
    {
        return self::$reseller === null ? null : (int) self::$reseller['id'];
    }

    public static function isWhiteLabel(): bool
    {
        return self::$reseller !== null;
    }

    /**
     * Branding used throughout the UI.
     *
     * @return array{name:string,logo:?string,favicon:?string,support_email:string,support_phone:string,whatsapp:string,white_label:bool,tagline:string}
     */
    public static function branding(): array
    {
        if (self::$reseller !== null) {
            return [
                'name'          => (string) (self::$reseller['brand_name'] ?: 'Digital Cards'),
                'logo'          => self::$reseller['brand_logo'] ? upload_url((string) self::$reseller['brand_logo']) : null,
                'favicon'       => self::$reseller['brand_favicon'] ? upload_url((string) self::$reseller['brand_favicon']) : null,
                'support_email' => (string) (self::$reseller['support_email'] ?? ''),
                'support_phone' => (string) (self::$reseller['support_phone'] ?? ''),
                'whatsapp'      => (string) (self::$reseller['support_whatsapp'] ?? ''),
                'tagline'       => (string) (self::$reseller['tagline'] ?? ''),
                'white_label'   => true,
            ];
        }

        return [
            'name'          => (string) (Settings::get('site_name') ?: Config::get('app.name', APP_NAME_DEFAULT)),
            'logo'          => Settings::get('site_logo') ? upload_url((string) Settings::get('site_logo')) : null,
            'favicon'       => Settings::get('site_favicon') ? upload_url((string) Settings::get('site_favicon')) : null,
            'support_email' => (string) (Settings::get('support_email') ?? ''),
            'support_phone' => (string) (Settings::get('support_phone') ?? ''),
            'whatsapp'      => (string) (Settings::get('support_whatsapp') ?? ''),
            'tagline'       => (string) (Settings::get('site_tagline') ?? 'Your business, one link away.'),
            'white_label'   => false,
        ];
    }

    public static function reset(): void
    {
        self::$reseller = null;
        self::$resolved = false;
    }
}
