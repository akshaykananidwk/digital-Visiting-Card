<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;
use App\Models\Card;
use App\Models\CardGallery;
use App\Models\CardProduct;
use App\Models\CardService as CardServiceModel;
use App\Models\Plan;
use App\Models\Subscription;

/**
 * Centralised plan/limit engine.
 *
 * Every limit question in the application is answered here so that the same
 * rule is applied by the UI *and* by the server-side handler. A limit of -1
 * means unlimited.
 */
final class PlanLimiter
{
    /** @var array<int,array<string,mixed>> per-request cache keyed by user id */
    private static array $planCache = [];

    /**
     * Resolve the effective plan for a user: their active subscription's plan,
     * otherwise the platform's free plan, otherwise a hard-coded minimum.
     *
     * @return array<string,mixed>
     */
    public static function planFor(int $userId): array
    {
        if (isset(self::$planCache[$userId])) {
            return self::$planCache[$userId];
        }

        $subscription = (new Subscription())->activeFor($userId);
        $plan = null;

        if ($subscription !== null) {
            $plan = (new Plan())->find((int) $subscription['plan_id']);
            if ($plan !== null) {
                $plan['__subscription'] = $subscription;
            }
        }

        if ($plan === null) {
            $plan = (new Plan())->freePlan();
        }

        if ($plan === null) {
            $plan = self::fallbackPlan();
        }

        return self::$planCache[$userId] = $plan;
    }

    public static function flush(?int $userId = null): void
    {
        if ($userId === null) {
            self::$planCache = [];

            return;
        }
        unset(self::$planCache[$userId]);
    }

    /** @return array<string,mixed> */
    private static function fallbackPlan(): array
    {
        return [
            'id'                => 0,
            'slug'              => 'free',
            'name'              => 'Free',
            'price'             => 0.0,
            'duration_days'     => 0,
            'card_limit'        => 1,
            'product_limit'     => 3,
            'service_limit'     => 3,
            'gallery_limit'     => 3,
            'video_limit'       => 0,
            'lead_limit'        => -1,
            'storage_limit_mb'  => 25,
            'premium_templates' => 0,
            'custom_domain'     => 0,
            'remove_branding'   => 0,
            'analytics'         => 1,
            'qr_download'       => 1,
            'vcard'             => 1,
            'enquiry_form'      => 1,
            'seo_controls'      => 0,
            'api_access'        => 0,
            'features'          => [],
            'is_free'           => 1,
        ];
    }

    public static function limit(int $userId, string $key): int
    {
        $plan = self::planFor($userId);

        return (int) ($plan[$key] ?? 0);
    }

    public static function feature(int $userId, string $key): bool
    {
        $plan = self::planFor($userId);

        return (bool) ($plan[$key] ?? false);
    }

    // ------------------------------------------------------------ Checks --

    /** @return array{allowed:bool,used:int,limit:int,message:string} */
    public static function canCreateCard(int $userId): array
    {
        $limit = self::limit($userId, 'card_limit');
        $used = (new Card())->countForUser($userId);

        return self::result($used, $limit, 'card', 'You have reached your plan\'s card limit. Upgrade to create more cards.');
    }

    /** @return array{allowed:bool,used:int,limit:int,message:string} */
    public static function canAddProduct(int $userId, int $cardId): array
    {
        $limit = self::limit($userId, 'product_limit');
        $used = (new CardProduct())->count(['card_id' => $cardId]);

        return self::result($used, $limit, 'product', 'You have reached your plan\'s product limit for this card.');
    }

    /** @return array{allowed:bool,used:int,limit:int,message:string} */
    public static function canAddService(int $userId, int $cardId): array
    {
        $limit = self::limit($userId, 'service_limit');
        $used = (new CardServiceModel())->count(['card_id' => $cardId]);

        return self::result($used, $limit, 'service', 'You have reached your plan\'s service limit for this card.');
    }

