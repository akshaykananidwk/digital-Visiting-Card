<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Url;
use App\Models\Card;
use App\Models\Template;
use App\Services\CardPresenter;

/**
 * Read-only JSON representation of a card — the foundation for the future
 * mobile applications. Only published cards are exposed publicly, and only
 * the fields the owner chose to publish.
 */
final class CardApiController extends Controller
{
    public function show(string $slug): Response
    {
        $card = (new Card())->findPublished($slug);
        if ($card === null) {
            return $this->fail('Card not found.', 404);
        }

        $template = $card['template_id'] !== null ? (new Template())->find((int) $card['template_id']) : null;
        $presenter = new CardPresenter($card, $template);

        return $this->json([
            'success' => true,
            'data'    => [
                'slug'        => $card['slug'],
                'url'         => $presenter->url(),
                'name'        => $presenter->displayName(),
                'designation' => $card['designation'],
                'business'    => $card['business_name'],
                'category'    => $card['business_category'],
                'about'       => $card['about'],
                'images'      => [
                    'profile' => $presenter->image('profile_image'),
                    'cover'   => $presenter->image('cover_image'),
                    'logo'    => $presenter->image('logo_image'),
                ],
                'contact' => [
                    'phone'     => $card['phone'],
                    'whatsapp'  => $presenter->whatsappLink(),
                    'email'     => $card['email'],
                    'website'   => $presenter->websiteLink(),
                    'address'   => $presenter->fullAddress(),
                    'directions'=> $presenter->directionsLink(),
                ],
                'hours'    => $presenter->businessHours(),
                'open'     => $presenter->openState(),
                'social'   => $presenter->social(),
                'services' => array_map(static fn (array $row): array => [
                    'title' => $row['title'], 'description' => $row['description'], 'price' => $row['price'],
                ], $presenter->services()),
                'products' => array_map(static fn (array $row): array => [
                    'name'  => $row['name'], 'price' => $row['price'], 'discount_price' => $row['discount_price'],
                    'image' => $row['image'] ? upload_url((string) $row['image']) : null,
                ], $presenter->products()),
                'gallery'  => array_map(static fn (array $row): array => [
                    'type' => $row['type'],
                    'url'  => (string) $row['type'] === 'image' ? upload_url((string) $row['path']) : $row['path'],
                ], $presenter->gallery()),
                'vcard'    => Url::to('card/' . $card['slug'] . '/vcard'),
                'qr'       => Url::to('card/' . $card['slug'] . '/qr?format=svg'),
            ],
        ])->header('Cache-Control', 'public, max-age=120');
    }

    /** Cards belonging to the signed-in user. */
    public function mine(): Response
    {
        $userId = Auth::instance()->id();
        if ($userId === null) {
            return $this->fail('Unauthenticated.', 401);
        }

        $cards = (new Card())->forUser($userId);

        return $this->json([
            'success' => true,
            'data'    => array_map(static fn (array $card): array => [
                'id'      => (int) $card['id'],
                'title'   => $card['title'],
                'slug'    => $card['slug'],
                'status'  => $card['status'],
                'url'     => Url::card((string) $card['slug']),
                'views'   => (int) $card['views_count'],
                'leads'   => (int) $card['leads_count'],
                'updated' => $card['updated_at'],
            ], $cards),
        ]);
    }
}
