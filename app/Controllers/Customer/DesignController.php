<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\AuditLog;
use App\Core\Response;
use App\Models\Card;
use App\Models\Template;
use App\Models\TemplateCategory;
use App\Services\CardBuilder;
use App\Services\PlanLimiter;
use App\Services\TemplateRenderer;
use Throwable;

/** Design picker and per-card theme overrides. */
final class DesignController extends PanelController
{
    public function index(string $id): Response
    {
        $card = $this->ownedCard($id);
        $templates = new Template();

        $filters = [
            'search'   => $this->request->string('q'),
            'category' => $this->request->int('category'),
            'premium'  => $this->request->string('premium'),
            'style'    => $this->request->string('style'),
            'color'    => $this->request->string('color'),
            'mode'     => $this->request->string('mode'),
            'sort'     => $this->request->string('sort', 'featured'),
        ];

        return $this->render('customer.design', [
            'title'      => 'Design: ' . (string) $card['title'],
            'card'       => $card,
            'result'     => $templates->browse($filters, max(1, $this->request->int('page', 1)), 18),
            'filters'    => $filters,
            'categories' => (new TemplateCategory())->tree(),
            'facets'     => $templates->facets(),
            'current'    => $card['template_id'] !== null ? $templates->find((int) $card['template_id']) : null,
            'canPremium' => PlanLimiter::canUsePremiumTemplate($this->userId()),
            'overrides'  => is_array($card['theme_overrides'] ?? null) ? $card['theme_overrides'] : [],
            'fonts'      => TemplateRenderer::FONTS,
        ]);
    }

    public function apply(string $id): Response
    {
        $card = $this->ownedCard($id);
        $templateId = $this->request->int('template_id');

        try {
            (new CardBuilder())->applyTemplate((int) $card['id'], $this->userId(), $templateId);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return $this->redirect('cards/' . (int) $card['id'] . '/design');
        }

        AuditLog::record('card.design_changed', 'card', (int) $card['id'], ['template_id' => $templateId]);

        // Content is stored independently of the design, so nothing is lost.
        $this->success('Design applied. All of your content has been carried over.');

        return $this->redirect('cards/' . (int) $card['id'] . '/design');
    }

    public function saveTheme(string $id): Response
    {
        $card = $this->ownedCard($id);

        $overrides = [];
        $palette = [];
        foreach (['primary', 'secondary', 'accent', 'bg', 'surface', 'text'] as $key) {
            $value = $this->request->string('color_' . $key);
            if ($value !== '' && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1) {
                $palette[$key] = $value;
            }
        }
        if ($palette !== []) {
            $overrides['palette'] = $palette;
        }

        $fonts = [];
        foreach (['heading', 'body'] as $key) {
            $value = $this->request->string('font_' . $key);
            if (in_array($value, TemplateRenderer::FONTS, true)) {
                $fonts[$key] = $value;
            }
        }
        if ($fonts !== []) {
            $overrides['fonts'] = $fonts;
        }

        $radius = $this->request->int('radius', -1);
        if ($radius >= 0) {
            $overrides['radius'] = max(0, min(40, $radius));
        }

        $buttons = $this->request->string('buttons');
        if (in_array($buttons, ['pill', 'rounded', 'square'], true)) {
            $overrides['buttons'] = $buttons;
        }

        $mode = $this->request->string('mode');
        if (in_array($mode, ['light', 'dark'], true)) {
            $overrides['mode'] = $mode;
        }

        if ($this->request->has('effects')) {
            $overrides['effects'] = array_values(array_intersect(
                array_map('strval', $this->request->array('effects')),
                TemplateRenderer::EFFECTS
            ));
        }

        (new Card())->updateById((int) $card['id'], ['theme_overrides' => $overrides]);
        $this->success('Theme customisation saved.');

        return $this->redirect('cards/' . (int) $card['id'] . '/design');
    }

    public function resetTheme(string $id): Response
    {
        $card = $this->ownedCard($id);
        (new Card())->updateById((int) $card['id'], ['theme_overrides' => []]);

        $this->success('Theme reset to the template defaults.');

        return $this->redirect('cards/' . (int) $card['id'] . '/design');
    }
}
