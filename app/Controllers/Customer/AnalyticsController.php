<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Response;
use App\Models\Card;
use App\Services\AnalyticsService;
use App\Services\PlanLimiter;

final class AnalyticsController extends PanelController
{
    public function index(): Response
    {
        $userId = $this->userId();

        if (!PlanLimiter::canUseAnalytics($userId)) {
            $this->error('Analytics are not included in your current plan.');

            return $this->redirect('billing');
        }

        $days = $this->period();

        return $this->render('customer.analytics', [
            'title'  => 'Analytics',
            'report' => (new AnalyticsService())->userReport($userId, $days),
            'cards'  => (new Card())->forUser($userId),
            'days'   => $days,
            'card'   => null,
        ]);
    }

    public function card(string $id): Response
    {
        $card = $this->ownedCard($id);
        $userId = $this->userId();

        if (!PlanLimiter::canUseAnalytics($userId)) {
            $this->error('Analytics are not included in your current plan.');

            return $this->redirect('billing');
        }

        $days = $this->period();

        return $this->render('customer.analytics', [
            'title'  => 'Analytics: ' . (string) $card['title'],
            'report' => (new AnalyticsService())->cardReport((int) $card['id'], $days),
            'cards'  => (new Card())->forUser($userId),
            'days'   => $days,
            'card'   => $card,
        ]);
    }

    private function period(): int
    {
        $days = $this->request->int('days', 30);

        return in_array($days, [7, 30, 90, 365], true) ? $days : 30;
    }
}
