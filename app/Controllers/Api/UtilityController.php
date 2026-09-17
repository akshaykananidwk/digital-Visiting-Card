<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Models\Card;

final class UtilityController extends Controller
{
    /** Live availability check for the card link picker. */
    public function slugAvailable(): Response
    {
        $slug = strtolower(trim($this->request->string('slug')));
        $cardId = $this->request->int('card_id');

        if ($slug === '') {
            return $this->json(['available' => false, 'message' => 'Enter a link.']);
        }
        if (preg_match('/^[a-z0-9][a-z0-9\-]{1,98}[a-z0-9]$/', $slug) !== 1) {
            return $this->json(['available' => false, 'message' => 'Use 3–100 lowercase letters, numbers or dashes.']);
        }
        if (in_array($slug, Card::RESERVED_SLUGS, true)) {
            return $this->json(['available' => false, 'message' => 'That link is reserved.']);
        }

        $cards = new Card();

        // A signed-in user may only exclude a card they own.
        $except = null;
        if ($cardId > 0 && Auth::instance()->check()) {
            $owned = $cards->findForOwner($cardId, (int) Auth::instance()->id());
            $except = $owned === null ? null : $cardId;
        }

        $taken = $cards->slugExists($slug, $except);

        return $this->json([
            'available' => !$taken,
            'slug'      => $slug,
            'message'   => $taken ? 'That link is already taken.' : 'Available',
        ]);
    }
}
