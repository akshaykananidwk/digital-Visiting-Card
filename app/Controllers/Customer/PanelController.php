<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Models\Card;
use App\Models\Lead;
use App\Models\Notification;
use App\Services\PlanLimiter;

/** Shared behaviour for every customer-panel screen. */
abstract class PanelController extends Controller
{
    /** @return array<string,mixed> */
    protected function currentUser(): array
    {
        $user = Auth::instance()->user();
        if ($user === null) {
            throw HttpException::unauthorized();
        }

        return $user;
    }

    protected function userId(): int
    {
        return (int) $this->currentUser()['id'];
    }

    /**
     * Load a card the signed-in user owns. Every card-scoped action goes
     * through here, which is what prevents one tenant reading another's data.
     *
     * @return array<string,mixed>
     */
    protected function ownedCard(int|string $cardId): array
    {
        $card = (new Card())->findForOwner((int) $cardId, $this->userId());

        if ($card === null) {
            throw HttpException::notFound('That card does not exist or does not belong to your account.');
        }

        return $card;
    }

    /** @param array<string,mixed> $data */
    protected function render(string $view, array $data = [], int $status = 200): Response
    {
        return parent::render($view, $data + $this->panelData(), $status);
    }

    /** @return array<string,mixed> */
    protected function panelData(): array
    {
        $userId = $this->userId();
        $unreadLeads = (new Lead())->unreadCount($userId);

        return [
            'navGroups' => [
                '' => [
                    ['href' => url('dashboard'), 'label' => 'Dashboard', 'icon' => 'grid'],
                    ['href' => url('cards'), 'label' => 'My cards', 'icon' => 'layout'],
                ],
                'Engagement' => [
                    ['href' => url('leads'), 'label' => 'Leads', 'icon' => 'inbox', 'count' => $unreadLeads],
                    ['href' => url('analytics'), 'label' => 'Analytics', 'icon' => 'bar-chart'],
                ],
                'Account' => [
                    ['href' => url('billing'), 'label' => 'Plan &amp; billing', 'icon' => 'credit-card'],
                    ['href' => url('account'), 'label' => 'Account', 'icon' => 'settings'],
                ],
            ],
            'mobileNav' => [
                ['href' => url('dashboard'), 'label' => 'Home', 'icon' => 'home'],
                ['href' => url('cards'), 'label' => 'Cards', 'icon' => 'layout'],
                ['href' => url('leads'), 'label' => 'Leads', 'icon' => 'inbox'],
                ['href' => url('analytics'), 'label' => 'Stats', 'icon' => 'bar-chart'],
                ['href' => url('account'), 'label' => 'Account', 'icon' => 'settings'],
            ],
            'planLimits'    => PlanLimiter::planFor($userId),
            'planUsage'     => PlanLimiter::usage($userId),
            'notifications' => (new Notification())->forUser($userId, 8),
        ];
    }
}
