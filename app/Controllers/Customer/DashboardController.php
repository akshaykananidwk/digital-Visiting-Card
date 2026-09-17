<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Response;
use App\Models\Card;
use App\Models\CardDailyStat;
use App\Models\Lead;
use App\Services\PlanLimiter;
use App\Services\SubscriptionService;

final class DashboardController extends PanelController
{
    public function index(): Response
    {
        $userId = $this->userId();
        $cards = new Card();
        $stats = new CardDailyStat();

        $all = $cards->forUser($userId);
        $totals = $stats->totalsForUser($userId);

        return $this->render('customer.dashboard', [
            'title'        => 'Dashboard',
            'cards'        => $all,
            'counts'       => [
                'total'     => count($all),
                'published' => count(array_filter($all, static fn (array $c): bool => (string) $c['status'] === 'published')),
                'draft'     => count(array_filter($all, static fn (array $c): bool => (string) $c['status'] === 'draft')),
                'expired'   => count(array_filter($all, static fn (array $c): bool => (string) $c['status'] === 'expired')),
            ],
            'totals'       => $totals,
            'series'       => $stats->series(null, $userId, 'views', 30),
            'recentLeads'  => (new Lead())->recentForUser($userId, 5),
            'unreadLeads'  => (new Lead())->unreadCount($userId),
            'subscription' => (new SubscriptionService())->summaryFor($userId),
            'canCreate'    => PlanLimiter::canCreateCard($userId),
        ]);
    }
}
