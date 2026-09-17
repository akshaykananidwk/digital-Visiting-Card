<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuditLog;
use App\Core\Response;
use App\Core\Uploader;
use App\Models\Template;
use App\Models\TemplateCategory;
use App\Services\TemplateFactory;
use App\Services\TemplateRenderer;
use Throwable;

final class TemplateController extends AdminController
{
    public function index(): Response
    {
        $filters = [
            'search'      => $this->request->string('q'),
            'category'    => $this->request->int('category'),
            'premium'     => $this->request->string('premium'),
            'style'       => $this->request->string('style'),
            'sort'        => $this->request->string('sort', 'featured'),
            'active_only' => false,
        ];

        $templates = new Template();

        return $this->render('admin.templates.index', [
            'title'      => 'Templates',
            'result'     => $templates->browse($filters, $this->page(), 24),
            'filters'    => $filters,
            'categories' => (new TemplateCategory())->tree(),
            'facets'     => $templates->facets(),
            'counts'     => [
                'total'    => $templates->count([]),
                'active'   => $templates->count(['is_active' => 1]),
                'premium'  => $templates->count(['is_premium' => 1]),
                'featured' => $templates->count(['is_featured' => 1]),
            ],
            'catalogue'  => count(TemplateFactory::CATEGORIES),
        ]);
    }

    public function create(): Response
    {
        return $this->render('admin.templates.edit', [
            'title'      => 'Create template',
            'template'   => null,
            'categories' => (new TemplateCategory())->tree(),
            'layouts'    => TemplateRenderer::LAYOUTS,
            'fonts'      => TemplateRenderer::FONTS,
            'effects'    => TemplateRenderer::EFFECTS,
            'defaults'   => TemplateRenderer::defaults(),
        ]);
    }

    public function store(): Response
    {
        $data = $this->templateInput();
        $templates = new Template();

        if ($templates->codeExists((string) $data['code'])) {
            $this->error('That template code is already in use.');

            return $this->redirect('admin/templates/create');
        }

        $data['sort_order'] = $templates->nextSortOrder();
        $data['created_by'] = $this->userId();
        $data['created_at'] = now();
        $data['preview_image'] = $this->uploadPreview();

        $id = $templates->create($data);
        (new TemplateCategory())->refreshCounts();

        AuditLog::record('admin.template_created', 'template', $id, ['code' => $data['code']]);
        $this->success('Template created.');

        return $this->redirect('admin/templates/' . $id . '/edit');
    }

    public function edit(string $id): Response
    {
        $template = (new Template())->find((int) $id);
        if ($template === null) {
            $this->error('Template not found.');

            return $this->redirect('admin/templates');
        }

        return $this->render('admin.templates.edit', [
            'title'      => 'Edit: ' . (string) $template['name'],
            'template'   => $template,
            'categories' => (new TemplateCategory())->tree(),
            'layouts'    => TemplateRenderer::LAYOUTS,
            'fonts'      => TemplateRenderer::FONTS,
            'effects'    => TemplateRenderer::EFFECTS,
            'defaults'   => TemplateRenderer::defaults(),
        ]);
    }

    public function update(string $id): Response
    {
        $templates = new Template();
        $template = $templates->find((int) $id);
        if ($template === null) {
            return $this->redirect('admin/templates');
        }

        $data = $this->templateInput();

        if ($templates->codeExists((string) $data['code'], (int) $template['id'])) {
            $this->error('That template code is already in use.');

            return $this->redirect('admin/templates/' . (int) $template['id'] . '/edit');
        }

        $preview = $this->uploadPreview();
        if ($preview !== null) {
            if (!empty($template['preview_image'])) {
                Uploader::delete((string) $template['preview_image']);
            }
            $data['preview_image'] = $preview;
        }

        $templates->updateById((int) $template['id'], $data);
        (new TemplateCategory())->refreshCounts();

        AuditLog::record('admin.template_updated', 'template', (int) $template['id'], ['code' => $data['code']]);
        $this->success('Template saved.');

        return $this->redirect('admin/templates/' . (int) $template['id'] . '/edit');
    }

    public function toggle(string $id): Response
    {
        $templates = new Template();
        $template = $templates->find((int) $id);
        if ($template === null) {
            return $this->redirect('admin/templates');
        }

        $field = $this->request->string('field', 'is_active');
        if (!in_array($field, ['is_active', 'is_premium', 'is_featured', 'is_popular'], true)) {
            return $this->redirect('admin/templates');
        }

        $value = (int) $template[$field] === 1 ? 0 : 1;
        $templates->updateById((int) $template['id'], [$field => $value]);
        (new TemplateCategory())->refreshCounts();

        AuditLog::record('admin.template_toggled', 'template', (int) $template['id'], [$field => $value]);

        if ($this->request->wantsJson()) {
            return $this->ok('Updated.', ['value' => $value]);
        }

        return $this->back('admin/templates');
    }

