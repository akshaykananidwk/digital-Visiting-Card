<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Settings;
use App\Core\Url;
use App\Models\Card;
use App\Models\CardGallery;
use App\Models\CardProduct;
use App\Models\CardSection;
use App\Models\CardService as CardServiceModel;
use App\Models\CardSocialLink;
use App\Models\Model;
use App\Models\QrCodeRecord;
use App\Models\Subscription;
use App\Models\Template;
use App\Models\User;
use RuntimeException;

/** Creation, duplication and deletion of cards, with all side effects. */
final class CardBuilder
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::instance();
    }

    /**
     * Create a card with its default sections and QR record.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function create(int $userId, array $data): array
    {
        $cards = new Card();
        $user = (new User())->find($userId);
        if ($user === null) {
            throw new RuntimeException('User not found.');
        }

        $templateId = $this->resolveTemplate($userId, isset($data['template_id']) ? (int) $data['template_id'] : null, (string) ($data['business_category'] ?? ''));

        $slugBase = (string) ($data['slug'] ?? '');
        if ($slugBase === '') {
            $slugBase = (string) ($data['business_name'] ?? $data['full_name'] ?? $data['title'] ?? 'card');
        }
        $slug = $cards->uniqueSlug($slugBase);

        $subscription = (new Subscription())->activeFor($userId);

        return $this->db->transaction(function () use ($cards, $userId, $user, $data, $templateId, $slug, $subscription): array {
            $cardId = $cards->create([
                'uuid'              => Model::uuid(),
                'user_id'           => $userId,
                'reseller_id'       => $user['reseller_id'] ?? null,
                'template_id'       => $templateId,
                'slug'              => $slug,
                'status'            => 'draft',
                'title'             => (string) ($data['title'] ?? $data['full_name'] ?? 'My card'),
                'full_name'         => $data['full_name'] ?? null,
                'designation'       => $data['designation'] ?? null,
                'business_name'     => $data['business_name'] ?? null,
                'business_category' => $data['business_category'] ?? null,
                'phone'             => $data['phone'] ?? null,
                'whatsapp'          => $data['whatsapp'] ?? ($data['phone'] ?? null),
                'whatsapp_message'  => (string) (Settings::get('default_whatsapp_message') ?: 'Hello, I found your digital visiting card and would like to know more.'),
                'email'             => $data['email'] ?? $user['email'],
                'city'              => $data['city'] ?? null,
                'state'             => $data['state'] ?? null,
                'country'           => (string) (Settings::get('default_country') ?: 'India'),
                'business_hours'    => self::defaultHours(),
                'settings'          => self::defaultSettings(),
                'expires_at'        => $subscription['ends_at'] ?? null,
                'created_at'        => now(),
            ]);

            (new CardSection())->seedDefaults($cardId);
            (new QrCodeRecord())->sync($cardId, Url::card($slug) . '?src=qr');

            if ($templateId !== null) {
                (new Template())->incrementUsage($templateId);
            }

            PlanLimiter::flush($userId);

            return (array) (new Card())->find($cardId);
        });
    }

    /**
     * Duplicate a card with all of its content.
     *
     * @return array<string,mixed>
     */
    public function duplicate(int $cardId, int $userId): array
    {
        $cards = new Card();
        $source = $cards->findForOwner($cardId, $userId);
        if ($source === null) {
            throw new RuntimeException('Card not found.');
        }

        $limit = PlanLimiter::canCreateCard($userId);
        if (!$limit['allowed']) {
            throw new RuntimeException($limit['message']);
        }

        return $this->db->transaction(function () use ($cards, $source, $userId): array {
            $slug = $cards->uniqueSlug((string) $source['slug'] . '-copy');

            $payload = $source;
            unset($payload['id']);
            $payload['uuid'] = Model::uuid();
            $payload['slug'] = $slug;
            $payload['status'] = 'draft';
            $payload['title'] = mb_substr((string) $source['title'] . ' (copy)', 0, 150);
            $payload['views_count'] = 0;
            $payload['leads_count'] = 0;
            $payload['published_at'] = null;
            $payload['created_at'] = now();
            $payload['updated_at'] = now();

            $newId = $cards->create($payload);

            foreach ((new CardServiceModel())->forCard((int) $source['id']) as $row) {
                unset($row['id']);
                $row['card_id'] = $newId;
                $row['created_at'] = now();
                (new CardServiceModel())->create($row);
            }
            foreach ((new CardProduct())->forCard((int) $source['id']) as $row) {
                unset($row['id']);
                $row['card_id'] = $newId;
                $row['created_at'] = now();
                (new CardProduct())->create($row);
            }
            foreach ((new CardGallery())->forCard((int) $source['id']) as $row) {
                unset($row['id']);
                $row['card_id'] = $newId;
                $row['created_at'] = now();
                (new CardGallery())->create($row);
            }
            foreach ((new CardSocialLink())->forCard((int) $source['id']) as $row) {
                unset($row['id']);
                $row['card_id'] = $newId;
                $row['created_at'] = now();
                (new CardSocialLink())->create($row);
            }
            foreach ((new CardSection())->forCard((int) $source['id']) as $row) {
                unset($row['id']);
                $row['card_id'] = $newId;
                $row['created_at'] = now();
                (new CardSection())->create($row);
            }

            (new QrCodeRecord())->sync($newId, Url::card($slug) . '?src=qr');
            PlanLimiter::flush($userId);

            return (array) $cards->find($newId);
        });
    }

    /**
     * Change a card's design without touching its content.
     *
     * The template only supplies design tokens, so a switch is a single
     * column update — there is no data to migrate between templates.
     */
    public function applyTemplate(int $cardId, int $userId, int $templateId): bool
    {
        $cards = new Card();
        $card = $cards->findForOwner($cardId, $userId);
        if ($card === null) {
            return false;
        }

        $template = (new Template())->find($templateId);
        if ($template === null || (int) $template['is_active'] !== 1) {
            throw new RuntimeException('That design is not available.');
        }
        if ((int) $template['is_premium'] === 1 && !PlanLimiter::canUsePremiumTemplate($userId)) {
            throw new RuntimeException('Premium designs are not included in your current plan.');
        }

        $cards->updateById($cardId, ['template_id' => $templateId]);
        (new Template())->incrementUsage($templateId);

        return true;
    }

    /** Rename the public slug and refresh the QR target. */
    public function changeSlug(int $cardId, int $userId, string $slug): string
    {
        $cards = new Card();
        $card = $cards->findForOwner($cardId, $userId);
        if ($card === null) {
            throw new RuntimeException('Card not found.');
        }

        $slug = strtolower(trim($slug));
        if (preg_match('/^[a-z0-9][a-z0-9\-]{1,98}[a-z0-9]$/', $slug) !== 1) {
            throw new RuntimeException('The link may only contain lowercase letters, numbers and dashes (3–100 characters).');
        }
        if (in_array($slug, Card::RESERVED_SLUGS, true)) {
            throw new RuntimeException('That link is reserved. Please choose another.');
        }
        if ($cards->slugExists($slug, $cardId)) {
            throw new RuntimeException('That link is already taken. Please choose another.');
        }

        $cards->updateById($cardId, ['slug' => $slug]);
        (new QrCodeRecord())->sync($cardId, Url::card($slug) . '?src=qr');

        return $slug;
    }

    public function delete(int $cardId, int $userId): bool
    {
        $cards = new Card();
        $card = $cards->findForOwner($cardId, $userId);
        if ($card === null) {
            return false;
        }

        $cards->deleteById($cardId);
        PlanLimiter::flush($userId);

        return true;
    }

    private function resolveTemplate(int $userId, ?int $requested, string $category): ?int
    {
        $templates = new Template();

        if ($requested !== null && $requested > 0) {
            $template = $templates->find($requested);
            if ($template !== null && (int) $template['is_active'] === 1) {
                $premiumAllowed = (int) $template['is_premium'] === 0 || PlanLimiter::canUsePremiumTemplate($userId);
                if ($premiumAllowed) {
                    return (int) $template['id'];
                }
            }
        }

        // Fall back to a free design that matches the chosen industry.
        if ($category !== '') {
            $match = $templates->browse([
                'search'  => $category,
                'premium' => 'free',
                'sort'    => 'featured',
            ], 1, 1);
            if ($match['data'] !== []) {
                return (int) $match['data'][0]['id'];
            }
        }

        $fallback = $templates->where(['is_active' => 1, 'is_premium' => 0], 'sort_order ASC', 1);

        return $fallback === [] ? null : (int) $fallback[0]['id'];
    }

    /** @return array<string,array<string,mixed>> */
    public static function defaultHours(): array
    {
        $hours = [];
        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat'] as $day) {
            $hours[$day] = ['open' => '09:30', 'close' => '19:00', 'closed' => false];
        }
        $hours['sun'] = ['open' => '', 'close' => '', 'closed' => true];

        return $hours;
    }

    /** @return array<string,mixed> */
    public static function defaultSettings(): array
    {
        return [
            'enquiry_enabled'  => true,
            'show_qr'          => true,
            'vcard_enabled'    => true,
            'show_powered_by'  => true,
            'noindex'          => false,
        ];
    }
}
