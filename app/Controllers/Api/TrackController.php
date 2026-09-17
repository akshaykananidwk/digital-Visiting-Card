<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Card;
use App\Models\CardEvent;
use App\Services\AnalyticsService;
use App\Services\PlanLimiter;

/**
 * Public event tracking endpoint used by the card runtime.
 *
 * Deliberately permissive about CSRF (beacons cannot always carry the token)
 * but strict about everything else: the slug must resolve to a published
 * card, the event name must be whitelisted and the endpoint is rate limited.
 */
final class TrackController extends Controller
{
    public function store(): Response
    {
        $slug = strtolower($this->request->string('slug'));
        $event = $this->request->string('event');
        $label = $this->request->string('label') ?: null;

        if ($slug === '' || $event === '') {
            return Response::noContent();
        }
        if (!CardEvent::isValidType($event)) {
            return Response::noContent();
        }

        $card = (new Card())->findPublished($slug);
        if ($card === null) {
            return Response::noContent();
        }
        if (!PlanLimiter::canUseAnalytics((int) $card['user_id'])) {
            return Response::noContent();
        }

        (new AnalyticsService())->recordEvent((int) $card['id'], $event, $this->request, $label);

        return Response::noContent();
    }
}
