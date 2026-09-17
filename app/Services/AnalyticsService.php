<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Models\CardDailyStat;
use App\Models\CardEvent;
use App\Models\CardView;
use Throwable;

/**
 * Privacy-conscious analytics.
 *
 * Raw IP addresses are never stored: visitors are identified by a salted
 * daily hash of (ip + user agent + card), which allows unique-visitor counts
 * without keeping personal data or being able to track a visitor across days.
 */
final class AnalyticsService
{
    public function recordView(int $cardId, Request $request, ?string $source = null): void
    {
        try {
            if ($this->isBot($request->userAgent())) {
                return;
            }

            $hash = $this->visitorHash($cardId, $request);
            $views = new CardView();
            $isUnique = !$views->seenToday($cardId, $hash);

            $views->create([
                'card_id'       => $cardId,
                'visitor_hash'  => $hash,
                'referrer_host' => $this->refererHost($request->referer()),
                'device_type'   => $this->deviceType($request->userAgent()),
                'source'        => $source !== null ? substr($source, 0, 40) : null,
                'viewed_on'     => date('Y-m-d'),
                'created_at'    => now(),
            ]);

            $stats = new CardDailyStat();
            $stats->bump($cardId, 'view');
            if ($isUnique) {
                $stats->bump($cardId, 'unique_view');
            }

            Database::instance()->execute(
                'UPDATE `' . Database::instance()->table('cards') . '` SET `views_count` = `views_count` + 1 WHERE `id` = :id',
                ['id' => $cardId]
            );
        } catch (Throwable $e) {
            // Analytics must never break a public card render.
            \App\Core\Logger::warning('View tracking failed: ' . $e->getMessage(), ['card_id' => $cardId]);
        }
    }

    public function recordEvent(int $cardId, string $eventType, Request $request, ?string $label = null): bool
    {
        if (!CardEvent::isValidType($eventType)) {
            return false;
        }

        try {
            if ($this->isBot($request->userAgent())) {
                return false;
            }

            (new CardEvent())->create([
                'card_id'      => $cardId,
                'event_type'   => $eventType,
                'label'        => $label !== null ? substr($label, 0, 120) : null,
                'visitor_hash' => $this->visitorHash($cardId, $request),
                'device_type'  => $this->deviceType($request->userAgent()),
                'occurred_on'  => date('Y-m-d'),
                'created_at'   => now(),
            ]);

            (new CardDailyStat())->bump($cardId, $eventType);

            if ($eventType === 'qr_scan') {
                (new \App\Models\QrCodeRecord())->registerScan($cardId);
            }

            return true;
        } catch (Throwable $e) {
            \App\Core\Logger::warning('Event tracking failed: ' . $e->getMessage(), [
                'card_id' => $cardId, 'event' => $eventType,
            ]);

            return false;
        }
    }

    /**
     * Full analytics payload for one card.
     *
     * @return array<string,mixed>
     */
    public function cardReport(int $cardId, int $days = 30): array
    {
        $stats = new CardDailyStat();
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $to = date('Y-m-d');

        return [
            'totals'      => $stats->totals($cardId),
            'period'      => $stats->totals($cardId, $from, $to),
            'views'       => $stats->series($cardId, null, 'views', $days),
            'unique'      => $stats->series($cardId, null, 'unique_views', $days),
            'whatsapp'    => $stats->series($cardId, null, 'whatsapp', $days),
            'calls'       => $stats->series($cardId, null, 'calls', $days),
            'devices'     => $this->deviceBreakdown($cardId, $from, $to),
            'referrers'   => $this->referrerBreakdown($cardId, $from, $to),
            'days'        => $days,
            'from'        => $from,
            'to'          => $to,
        ];
    }

    /** @return array<string,mixed> */
    public function userReport(int $userId, int $days = 30): array
    {
        $stats = new CardDailyStat();
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $to = date('Y-m-d');

        return [
            'totals' => $stats->totalsForUser($userId),
            'period' => $stats->totalsForUser($userId, $from, $to),
            'views'  => $stats->series(null, $userId, 'views', $days),
            'days'   => $days,
        ];
    }

    /** @return array<int,array{label:string,value:int}> */
    private function deviceBreakdown(int $cardId, string $from, string $to): array
    {
        $rows = Database::instance()->select(
            'SELECT `device_type` AS label, COUNT(*) AS value FROM `' . Database::instance()->table('card_views') . '`
              WHERE `card_id` = :card AND `viewed_on` BETWEEN :from AND :to
              GROUP BY `device_type` ORDER BY value DESC',
            ['card' => $cardId, 'from' => $from, 'to' => $to]
        );

        return array_map(static fn (array $row): array => [
            'label' => (string) $row['label'],
            'value' => (int) $row['value'],
        ], $rows);
    }

    /** @return array<int,array{label:string,value:int}> */
    private function referrerBreakdown(int $cardId, string $from, string $to, int $limit = 8): array
    {
        $rows = Database::instance()->select(
            'SELECT COALESCE(NULLIF(`referrer_host`, ""), "direct") AS label, COUNT(*) AS value
               FROM `' . Database::instance()->table('card_views') . '`
              WHERE `card_id` = :card AND `viewed_on` BETWEEN :from AND :to
              GROUP BY label ORDER BY value DESC LIMIT ' . max(1, min(50, $limit)),
            ['card' => $cardId, 'from' => $from, 'to' => $to]
        );

        return array_map(static fn (array $row): array => [
            'label' => (string) $row['label'],
            'value' => (int) $row['value'],
        ], $rows);
    }

    /** Salted, rotating hash — not reversible to an IP address. */
    private function visitorHash(int $cardId, Request $request): string
    {
        $salt = (string) Config::get('app.key', 'dvc-analytics');

        return hash('sha256', implode('|', [
            $request->ip(),
            $request->userAgent(),
            $cardId,
            date('Y-m-d'),
            $salt,
        ]));
    }

    private function deviceType(string $userAgent): string
    {
        if ($userAgent === '') {
            return 'unknown';
        }
        if ($this->isBot($userAgent)) {
            return 'bot';
        }
        if (preg_match('/iPad|Tablet|PlayBook|Silk|Android(?!.*Mobile)/i', $userAgent) === 1) {
            return 'tablet';
        }
        if (preg_match('/Mobile|iPhone|Android|BlackBerry|IEMobile|Opera Mini/i', $userAgent) === 1) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function isBot(string $userAgent): bool
    {
        if ($userAgent === '') {
            return false;
        }

        return preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|curl|wget|headless|lighthouse|pingdom|uptime/i', $userAgent) === 1;
    }

    private function refererHost(string $referer): ?string
    {
        if ($referer === '') {
            return null;
        }
        $host = parse_url($referer, PHP_URL_HOST);

        return is_string($host) ? substr(strtolower($host), 0, 190) : null;
    }

    /** Housekeeping: drop raw rows older than the retention window. */
    public function prune(int $retentionDays = 400): int
    {
        $db = Database::instance();
        $deleted = $db->execute(
            'DELETE FROM `' . $db->table('card_views') . '` WHERE `viewed_on` < DATE_SUB(CURDATE(), INTERVAL :days DAY)',
            ['days' => $retentionDays]
        );
        $deleted += $db->execute(
            'DELETE FROM `' . $db->table('card_events') . '` WHERE `occurred_on` < DATE_SUB(CURDATE(), INTERVAL :days DAY)',
            ['days' => $retentionDays]
        );

        return $deleted;
    }
}
