<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuditLog;
use App\Core\Response;
use App\Models\TemplateCategory;

final class CategoryController extends AdminController
{
    public function index(): Response
    {
        $categories = new TemplateCategory();
        $categories->refreshCounts();

        return $this->render('admin.categories', [
            'title'      => 'Template categories',
            'tree'       => $categories->tree(),
            'flat'       => $categories->where([], 'sort_order ASC'),
        ]);
    }

    public function store(): Response
    {
        $data = $this->validate([
            'name'        => 'required|string|min:2|max:120',
            'slug'        => 'nullable|slug',
            'parent_id'   => 'nullable|integer',
            'icon'        => 'nullable|string|max:60',
            'description' => 'nullable|string|max:255',
            'sort_order'  => 'nullable|integer',
        ]);

        $categories = new TemplateCategory();
        $slug = (string) ($data['slug'] ?? '') !== '' ? (string) $data['slug'] : str_slug((string) $data['name']);

        if ($categories->slugExists($slug)) {
            $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        }

        $id = $categories->create([
            'parent_id'   => !empty($data['parent_id']) ? (int) $data['parent_id'] : null,
            'slug'        => $slug,
            'name'        => (string) $data['name'],
            'icon'        => $data['icon'] ?? 'tag',
            'description' => $data['description'] ?? null,
            'is_active'   => 1,
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'created_at'  => now(),
        ]);

        AuditLog::record('admin.category_created', 'template_category', $id, ['slug' => $slug]);
        $this->success('Category created.');

        return $this->redirect('admin/categories');
    }

    public function update(string $id): Response
    {
        $categories = new TemplateCategory();
        $category = $categories->find((int) $id);
        if ($category === null) {
            return $this->redirect('admin/categories');
        }

        $data = $this->validate([
            'name'        => 'required|string|min:2|max:120',
            'icon'        => 'nullable|string|max:60',
            'description' => 'nullable|string|max:255',
            'sort_order'  => 'nullable|integer',
        ]);

        $data['is_active'] = $this->request->bool('is_active', true) ? 1 : 0;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $categories->updateById((int) $category['id'], $data);
        AuditLog::record('admin.category_updated', 'template_category', (int) $category['id']);

        $this->success('Category updated.');

        return $this->redirect('admin/categories');
    }

    public function destroy(string $id): Response
    {
        $categories = new TemplateCategory();
        $category = $categories->find((int) $id);
        if ($category === null) {
            return $this->redirect('admin/categories');
        }

        $children = $categories->count(['parent_id' => (int) $category['id']]);
        if ($children > 0) {
            $this->error('Move or delete the ' . $children . ' sub-categories first.');

            return $this->redirect('admin/categories');
        }

        // Templates keep working; their category is simply cleared.
        $this->db()->execute(
            'UPDATE `' . $this->db()->table('templates') . '` SET `category_id` = NULL WHERE `category_id` = :id',
            ['id' => (int) $category['id']]
        );

        $categories->deleteById((int) $category['id']);
        AuditLog::record('admin.category_deleted', 'template_category', (int) $category['id']);

        $this->success('Category deleted.');

        return $this->redirect('admin/categories');
    }
}