    public function duplicate(string $id): Response
    {
        $templates = new Template();
        $template = $templates->find((int) $id);
        if ($template === null) {
            return $this->redirect('admin/templates');
        }

        $payload = $template;
        unset($payload['id']);
        $payload['code'] = substr((string) $template['code'], 0, 30) . '-c' . substr(bin2hex(random_bytes(3)), 0, 5);
        $payload['name'] = mb_substr((string) $template['name'] . ' (copy)', 0, 150);
        $payload['usage_count'] = 0;
        $payload['sort_order'] = $templates->nextSortOrder();
        $payload['created_by'] = $this->userId();
        $payload['created_at'] = now();

        $newId = $templates->create($payload);
        AuditLog::record('admin.template_duplicated', 'template', $newId, ['from' => (int) $template['id']]);

        $this->success('Template duplicated.');

        return $this->redirect('admin/templates/' . $newId . '/edit');
    }

    public function destroy(string $id): Response
    {
        $templates = new Template();
        $template = $templates->find((int) $id);
        if ($template === null) {
            return $this->redirect('admin/templates');
        }

        $inUse = (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM `' . $this->db()->table('cards') . '` WHERE `template_id` = :id AND `deleted_at` IS NULL',
            ['id' => (int) $template['id']]
        );

        if ($inUse > 0) {
            // Deactivate instead of deleting so live cards keep rendering.
            $templates->updateById((int) $template['id'], ['is_active' => 0]);
            $this->error($inUse . ' live card(s) use this template, so it was deactivated instead of deleted.');

            return $this->redirect('admin/templates');
        }

        if (!empty($template['preview_image'])) {
            Uploader::delete((string) $template['preview_image']);
        }
        $templates->deleteById((int) $template['id']);
        (new TemplateCategory())->refreshCounts();

        AuditLog::record('admin.template_deleted', 'template', (int) $template['id'], ['code' => $template['code']]);
        $this->success('Template deleted.');

        return $this->redirect('admin/templates');
    }

    /** Bulk-generate designs from the built-in design-token catalogue. */
    public function generate(): Response
    {
        $perCategory = max(1, min(80, $this->request->int('per_category', 24)));

        try {
            @set_time_limit(600);
            $result = (new TemplateFactory())->generate($perCategory);
            AuditLog::record('admin.templates_generated', 'template', null, $result);

            $this->success(sprintf(
                '%d design(s) generated, %d already existed. The library now has %d designs.',
                $result['created'],
                $result['skipped'],
                $result['total']
            ));
        } catch (Throwable $e) {
            $this->error('Generation failed: ' . $e->getMessage());
        }

        return $this->redirect('admin/templates');
    }

    /** @return array<string,mixed> */
    private function templateInput(): array
    {
        $data = $this->validate([
            'code'         => 'required|alpha_dash|max:40',
            'name'         => 'required|string|min:2|max:150',
            'category_id'  => 'nullable|integer',
            'layout'       => 'required|in:' . implode(',', array_keys(TemplateRenderer::LAYOUTS)),
            'theme_mode'   => 'required|in:light,dark,auto',
            'style'        => 'nullable|string|max:60',
            'industry'     => 'nullable|string|max:80',
            'color_family' => 'nullable|string|max:40',
            'tags'         => 'nullable|string|max:255',
        ]);

        $palette = [];
        foreach (['primary', 'secondary', 'accent', 'bg', 'surface', 'text', 'muted', 'border'] as $key) {
            $value = $this->request->string('color_' . $key);
            if ($value !== '' && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1) {
                $palette[$key] = $value;
            }
        }
        $palette['on_primary'] = TemplateRenderer::readable($palette['primary'] ?? '#4f46e5');

        $effects = array_values(array_intersect(
            array_map('strval', $this->request->array('effects')),
            TemplateRenderer::EFFECTS
        ));

        $data['config'] = TemplateRenderer::normalise([
            'palette'      => $palette,
            'fonts'        => [
                'heading' => $this->request->string('font_heading', 'Poppins'),
                'body'    => $this->request->string('font_body', 'Inter'),
            ],
            'mode'         => (string) $data['theme_mode'] === 'dark' ? 'dark' : 'light',
            'radius'       => $this->request->int('radius', 22),
            'buttons'      => $this->request->string('buttons', 'pill'),
            'cover'        => $this->request->string('cover', 'gradient'),
            'pattern'      => $this->request->string('pattern', 'none'),
            'avatar'       => $this->request->string('avatar', 'circle'),
            'density'      => $this->request->string('density', 'comfortable'),
            'align'        => $this->request->string('align', 'center'),
            'cover_height' => $this->request->int('cover_height', 190),
            'shadow'       => $this->request->string('shadow', 'soft'),
            'effects'      => $effects,
        ]);

        $data['category_id'] = !empty($data['category_id']) ? (int) $data['category_id'] : null;
        $data['is_premium'] = $this->request->bool('is_premium') ? 1 : 0;
        $data['is_active'] = $this->request->bool('is_active', true) ? 1 : 0;
        $data['is_featured'] = $this->request->bool('is_featured') ? 1 : 0;
        $data['is_popular'] = $this->request->bool('is_popular') ? 1 : 0;

        return $data;
    }

    private function uploadPreview(): ?string
    {
        $file = $this->request->file('preview_image');
        if ($file === null) {
            return null;
        }

        try {
            return (new Uploader())->image($file, 'templates', 900);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return null;
        }
    }
}
