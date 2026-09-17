<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\AuditLog;
use App\Core\Response;
use App\Core\Url;
use App\Models\Card;
use App\Models\CardDailyStat;
use App\Models\Template;
use App\Services\CardBuilder;
use App\Services\CardPresenter;
use App\Services\PlanLimiter;
use App\Services\TemplateRenderer;
use Throwable;

final class CardController extends PanelController
{
    public function index(): Response
    {
        $userId = $this->userId();
        $cards = (new Card())->forUser($userId);
        $stats = new CardDailyStat();

        foreach ($cards as $index => $card) {
            $cards[$index]['stats'] = $stats->totals((int) $card['id']);
        }

        return $this->render('customer.cards.index', [
            'title'     => 'My cards',
            'cards'     => $cards,
            'canCreate' => PlanLimiter::canCreateCard($userId),
        ]);
    }

    public function create(): Response
    {
        $userId = $this->userId();
        $limit = PlanLimiter::canCreateCard($userId);

        if (!$limit['allowed']) {
            $this->error($limit['message']);

            return $this->redirect('billing');
        }

        $templateId = $this->request->int('template');
        $template = $templateId > 0 ? (new Template())->find($templateId) : null;

        return $this->render('customer.cards.create', [
            'title'      => 'Create a card',
            'template'   => $template,
            'suggested'  => (new Template())->where(['is_active' => 1], 'sort_order ASC', 12),
            'categories' => (new \App\Models\TemplateCategory())->tree(),
            'limit'      => $limit,
        ]);
    }

    public function store(): Response
    {
        $userId = $this->userId();

        $limit = PlanLimiter::canCreateCard($userId);
        if (!$limit['allowed']) {
            $this->error($limit['message']);

            return $this->redirect('billing');
        }

        $data = $this->validate([
            'title'             => 'required|string|min:2|max:150',
            'full_name'         => 'required|string|min:2|max:150',
            'designation'       => 'nullable|string|max:150',
            'business_name'     => 'nullable|string|max:190',
            'business_category' => 'nullable|string|max:120',
            'phone'             => 'required|phone|max:25',
            'whatsapp'          => 'nullable|phone|max:25',
            'email'             => 'nullable|email',
            'city'              => 'nullable|string|max:100',
            'slug'              => 'nullable|slug',
            'template_id'       => 'nullable|integer',
        ]);

        try {
            $card = (new CardBuilder())->create($userId, $data);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return $this->redirect('cards/create');
        }

        AuditLog::record('card.created', 'card', (int) $card['id'], ['slug' => $card['slug']]);
        $this->success('Card created. Add your details and publish when you are ready.');

        return $this->redirect('cards/' . (int) $card['id'] . '/editor');
    }

    public function preview(string $id): Response
    {
        $card = $this->ownedCard($id);
        $template = $card['template_id'] !== null ? (new Template())->find((int) $card['template_id']) : null;

        return $this->view->render('card.preview', [
            'card'         => new CardPresenter($card, $template),
            'design'       => TemplateRenderer::forCard($card, $template),
            'template'     => $template ?? ['name' => 'Default design'],
            'showBranding' => false,
        ])->header('X-Robots-Tag', 'noindex');
    }

    public function publish(string $id): Response
    {
        $card = $this->ownedCard($id);
        $cards = new Card();

        $missing = $this->missingRequirements($card);
        if ($missing !== []) {
            $this->error('Please complete these before publishing: ' . implode(', ', $missing) . '.');

            return $this->redirect('cards/' . (int) $card['id'] . '/editor');
        }

        $subscription = (new \App\Models\Subscription())->activeFor($this->userId());
        if ($subscription === null) {
            $this->error('Your subscription is not active. Renew your plan to publish this card.');

            return $this->redirect('billing');
        }

        $cards->publish((int) $card['id']);
        AuditLog::record('card.published', 'card', (int) $card['id'], ['slug' => $card['slug']]);

        $this->success('Your card is live at ' . Url::card((string) $card['slug']));

        return $this->redirect('cards/' . (int) $card['id'] . '/editor');
    }

    public function unpublish(string $id): Response
    {
        $card = $this->ownedCard($id);
        (new Card())->unpublish((int) $card['id']);
        AuditLog::record('card.unpublished', 'card', (int) $card['id']);

        $this->success('Your card is now a draft and is no longer publicly visible.');

        return $this->redirect('cards/' . (int) $card['id'] . '/editor');
    }

    public function duplicate(string $id): Response
    {
        $card = $this->ownedCard($id);

        try {
            $copy = (new CardBuilder())->duplicate((int) $card['id'], $this->userId());
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return $this->redirect('cards');
        }

        AuditLog::record('card.duplicated', 'card', (int) $copy['id'], ['from' => (int) $card['id']]);
        $this->success('Card duplicated.');

        return $this->redirect('cards/' . (int) $copy['id'] . '/editor');
    }

    public function destroy(string $id): Response
    {
        $card = $this->ownedCard($id);

        if ($this->request->string('confirm_slug') !== (string) $card['slug']) {
            $this->error('Type the card link exactly to confirm deletion.');

            return $this->redirect('cards/' . (int) $card['id'] . '/editor');
        }

        (new CardBuilder())->delete((int) $card['id'], $this->userId());
        AuditLog::record('card.deleted', 'card', (int) $card['id'], ['slug' => $card['slug']]);

        $this->success('Card deleted.');

        return $this->redirect('cards');
    }

    public function updateSlug(string $id): Response
    {
        $card = $this->ownedCard($id);

        try {
            $slug = (new CardBuilder())->changeSlug((int) $card['id'], $this->userId(), $this->request->string('slug'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return $this->redirect('cards/' . (int) $card['id'] . '/editor');
        }

        AuditLog::record('card.slug_changed', 'card', (int) $card['id'], ['from' => $card['slug'], 'to' => $slug]);
        $this->success('Your card link is now ' . Url::card($slug));

        return $this->redirect('cards/' . (int) $card['id'] . '/editor');
    }

    /**
     * @param array<string,mixed> $card
     * @return array<int,string>
     */
    private function missingRequirements(array $card): array
    {
        $missing = [];
        if (trim((string) ($card['full_name'] ?? '')) === '') {
            $missing[] = 'your name';
        }
        if (trim((string) ($card['phone'] ?? '')) === '' && trim((string) ($card['whatsapp'] ?? '')) === '') {
            $missing[] = 'a phone or WhatsApp number';
        }
        if ($card['template_id'] === null) {
            $missing[] = 'a design';
        }

        return $missing;
    }
}
