<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuditLog;
use App\Core\Response;
use App\Models\Plan;
use App\Models\Subscription;

final class PlanController extends AdminController
{
    public function index(): Response
    {
        $plans = (new Plan())->where([], 'sort_order ASC');
        $subscriptions = new Subscription();

        foreach ($plans as $index => $plan) {
            $plans[$index]['active_subscribers'] = $subscriptions->count([
                'plan_id' => (int) $plan['id'],
                'status'  => 'active',
            ]);
        }

        return $this->render('admin.plans.index', ['title' => 'Plans', 'plans' => $plans]);
    }

    public function create(): Response
    {
        return $this->render('admin.plans.edit', ['title' => 'Create plan', 'plan' => null]);
    }

    public function store(): Response
    {
        $data = $this->planInput();
        $plans = new Plan();

        if ($plans->slugExists((string) $data['slug'])) {
            $this->error('That plan slug is already in use.');

            return $this->redirect('admin/plans/create');
        }

        $data['created_at'] = now();
        $id = $plans->create($data);

        AuditLog::record('admin.plan_created', 'plan', $id, ['slug' => $data['slug']]);
        $this->success('Plan created.');

        return $this->redirect('admin/plans');
    }

    public function edit(string $id): Response
    {
        $plan = (new Plan())->find((int) $id);
        if ($plan === null) {
            $this->error('Plan not found.');

            return $this->redirect('admin/plans');
        }

        return $this->render('admin.plans.edit', ['title' => 'Edit: ' . (string) $plan['name'], 'plan' => $plan]);
    }

    public function update(string $id): Response
    {
        $plans = new Plan();
        $plan = $plans->find((int) $id);
        if ($plan === null) {
            return $this->redirect('admin/plans');
        }

        $data = $this->planInput();
        if ($plans->slugExists((string) $data['slug'], (int) $plan['id'])) {
            $this->error('That plan slug is already in use.');

            return $this->redirect('admin/plans/' . (int) $plan['id'] . '/edit');
        }

        $plans->updateById((int) $plan['id'], $data);

        // Limits are read from the plan on every request, so existing
        // subscribers pick the change up immediately.
        \App\Services\PlanLimiter::flush();

        AuditLog::record('admin.plan_updated', 'plan', (int) $plan['id'], ['slug' => $data['slug']]);
        $this->success('Plan updated. Existing subscribers now use the new limits.');

        return $this->redirect('admin/plans');
    }

    public function destroy(string $id): Response
    {
        $plans = new Plan();
        $plan = $plans->find((int) $id);
        if ($plan === null) {
            return $this->redirect('admin/plans');
        }

        $active = (new Subscription())->count(['plan_id' => (int) $plan['id'], 'status' => 'active']);
        if ($active > 0) {
            $plans->updateById((int) $plan['id'], ['is_active' => 0]);
            $this->error($active . ' subscriber(s) are on this plan, so it was hidden instead of deleted.');

            return $this->redirect('admin/plans');
        }

        $plans->deleteById((int) $plan['id']);
        AuditLog::record('admin.plan_deleted', 'plan', (int) $plan['id'], ['slug' => $plan['slug']]);

        $this->success('Plan deleted.');

        return $this->redirect('admin/plans');
    }

    /** @return array<string,mixed> */
    private function planInput(): array
    {
        $data = $this->validate([
            'name'             => 'required|string|min:2|max:100',
            'slug'             => 'required|slug|max:60',
            'description'      => 'nullable|string|max:255',
            'price'            => 'required|numeric|min:0|max:9999999',
            'reseller_price'   => 'nullable|numeric|min:0|max:9999999',
            'mrp'              => 'nullable|numeric|min:0|max:9999999',
            'duration_days'    => 'required|integer|min:1|max:36500',
            'trial_days'       => 'nullable|integer|min:0|max:365',
            'card_limit'       => 'required|integer|min:-1|max:100000',
            'product_limit'    => 'required|integer|min:-1|max:100000',
            'service_limit'    => 'required|integer|min:-1|max:100000',
            'gallery_limit'    => 'required|integer|min:-1|max:100000',
            'video_limit'      => 'required|integer|min:-1|max:10000',
            'lead_limit'       => 'nullable|integer|min:-1|max:1000000',
            'storage_limit_mb' => 'required|integer|min:-1|max:1048576',
            'sort_order'       => 'nullable|integer|min:0|max:1000',
        ]);

        foreach ([
            'premium_templates', 'custom_domain', 'remove_branding', 'analytics', 'qr_download',
            'vcard', 'enquiry_form', 'seo_controls', 'api_access', 'priority_support',
            'is_free', 'is_active', 'is_featured',
        ] as $flag) {
            $data[$flag] = $this->request->bool($flag) ? 1 : 0;
        }

        // Optional numeric fields must land as numbers, not NULL — the
        // schema declares them NOT NULL.
        foreach (['trial_days' => 0, 'lead_limit' => -1, 'reseller_price' => null, 'mrp' => null] as $key => $fallback) {
            if (!isset($data[$key]) || $data[$key] === null || $data[$key] === '') {
                $data[$key] = $fallback;
            }
        }
        $data['trial_days'] = (int) $data['trial_days'];
        $data['lead_limit'] = (int) $data['lead_limit'];

        $features = array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            preg_split('/\r\n|\r|\n/', $this->request->string('features')) ?: []
        )));
        $data['features'] = array_slice($features, 0, 20);
        $data['currency'] = 'INR';
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
