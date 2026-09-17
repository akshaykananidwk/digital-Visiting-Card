<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Models\Card;

/** Auto-save and live-preview support for the editor. */
final class EditorApiController extends Controller
{
    /** Fields the auto-save endpoint accepts. */
    private const FIELDS = [
        'title', 'full_name', 'designation', 'business_name', 'tagline', 'about',
        'phone', 'whatsapp', 'email', 'website', 'address', 'city', 'state', 'pincode',
    ];

    public function autosave(string $id): Response
    {
        $userId = Auth::instance()->id();
        if ($userId === null) {
            return $this->fail('Unauthenticated.', 401);
        }

        $cards = new Card();
        $card = $cards->findForOwner((int) $id, $userId);
        if ($card === null) {
            return $this->fail('Card not found.', 404);
        }

        $draft = [];
        foreach (self::FIELDS as $field) {
            if ($this->request->has($field)) {
                $draft[$field] = mb_substr($this->request->string($field), 0, 5000);
            }
        }

        if ($draft === []) {
            return $this->ok('Nothing to save.');
        }

        // Drafts are stored separately so an unfinished edit never changes
        // what visitors currently see.
        $cards->updateById((int) $card['id'], ['draft_data' => $draft + ['saved_at' => now()]]);

        return $this->ok('Draft saved.', ['saved_at' => now('H:i')]);
    }

    public function preview(string $id): Response
    {
        $userId = Auth::instance()->id();
        if ($userId === null) {
            return $this->fail('Unauthenticated.', 401);
        }

        $card = (new Card())->findForOwner((int) $id, $userId);
        if ($card === null) {
            return $this->fail('Card not found.', 404);
        }

        return $this->json([
            'success' => true,
            'url'     => url('cards/' . (int) $card['id'] . '/preview'),
            'public'  => url('card/' . (string) $card['slug']),
            'status'  => $card['status'],
        ]);
    }
}
