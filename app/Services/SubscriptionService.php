<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AuditLog;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Settings;
use App\Models\Card;
use App\Models\Model;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use RuntimeException;

/**
 * Subscription lifecycle: activation after a verified payment, renewal,
 * expiry (with optional grace period) and the resulting card state changes.
 *
 * Every state change runs inside a database transaction together with the
 * order/payment rows so a partial activation can never be observed.
 */
final class SubscriptionService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::instance();
    }

    /**
     * Activate (or extend) a subscription for a paid order. Idempotent: if
     * the order already produced a subscription it is returned unchanged, so
     * a duplicate webhook cannot create a second subscription.
     *
     * @param array<string,mixed> $order
     * @return array<string,mixed> The active subscription row
     */
    public function activateForOrder(array $order): array
    {
        $orderId = (int) $order['id'];
        $subscriptions = new Subscription();

        $existing = $subscriptions->firstWhere(['order_id' => $orderId, 'status' => 'active']);
        if ($existing !== null) {
            return $existing;
        }

        $plan = (new Plan())->find((int) $order['plan_id']);
        if ($plan === null) {
            throw new RuntimeException('Plan for order ' . $orderId . ' no longer exists.');
        }

        return $this->db->transaction(function () use ($order, $plan, $orderId, $subscriptions): array {
            $userId = (int) $order['user_id'];
            $current = $subscriptions->activeFor($userId);

            // Stack the new term on top of any remaining time.
            $startsAt = now();
            if ($current !== null && !empty($current['ends_at']) && strtotime((string) $current['ends_at']) > time()) {
                $startsAt = (string) $current['ends_at'];
            }

            $durationDays = (int) $plan['duration_days'];
            $endsAt = $durationDays > 0
                ? date('Y-m-d H:i:s', strtotime($startsAt . ' +' . $durationDays . ' days'))
                : null;

            // Retire the previous subscription so only one is ever active.
            if ($current !== null) {
                $subscriptions->updateById((int) $current['id'], ['status' => 'expired']);
            }

            $subscriptionId = $subscriptions->create([
                'uuid'        => Model::uuid(),
                'user_id'     => $userId,
                'plan_id'     => (int) $plan['id'],
                'order_id'    => $orderId,
                'reseller_id' => $order['reseller_id'] ?? null,
                'status'      => 'active',
                'starts_at'   => $startsAt,
                'ends_at'     => $endsAt,
                'grace_until' => $endsAt !== null && PlanLimiter::graceDays() > 0
                    ? date('Y-m-d H:i:s', strtotime($endsAt . ' +' . PlanLimiter::graceDays() . ' days'))
                    : null,
                'amount'      => (float) $order['total'],
                'source'      => (string) ($order['meta']['source'] ?? 'self'),
                'snapshot'    => $this->planSnapshot($plan),
                'created_at'  => now(),
            ]);

            (new Order())->updateById($orderId, ['subscription_id' => $subscriptionId]);

            // Extend the owner's cards to the new expiry and re-publish any
            // card that had been expired.
            $this->syncCards($userId, $endsAt);

            PlanLimiter::flush($userId);

            (new Notification())->push(
                $userId,
                'subscription.activated',
                'Your ' . (string) $plan['name'] . ' plan is active',
                $endsAt === null ? 'Lifetime access enabled.' : 'Valid until ' . date('d M Y', (int) strtotime($endsAt)) . '.',
                'billing'
            );

            AuditLog::record('subscription.activated', 'subscription', $subscriptionId, [
                'plan'     => $plan['slug'],
                'order_id' => $orderId,
                'ends_at'  => $endsAt,
            ], $userId);

            return (array) $subscriptions->find($subscriptionId);
        });
    }

    /**
     * Grant a subscription without a payment (admin action or reseller sale
     * funded from the wallet).
     *
     * @return array<string,mixed>
     */
    public function grant(int $userId, int $planId, string $source = 'admin', ?int $orderId = null, ?int $resellerId = null): array
    {
        $plan = (new Plan())->find($planId);
        if ($plan === null) {
            throw new RuntimeException('Plan not found.');
        }

        return $this->db->transaction(function () use ($userId, $plan, $source, $orderId, $resellerId): array {
            $subscriptions = new Subscription();
            $current = $subscriptions->activeFor($userId);

            $startsAt = now();
            if ($current !== null && !empty($current['ends_at']) && strtotime((string) $current['ends_at']) > time()) {
                $startsAt = (string) $current['ends_at'];
            }
            $durationDays = (int) $plan['duration_days'];
            $endsAt = $durationDays > 0
                ? date('Y-m-d H:i:s', strtotime($startsAt . ' +' . $durationDays . ' days'))
                : null;

            if ($current !== null) {
                $subscriptions->updateById((int) $current['id'], ['status' => 'expired']);
            }

            $id = $subscriptions->create([
                'uuid'        => Model::uuid(),
                'user_id'     => $userId,
                'plan_id'     => (int) $plan['id'],
                'order_id'    => $orderId,
                'reseller_id' => $resellerId,
                'status'      => 'active',
                'starts_at'   => $startsAt,
                'ends_at'     => $endsAt,
                'amount'      => (float) $plan['price'],
                'source'      => $source,
                'snapshot'    => $this->planSnapshot($plan),
                'created_at'  => now(),
            ]);

            $this->syncCards($userId, $endsAt);
            PlanLimiter::flush($userId);

            AuditLog::record('subscription.granted', 'subscription', $id, [
                'plan' => $plan['slug'], 'source' => $source, 'user_id' => $userId,
            ]);

            return (array) $subscriptions->find($id);
        });
    }

    /** Extend an active subscription by N days (admin "extend" action). */
    public function extend(int $subscriptionId, int $days): bool
    {
        $subscriptions = new Subscription();
        $subscription = $subscriptions->find($subscriptionId);
        if ($subscription === null) {
            return false;
        }

        $base = !empty($subscription['ends_at']) && strtotime((string) $subscription['ends_at']) > time()
            ? (string) $subscription['ends_at']
            : now();
        $endsAt = date('Y-m-d H:i:s', strtotime($base . ' +' . $days . ' days'));

        return $this->db->transaction(function () use ($subscriptions, $subscriptionId, $subscription, $endsAt, $days): bool {
            $subscriptions->updateById($subscriptionId, ['status' => 'active', 'ends_at' => $endsAt]);
            $this->syncCards((int) $subscription['user_id'], $endsAt);
            PlanLimiter::flush((int) $subscription['user_id']);

            AuditLog::record('subscription.extended', 'subscription', $subscriptionId, [
                'days' => $days, 'ends_at' => $endsAt,
            ]);

            return true;
        });
    }

    public function cancel(int $subscriptionId, string $reason = ''): bool
    {
        $subscriptions = new Subscription();
        $subscription = $subscriptions->find($subscriptionId);
        if ($subscription === null) {
            return false;
        }

        $subscriptions->updateById($subscriptionId, [
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'auto_renew'   => 0,
        ]);
        PlanLimiter::flush((int) $subscription['user_id']);

        AuditLog::record('subscription.cancelled', 'subscription', $subscriptionId, ['reason' => $reason]);

        return true;
    }

    /**
     * Expire everything past its term. Safe to run from cron or on demand.
     *
     * @return array{expired:int,cards:int}
     */
    public function expireDue(): array
    {
        $subscriptions = new Subscription();
        $due = $subscriptions->dueForExpiry();
        $graceDays = PlanLimiter::graceDays();
        $expired = 0;

        foreach ($due as $subscription) {
            $graceUntil = $subscription['grace_until'] ?? null;
            if ($graceDays > 0 && $graceUntil !== null && strtotime((string) $graceUntil) > time()) {
                continue;                       // still inside the grace window
            }

            $subscriptions->markExpired((int) $subscription['id']);
            $expired++;
            PlanLimiter::flush((int) $subscription['user_id']);

            (new Notification())->push(
                (int) $subscription['user_id'],
                'subscription.expired',
                'Your subscription has expired',
                'Renew now to keep your digital cards online.',
                'billing'
            );
        }

        $cards = 0;
        if (PlanLimiter::expiryBehaviour() !== 'keep_live') {
            $cards = (new Card())->expireDue();
        }

        if ($expired > 0 || $cards > 0) {
            Logger::info('Subscription expiry sweep', ['subscriptions' => $expired, 'cards' => $cards]);
        }

        return ['expired' => $expired, 'cards' => $cards];
    }

    /**
     * Push the subscription end date onto the user's cards and restore any
     * card that was previously expired.
     */
    private function syncCards(int $userId, ?string $endsAt): void
    {
        $cards = new Card();
        $this->db->execute(
            'UPDATE `' . $cards->table() . '` SET `expires_at` = :expires, `updated_at` = :now
              WHERE `user_id` = :user AND `deleted_at` IS NULL',
            ['expires' => $endsAt, 'now' => now(), 'user' => $userId]
        );
        $this->db->execute(
            'UPDATE `' . $cards->table() . '` SET `status` = :published, `updated_at` = :now
              WHERE `user_id` = :user AND `deleted_at` IS NULL AND `status` = :expired',
            ['published' => 'published', 'now' => now(), 'user' => $userId, 'expired' => 'expired']
        );
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private function planSnapshot(array $plan): array
    {
        return [
            'slug'              => $plan['slug'] ?? '',
            'name'              => $plan['name'] ?? '',
            'price'             => $plan['price'] ?? 0,
            'duration_days'     => $plan['duration_days'] ?? 0,
            'card_limit'        => $plan['card_limit'] ?? 1,
            'product_limit'     => $plan['product_limit'] ?? 0,
            'service_limit'     => $plan['service_limit'] ?? 0,
            'gallery_limit'     => $plan['gallery_limit'] ?? 0,
            'premium_templates' => $plan['premium_templates'] ?? 0,
            'custom_domain'     => $plan['custom_domain'] ?? 0,
            'remove_branding'   => $plan['remove_branding'] ?? 0,
        ];
    }

    /**
     * Human readable status for the billing screen.
     *
     * @return array{plan:array<string,mixed>,subscription:array<string,mixed>|null,days_left:?int,is_expiring:bool}
     */
    public function summaryFor(int $userId): array
    {
        $subscription = (new Subscription())->activeFor($userId);
        $plan = PlanLimiter::planFor($userId);
        $daysLeft = null;

        if ($subscription !== null && !empty($subscription['ends_at'])) {
            $daysLeft = (int) ceil((strtotime((string) $subscription['ends_at']) - time()) / 86400);
        }

        return [
            'plan'         => $plan,
            'subscription' => $subscription,
            'days_left'    => $daysLeft,
            'is_expiring'  => $daysLeft !== null && $daysLeft <= 7,
        ];
    }
}