    /** @return array{allowed:bool,used:int,limit:int,message:string} */
    public static function canAddGalleryItem(int $userId, int $cardId, string $type = 'image'): array
    {
        if ($type === 'image') {
            $limit = self::limit($userId, 'gallery_limit');
            $used = (new CardGallery())->countForCard($cardId, 'image');

            return self::result($used, $limit, 'gallery image', 'You have reached your plan\'s gallery limit for this card.');
        }

        $limit = self::limit($userId, 'video_limit');
        $used = (new CardGallery())->countForCard($cardId, 'video') + (new CardGallery())->countForCard($cardId, 'youtube');

        return self::result($used, $limit, 'video', 'You have reached your plan\'s video limit for this card.');
    }

    /** @return array{allowed:bool,used:int,limit:int,message:string} */
    public static function canUseStorage(int $userId, int $additionalBytes): array
    {
        $limitMb = self::limit($userId, 'storage_limit_mb');
        if ($limitMb < 0) {
            return ['allowed' => true, 'used' => 0, 'limit' => -1, 'message' => ''];
        }
        $usedBytes = (new CardGallery())->storageUsed($userId) + $additionalBytes;
        $limitBytes = $limitMb * 1024 * 1024;

        return [
            'allowed' => $usedBytes <= $limitBytes,
            'used'    => $usedBytes,
            'limit'   => $limitBytes,
            'message' => $usedBytes <= $limitBytes ? '' : 'You have used all of your plan\'s storage (' . $limitMb . ' MB). Upgrade or remove some media.',
        ];
    }

    public static function canUsePremiumTemplate(int $userId): bool
    {
        return self::feature($userId, 'premium_templates');
    }

    public static function canUseCustomDomain(int $userId): bool
    {
        return self::feature($userId, 'custom_domain');
    }

    public static function canRemoveBranding(int $userId): bool
    {
        return self::feature($userId, 'remove_branding');
    }

    public static function canUseAnalytics(int $userId): bool
    {
        return self::feature($userId, 'analytics');
    }

    public static function canDownloadQr(int $userId): bool
    {
        return self::feature($userId, 'qr_download');
    }

    public static function canUseSeoControls(int $userId): bool
    {
        return self::feature($userId, 'seo_controls');
    }

    public static function canUseApi(int $userId): bool
    {
        return self::feature($userId, 'api_access');
    }

    /**
     * Snapshot of usage vs. limits, rendered on the customer dashboard.
     *
     * @return array<string,array{used:int,limit:int,percent:int}>
     */
    public static function usage(int $userId): array
    {
        $plan = self::planFor($userId);
        $cards = (new Card())->countForUser($userId);
        $storageUsed = (new CardGallery())->storageUsed($userId);
        $storageLimit = (int) ($plan['storage_limit_mb'] ?? 0) * 1024 * 1024;

        return [
            'cards'   => self::usageRow($cards, (int) ($plan['card_limit'] ?? 0)),
            'storage' => self::usageRow($storageUsed, $storageLimit),
        ];
    }

    /** @return array{used:int,limit:int,percent:int} */
    private static function usageRow(int $used, int $limit): array
    {
        if ($limit < 0) {
            return ['used' => $used, 'limit' => -1, 'percent' => 0];
        }

        return [
            'used'    => $used,
            'limit'   => $limit,
            'percent' => $limit === 0 ? 100 : (int) min(100, round(($used / $limit) * 100)),
        ];
    }

    /** @return array{allowed:bool,used:int,limit:int,message:string} */
    private static function result(int $used, int $limit, string $noun, string $message): array
    {
        if ($limit < 0) {
            return ['allowed' => true, 'used' => $used, 'limit' => -1, 'message' => ''];
        }

        return [
            'allowed' => $used < $limit,
            'used'    => $used,
            'limit'   => $limit,
            'message' => $used < $limit ? '' : $message,
        ];
    }

    /** Whether expired cards should be disabled, shown as expired or kept live. */
    public static function expiryBehaviour(): string
    {
        $value = (string) (Settings::get('expired_card_behaviour') ?: 'expired_page');

        return in_array($value, ['expired_page', 'disable', 'keep_live'], true) ? $value : 'expired_page';
    }

    public static function graceDays(): int
    {
        return max(0, (int) (Settings::get('subscription_grace_days') ?: 0));
    }
}
