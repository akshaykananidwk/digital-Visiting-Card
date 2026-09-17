<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\AuditLog;
use App\Core\Response;
use App\Models\Card;
use App\Models\Template;
use App\Models\TemplateCategory;
use App\Models\User;
use App\Services\CardBuilder;
use App\Services\PlanLimiter;

/**
 * Guided first-card flow. Each step stores its answer on the user record so
 * a customer can leave and come back without losing progress.
 */
final class OnboardingController extends PanelController
{
    public function index(): Response
    {
        $user = $this->currentUser();

        if ((int) $user['onboarding_step'] >= 5 || (new Card())->countForUser((int) $user['id']) > 0) {
            return $this->redirect('dashboard');
        }

        $categories = (new TemplateCategory())->tree();

        return $this->render('customer.onboarding', [
            'title'      => 'Create your first card',
            'categories' => $categories,
            'suggested'  => (new Template())->where(['is_active' => 1, 'is_premium' => 0], 'sort_order ASC', 12),
            'step'       => max(1, (int) $user['onboarding_step']),
        ]);
    }

    public function store(): Response
    {
        $userId = $this->userId();

        $limit = PlanLimiter::canCreateCard($userId);
        if (!$limit['allowed']) {
            $this->error($limit['message']);

            return $this->redirect('dashboard');
        }

        $data = $this->validate([
            'business_category' => 'required|string|max:120',
            'title'             => 'required|string|min:2|max:150',
            'full_name'         => 'required|string|min:2|max:150',
            'business_name'     => 'nullable|string|max:190',
            'phone'             => 'required|phone|max:25',
            'whatsapp'          => 'nullable|phone|max:25',
            'email'             => 'nullable|email',
            'city'              => 'nullable|string|max:100',
            'template_id'       => 'nullable|integer',
            'slug'              => 'nullable|slug',
        ]);

        $card = (new CardBuilder())->create($userId, $data);

        (new User())->updateById($userId, ['onboarding_step' => 5]);
        AuditLog::record('card.created', 'card', (int) $card['id'], ['source' => 'onboarding']);

        $this->success('Your card has been created. Add the finishing touches and publish it.');

        return $this->redirect('cards/' . (int) $card['id'] . '/editor');
    }
}
