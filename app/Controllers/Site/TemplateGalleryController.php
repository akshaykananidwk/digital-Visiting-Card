<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Models\Template;
use App\Models\TemplateCategory;
use App\Services\CardPresenter;
use App\Services\DemoCard;
use App\Services\PlanLimiter;
use App\Services\TemplateRenderer;

/** Public template marketplace with live previews. */
final class TemplateGalleryController extends Controller
{
    public function index(): Response
    {
        $templates = new Template();
        $filters = $this->filters();
        $page = max(1, $this->request->int('page', 1));

        $result = $templates->browse($filters, $page, 24);

        return $this->render('site.templates', [
            'title'      => 'Digital card designs',
            'metaDescription' => 'Browse ready-made digital visiting card designs for every industry — computer shops, CCTV, doctors, hotels, professionals and more.',
            'result'     => $result,
            'filters'    => $filters,
            'categories' => (new TemplateCategory())->tree(),
            'facets'     => $templates->facets(),
            'total'      => $templates->count(['is_active' => 1]),
        ]);
    }

    /** JSON endpoint used by the in-app design picker. */
    public function search(): Response
    {
        $result = (new Template())->browse($this->filters(), max(1, $this->request->int('page', 1)), 24);

        return $this->json([
            'success' => true,
            'total'   => $result['total'],
            'page'    => $result['page'],
            'pages'   => $result['pages'],
            'data'    => array_map(static fn (array $row): array => [
                'id'        => (int) $row['id'],
                'code'      => $row['code'],
                'name'      => $row['name'],
                'layout'    => $row['layout'],
                'style'     => $row['style'],
                'premium'   => (bool) $row['is_premium'],
                'preview'   => url('templates/preview/' . $row['code']),
            ], $result['data']),
        ]);
    }

    public function show(string $code): Response
    {
        $template = (new Template())->findByCode($code);
        if ($template === null || (int) $template['is_active'] !== 1) {
            throw HttpException::notFound('That design is no longer available.');
        }

        $category = $template['category_id'] !== null
            ? (new TemplateCategory())->find((int) $template['category_id'])
            : null;

        $canUse = !(bool) $template['is_premium']
            || (Auth::instance()->check() && PlanLimiter::canUsePremiumTemplate((int) Auth::instance()->id()));

        return $this->render('site.template-detail', [
            'title'    => $template['name'] . ' — digital card design',
            'template' => $template,
            'category' => $category,
            'canUse'   => $canUse,
            'related'  => (new Template())->where([
                'category_id' => $template['category_id'],
                'is_active'   => 1,
                'id'          => ['!=', (int) $template['id']],
            ], 'sort_order ASC', 8),
        ]);
    }

    /**
     * Live preview: the real card renderer driven by demo content, so what a
     * customer sees in the gallery is exactly what gets published.
     */
    public function preview(string $code): Response
    {
        $template = (new Template())->findByCode($code);
        if ($template === null) {
            throw HttpException::notFound();
        }

        $demo = DemoCard::build((int) $template['id'], $this->request->string('industry', (string) ($template['industry'] ?? '')));
        $presenter = new CardPresenter($demo, $template);
        $design = TemplateRenderer::forCard($demo, $template);

        return $this->render('card.preview', [
            'card'         => $presenter,
            'design'       => $design,
            'template'     => $template,
            'showBranding' => false,
        ])->header('X-Robots-Tag', 'noindex')
          ->header('Cache-Control', 'public, max-age=1800');
    }

    /** @return array<string,mixed> */
    private function filters(): array
    {
        return [
            'search'   => $this->request->string('q'),
            'category' => $this->request->int('category'),
            'premium'  => $this->request->string('premium'),
            'style'    => $this->request->string('style'),
            'layout'   => $this->request->string('layout'),
            'color'    => $this->request->string('color'),
            'mode'     => $this->request->string('mode'),
            'sort'     => $this->request->string('sort', 'featured'),
        ];
    }
}
